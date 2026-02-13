<?php

namespace KalnaLab\FlysystemMicrosoftGraph;

use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\ServiceProvider;
use League\Flysystem\Filesystem;

class MicrosoftGraphServiceProvider extends ServiceProvider
{
    /**
     * Register services
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/flysystem-msgraph.php',
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
            __DIR__ . '/../config/flysystem-msgraph.php' => config_path('flysystem-msgraph.php'),
        ], 'flysystem-msgraph-config');

        // Register the Microsoft Graph filesystem driver
        Storage::extend('msgraph', function ($app, $config) {
            return $this->createFilesystem($config);
        });

        // Also register 'sharepoint' as an alias
        Storage::extend('sharepoint', function ($app, $config) {
            return $this->createFilesystem($config);
        });
    }

    /**
     * Create a Flysystem instance with Microsoft Graph adapter
     */
    private function createFilesystem(array $config): FilesystemAdapter
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

        // Return Laravel's FilesystemAdapter wrapping Flysystem
        return new FilesystemAdapter(
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
}
