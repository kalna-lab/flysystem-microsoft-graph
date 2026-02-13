<?php

namespace KalnaLab\FlysystemMicrosoftGraph\Helpers;

use Microsoft\Graph\Graph;
use Microsoft\Graph\Model\Site;
use Microsoft\Graph\Model\Drive;
use KalnaLab\FlysystemMicrosoftGraph\TokenManager;

class SharePointHelper
{
    private Graph $graph;

    /**
     * @param Graph|null $graph Microsoft Graph client (null = auto-create)
     */
    public function __construct(?Graph $graph = null)
    {
        if ($graph === null) {
            // Auto-create Graph client with TokenManager
            $tokenManager = new TokenManager();
            $accessToken = $tokenManager->getAccessToken();
            
            $graph = new Graph();
            $graph->setAccessToken($accessToken);
        }
        
        $this->graph = $graph;
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

        // Get site using the identifier
        $site = $this->graph
            ->createRequest('GET', "/sites/{$siteIdentifier}")
            ->setReturnType(Site::class)
            ->execute();

        if (!$site || !$site->getId()) {
            throw new \RuntimeException('Site not found');
        }

        // Get default drive for the site
        $drive = $this->graph
            ->createRequest('GET', "/sites/{$site->getId()}/drive")
            ->setReturnType(Drive::class)
            ->execute();

        if (!$drive || !$drive->getId()) {
            throw new \RuntimeException('Drive not found for site');
        }

        return $drive->getId();
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

        // Search for sites
        $response = $this->graph
            ->createRequest('GET', "/sites?search={$siteName}")
            ->execute();

        if (!isset($response['value']) || empty($response['value'])) {
            throw new \RuntimeException("No site found matching: {$siteName}");
        }

        // Find exact match
        $site = null;
        foreach ($response['value'] as $foundSite) {
            if (isset($foundSite['webUrl']) && strpos($foundSite['webUrl'], $siteUrl) !== false) {
                $site = $foundSite;
                break;
            }
        }

        if (!$site || !isset($site['id'])) {
            throw new \RuntimeException("Site not found: {$siteUrl}");
        }

        // Get default drive
        $drive = $this->graph
            ->createRequest('GET', "/sites/{$site['id']}/drive")
            ->setReturnType(Drive::class)
            ->execute();

        if (!$drive || !$drive->getId()) {
            throw new \RuntimeException('Drive not found for site');
        }

        return $drive->getId();
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

        // Get site
        $site = $this->graph
            ->createRequest('GET', "/sites/{$siteIdentifier}")
            ->setReturnType(Site::class)
            ->execute();

        if (!$site || !$site->getId()) {
            throw new \RuntimeException('Site not found');
        }

        // Get all drives for the site
        $response = $this->graph
            ->createRequest('GET', "/sites/{$site->getId()}/drives")
            ->execute();

        $drives = [];
        if (isset($response['value'])) {
            foreach ($response['value'] as $drive) {
                $drives[] = [
                    'id' => $drive['id'] ?? null,
                    'name' => $drive['name'] ?? null,
                    'webUrl' => $drive['webUrl'] ?? null,
                    'driveType' => $drive['driveType'] ?? null,
                ];
            }
        }

        return $drives;
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
            $drive = $this->graph
                ->createRequest('GET', "/drives/{$driveId}")
                ->setReturnType(Drive::class)
                ->execute();

            return $drive && $drive->getId() !== null;
        } catch (\Exception $e) {
            return false;
        }
    }
}
