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

        $this->info("Finding Drive ID for: {$siteUrl}");
        $this->newLine();

        try {
            // Verify credentials are configured
            $this->checkCredentials();

            // Create helper (auto-creates Graph client)
            $this->info('Connecting to Microsoft Graph...');
            $helper = new SharePointHelper();

            if ($listAll) {
                // List all drives
                $this->info('Fetching all document libraries...');
                $drives = $helper->getAllDrivesForSite($siteUrl);

                if (empty($drives)) {
                    $this->warn('No document libraries found.');
                    return 1;
                }

                $this->table(
                    ['Name', 'Drive ID', 'Type'],
                    array_map(fn($drive) => [
                        $drive['name'],
                        $drive['id'],
                        $drive['driveType'] ?? 'N/A',
                    ], $drives)
                );

                $this->newLine();
                $this->info('💡 Use one of these Drive IDs in your .env file:');
                $this->line('MSGRAPH_DRIVE_ID=' . $drives[0]['id']);

            } else {
                // Get default drive
                $this->info('Finding default document library...');
                $driveId = $helper->getDriveIdFromSiteUrl($siteUrl);

                $this->newLine();
                $this->info('✅ Drive ID found!');
                $this->newLine();
                
                $this->line('Add this line to your .env file:');
                $this->line('');
                $this->line('MSGRAPH_DRIVE_ID=' . $driveId);
                $this->line('');

                // Test access
                if ($helper->testDriveAccess($driveId)) {
                    $this->info('✅ Access verified - you can use this Drive ID');
                } else {
                    $this->warn('⚠️  Drive found, but access could not be verified');
                }
            }

            return 0;

        } catch (\InvalidArgumentException $e) {
            $this->error('❌ Invalid URL: ' . $e->getMessage());
            return 1;
        } catch (\RuntimeException $e) {
            $this->error('❌ Error: ' . $e->getMessage());
            $this->newLine();
            $this->warn('Please check that:');
            $this->line('  1. Site URL is correct');
            $this->line('  2. You have access to the site');
            $this->line('  3. API permissions are properly configured in Azure AD');
            return 1;
        } catch (\Exception $e) {
            $this->error('❌ Unexpected error: ' . $e->getMessage());
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
            $this->error('❌ Missing configuration in .env:');
            foreach ($missing as $env) {
                $this->line("  - {$env}");
            }
            $this->newLine();
            throw new \RuntimeException('Please configure Microsoft Graph credentials first');
        }
    }
}
