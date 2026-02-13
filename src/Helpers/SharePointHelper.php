<?php

namespace KalnaLab\FlysystemMicrosoftGraph\Helpers;

use Microsoft\Graph\GraphServiceClient;
use Microsoft\Kiota\Authentication\Oauth\ClientCredentialContext;

class SharePointHelper
{
    private GraphServiceClient $graphServiceClient;

    /**
     * @param GraphServiceClient|null $graphServiceClient Microsoft Graph Service Client (null = auto-create)
     */
    public function __construct(?GraphServiceClient $graphServiceClient = null)
    {
        if ($graphServiceClient === null) {
            // Auto-create GraphServiceClient from config
            $clientId = config('filesystems.disks.sharepoint.clientId') 
                ?? config('flysystem-msgraph.defaults.client_id');
            $clientSecret = config('filesystems.disks.sharepoint.clientSecret') 
                ?? config('flysystem-msgraph.defaults.client_secret');
            $tenantId = config('filesystems.disks.sharepoint.tenantId') 
                ?? config('flysystem-msgraph.defaults.tenant_id');
            
            if (!$clientId || !$clientSecret || !$tenantId) {
                throw new \InvalidArgumentException('Microsoft Graph credentials not configured');
            }
            
            // Create authentication context
            $tokenRequestContext = new ClientCredentialContext(
                $tenantId,
                $clientId,
                $clientSecret
            );
            
            // Create GraphServiceClient
            $graphServiceClient = new GraphServiceClient($tokenRequestContext);
        }
        
        $this->graphServiceClient = $graphServiceClient;
    }

    /**
     * Get Drive ID from SharePoint site URL
     * 
     * @param string $siteUrl Full SharePoint site URL (e.g., https://contoso.sharepoint.com/sites/demo)
     * @return string Drive ID
     * @throws \Exception
     */
    public function getDriveIdFromSiteUrl(string $siteUrl): string
    {
        // Parse URL
        $parsedUrl = parse_url($siteUrl);
        
        if (!isset($parsedUrl['host']) || !isset($parsedUrl['path'])) {
            throw new \InvalidArgumentException('Invalid SharePoint site URL');
        }

        $hostname = $parsedUrl['host']; // e.g., contoso.sharepoint.com
        $sitePath = trim($parsedUrl['path'], '/'); // e.g., sites/demo

        // Method 1: Direct site lookup using hostname:path format
        try {
            return $this->getDriveIdDirectLookup($hostname, $sitePath);
        } catch (\Exception $e) {
            // Fallback to search method
            return $this->getDriveIdBySearch($siteUrl);
        }
    }

    /**
     * Get Drive ID using direct site lookup
     * 
     * @param string $hostname SharePoint hostname
     * @param string $sitePath Relative site path
     * @return string Drive ID
     */
    private function getDriveIdDirectLookup(string $hostname, string $sitePath): string
    {
        // Construct site identifier: hostname:path
        // Example: contoso.sharepoint.com:/sites/demo
        $siteIdentifier = $hostname . ':/' . $sitePath;

        try {
            // Get site using the identifier
            $site = $this->graphServiceClient
                ->sites()
                ->bySiteId($siteIdentifier)
                ->get()
                ->wait();

            if (!$site || !$site->getId()) {
                throw new \RuntimeException('Site not found');
            }

            // Get default drive for the site
            $drive = $this->graphServiceClient
                ->sites()
                ->bySiteId($site->getId())
                ->drive()
                ->get()
                ->wait();

            if (!$drive || !$drive->getId()) {
                throw new \RuntimeException('Drive not found for site');
            }

            return $drive->getId();
            
        } catch (\Exception $e) {
            throw new \RuntimeException("Failed to lookup site: {$e->getMessage()}", 0, $e);
        }
    }

    /**
     * Get Drive ID by searching for the site
     * 
     * @param string $siteUrl Full site URL to search for
     * @return string Drive ID
     */
    private function getDriveIdBySearch(string $siteUrl): string
    {
        // Extract site name from URL for search
        $siteName = basename(parse_url($siteUrl, PHP_URL_PATH));

        try {
            // Search for sites
            $searchResults = $this->graphServiceClient
                ->sites()
                ->get()
                ->wait();

            if (!$searchResults || !$searchResults->getValue()) {
                throw new \RuntimeException("No sites found");
            }

            // Find matching site
            $site = null;
            foreach ($searchResults->getValue() as $foundSite) {
                if ($foundSite->getWebUrl() && strpos($foundSite->getWebUrl(), $siteUrl) !== false) {
                    $site = $foundSite;
                    break;
                }
            }

            if (!$site || !$site->getId()) {
                throw new \RuntimeException("Site not found: {$siteUrl}");
            }

            // Get default drive
            $drive = $this->graphServiceClient
                ->sites()
                ->bySiteId($site->getId())
                ->drive()
                ->get()
                ->wait();

            if (!$drive || !$drive->getId()) {
                throw new \RuntimeException('Drive not found for site');
            }

            return $drive->getId();
            
        } catch (\Exception $e) {
            throw new \RuntimeException("Failed to search for site: {$e->getMessage()}", 0, $e);
        }
    }

    /**
     * Get all drives for a site
     * 
     * @param string $siteUrl Full SharePoint site URL
     * @return array Array of drives with id, name, and webUrl
     */
    public function getAllDrivesForSite(string $siteUrl): array
    {
        $parsedUrl = parse_url($siteUrl);
        $hostname = $parsedUrl['host'];
        $sitePath = trim($parsedUrl['path'], '/');
        $siteIdentifier = $hostname . ':/' . $sitePath;

        try {
            // Get site
            $site = $this->graphServiceClient
                ->sites()
                ->bySiteId($siteIdentifier)
                ->get()
                ->wait();

            if (!$site || !$site->getId()) {
                throw new \RuntimeException('Site not found');
            }

            // Get all drives for the site
            $drivesResult = $this->graphServiceClient
                ->sites()
                ->bySiteId($site->getId())
                ->drives()
                ->get()
                ->wait();

            $drives = [];
            if ($drivesResult && $drivesResult->getValue()) {
                foreach ($drivesResult->getValue() as $drive) {
                    $drives[] = [
                        'id' => $drive->getId(),
                        'name' => $drive->getName(),
                        'webUrl' => $drive->getWebUrl(),
                        'driveType' => $drive->getDriveType()?->value ?? null,
                    ];
                }
            }

            return $drives;
            
        } catch (\Exception $e) {
            throw new \RuntimeException("Failed to get drives for site: {$e->getMessage()}", 0, $e);
        }
    }

    /**
     * Test if we can access a specific drive
     * 
     * @param string $driveId Drive ID to test
     * @return bool
     */
    public function testDriveAccess(string $driveId): bool
    {
        try {
            $drive = $this->graphServiceClient
                ->drives()
                ->byDriveId($driveId)
                ->get()
                ->wait();

            return $drive && $drive->getId() !== null;
            
        } catch (\Exception $e) {
            return false;
        }
    }
}
