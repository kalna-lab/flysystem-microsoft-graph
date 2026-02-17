<?php

namespace KalnaLab\FlysystemMicrosoftGraph;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Storage;
use League\Flysystem\Filesystem;

class MicrosoftGraphServiceProvider extends ServiceProvider
{
    /**
     * Register services
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/flysystem-msgraph.php',
            'flysystem-msgraph'
        );

        // Register console commands
        if ($this->app->runningInConsole()) {
            $this->commands([
                Console\FindDriveIdCommand::class,
            ]);
        }
    }

    /**
     * Bootstrap services
     */
    public function boot(): void
    {
        // Publish configuration
        $this->publishes([
            __DIR__.'/../config/flysystem-msgraph.php' => config_path('flysystem-msgraph.php'),
        ], 'flysystem-msgraph-config');

        // Register the Microsoft Graph filesystem driver
        Storage::extend('msgraph', function ($app, $config) {
            return $this->createFilesystem($config);
        });

        // Also register 'sharepoint' as an alias
        Storage::extend('sharepoint', function ($app, $config) {
            return $this->createFilesystem($config);
        });

        // Register download route if enabled
        $this->registerDownloadRoute();
    }

    /**
     * Create a Flysystem instance with Microsoft Graph adapter
     */
    private function createFilesystem(array $config): MicrosoftGraphFilesystemAdapter
    {
        // Validate required config
        $this->validateConfig($config);

        // Create token manager
        $tokenManager = new TokenManager(
            app('cache.store'),
            $config['clientId'],
            $config['clientSecret'],
            $config['tenantId']
        );

        // Get access token
        $accessToken = $tokenManager->getAccessToken();

        // Create GraphClient (compatible with old Graph API)
        $graphClient = new GraphClient($accessToken);

        // Create adapter
        $adapter = new MicrosoftGraphAdapter(
            $graphClient,
            $config['driveId'],
            $config['prefix'] ?? ''
        );

        // Return our custom FilesystemAdapter with temporaryUrl support
        return new MicrosoftGraphFilesystemAdapter(
            new Filesystem($adapter, [
                'visibility' => 'public',
                'disable_asserts' => true,
            ]),
            $adapter,
            $config
        );
    }

    /**
     * Validate configuration array
     */
    private function validateConfig(array $config): void
    {
        $required = ['clientId', 'clientSecret', 'tenantId', 'driveId'];

        foreach ($required as $key) {
            if (empty($config[$key])) {
                throw new \InvalidArgumentException(
                    "Microsoft Graph filesystem driver requires '{$key}' configuration."
                );
            }
        }
    }

    /**
     * Register the SharePoint download route
     */
    private function registerDownloadRoute(): void
    {
        // Only register if enabled
        if (!config('flysystem-msgraph.download_route.enabled', true)) {
            return;
        }

        $router = $this->app->make('router');
        
        // Download route (forces download)
        $router->middleware(config('flysystem-msgraph.download_route.middleware', ['web']))
            ->get(
                config('flysystem-msgraph.download_route.path', 'sharepoint/download/{itemId}'),
                [Http\Controllers\SharePointDownloadController::class, '__invoke']
            )
            ->name(config('flysystem-msgraph.download_route.name', 'sharepoint.download'));

        // View route (inline viewing in browser)
        $router->middleware(config('flysystem-msgraph.view_route.middleware', ['web']))
            ->get(
                config('flysystem-msgraph.view_route.path', 'sharepoint/view/{itemId}'),
                [Http\Controllers\SharePointViewController::class, '__invoke']
            )
            ->name(config('flysystem-msgraph.view_route.name', 'sharepoint.view'));
    }
}
