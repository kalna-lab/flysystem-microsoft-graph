<?php

namespace KalnaLab\FlysystemMicrosoftGraph\Console;

use Illuminate\Console\Command;
use KalnaLab\FlysystemMicrosoftGraph\TokenManager;
use KalnaLab\FlysystemMicrosoftGraph\Helpers\SharePointHelper;
use Microsoft\Graph\Graph;

class FindDriveIdCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'msgraph:find-drive 
                            {site-url : SharePoint site URL (e.g., https://contoso.sharepoint.com/sites/demo)}
                            {--list-all : List all drives for the site}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Find SharePoint Drive ID from site URL';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $siteUrl = $this->argument('site-url');
        $listAll = $this->option('list-all');

        $this->info("Finder Drive ID for: {$siteUrl}");
        $this->newLine();

        try {
            // Verify credentials are configured
            $this->checkCredentials();

            // Create token manager
            $tokenManager = new TokenManager(
                app('cache.store'),
                config('filesystems.disks.sharepoint.clientId'),
                config('filesystems.disks.sharepoint.clientSecret'),
                config('filesystems.disks.sharepoint.tenantId')
            );

            // Get access token
            $this->info('Henter access token...');
            $accessToken = $tokenManager->getAccessToken();

            // Create Graph client
            $graph = new Graph();
            $graph->setAccessToken($accessToken);

            // Create helper
            $helper = new SharePointHelper($graph);

            if ($listAll) {
                // List all drives
                $this->info('Henter alle dokumentbiblioteker...');
                $drives = $helper->getAllDrivesForSite($siteUrl);

                if (empty($drives)) {
                    $this->warn('Ingen dokumentbiblioteker fundet.');
                    return 1;
                }

                $this->table(
                    ['Navn', 'Drive ID', 'Type'],
                    array_map(fn($drive) => [
                        $drive['name'],
                        $drive['id'],
                        $drive['driveType'] ?? 'N/A',
                    ], $drives)
                );

                $this->newLine();
                $this->info('💡 Brug et af disse Drive ID\'er i din .env fil:');
                $this->line('MSGRAPH_DRIVE_ID=' . $drives[0]['id']);

            } else {
                // Get default drive
                $this->info('Finder standard dokumentbibliotek...');
                $driveId = $helper->getDriveIdFromSiteUrl($siteUrl);

                $this->newLine();
                $this->info('✅ Drive ID fundet!');
                $this->newLine();
                
                $this->line('Tilføj denne linje til din .env fil:');
                $this->line('');
                $this->line('MSGRAPH_DRIVE_ID=' . $driveId);
                $this->line('');

                // Test access
                if ($helper->testDriveAccess($driveId)) {
                    $this->info('✅ Adgang verificeret - du kan bruge dette Drive ID');
                } else {
                    $this->warn('⚠️  Drive fundet, men adgang kunne ikke verificeres');
                }
            }

            return 0;

        } catch (\InvalidArgumentException $e) {
            $this->error('❌ Ugyldig URL: ' . $e->getMessage());
            return 1;
        } catch (\RuntimeException $e) {
            $this->error('❌ Fejl: ' . $e->getMessage());
            $this->newLine();
            $this->warn('Tjek at:');
            $this->line('  1. Site URL er korrekt');
            $this->line('  2. Du har adgang til sitet');
            $this->line('  3. API permissions er korrekt sat i Azure AD');
            return 1;
        } catch (\Exception $e) {
            $this->error('❌ Uventet fejl: ' . $e->getMessage());
            return 1;
        }
    }

    /**
     * Check that required credentials are configured
     */
    private function checkCredentials(): void
    {
        $required = [
            'filesystems.disks.sharepoint.clientId' => 'MSGRAPH_CLIENT_ID',
            'filesystems.disks.sharepoint.clientSecret' => 'MSGRAPH_CLIENT_SECRET',
            'filesystems.disks.sharepoint.tenantId' => 'MSGRAPH_TENANT_ID',
        ];

        $missing = [];
        foreach ($required as $config => $env) {
            if (empty(config($config))) {
                $missing[] = $env;
            }
        }

        if (!empty($missing)) {
            $this->error('❌ Manglende konfiguration i .env:');
            foreach ($missing as $env) {
                $this->line("  - {$env}");
            }
            $this->newLine();
            throw new \RuntimeException('Konfigurer Microsoft Graph credentials først');
        }
    }
}
