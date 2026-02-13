<?php

/**
 * Eksempel: Find Drive ID fra SharePoint Site URL
 * 
 * Dette script viser hvordan man finder Drive ID automatisk
 * fra en SharePoint site URL.
 */

use KalnaLab\FlysystemMicrosoftGraph\TokenManager;
use KalnaLab\FlysystemMicrosoftGraph\Helpers\SharePointHelper;
use Microsoft\Graph\Graph;

// Setup (i en Laravel app ville dette være i en controller/command)
$cache = app('cache.store');

// Opret token manager
$tokenManager = new TokenManager(
    $cache,
    config('filesystems.disks.sharepoint.clientId'),
    config('filesystems.disks.sharepoint.clientSecret'),
    config('filesystems.disks.sharepoint.tenantId')
);

// Få access token
$accessToken = $tokenManager->getAccessToken();

// Opret Graph client
$graph = new Graph();
$graph->setAccessToken($accessToken);

// Opret helper
$helper = new SharePointHelper($graph);

// Eksempel 1: Find drive ID fra site URL
try {
    $siteUrl = 'https://contoso.sharepoint.com/sites/demo';
    $driveId = $helper->getDriveIdFromSiteUrl($siteUrl);
    
    echo "Drive ID for {$siteUrl}: {$driveId}\n";
    
} catch (Exception $e) {
    echo "Fejl: {$e->getMessage()}\n";
}

// Eksempel 2: Vis alle drives for et site
try {
    $siteUrl = 'https://contoso.sharepoint.com/sites/demo';
    $drives = $helper->getAllDrivesForSite($siteUrl);
    
    echo "\nDokumentbiblioteker på sitet:\n";
    foreach ($drives as $drive) {
        echo "  - {$drive['name']}: {$drive['id']}\n";
    }
    
} catch (Exception $e) {
    echo "Fejl: {$e->getMessage()}\n";
}

// Eksempel 3: Test drive adgang
$driveId = 'b!ABC123...';
if ($helper->testDriveAccess($driveId)) {
    echo "\n✅ Har adgang til drive: {$driveId}\n";
} else {
    echo "\n❌ Ingen adgang til drive: {$driveId}\n";
}
