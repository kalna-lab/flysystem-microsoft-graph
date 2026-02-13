<?php

/**
 * Example: Find Drive ID from SharePoint Site URL
 * 
 * This script shows how to automatically find Drive ID
 * from a SharePoint site URL.
 */

use KalnaLab\FlysystemMicrosoftGraph\Helpers\SharePointHelper;

// Example 1: Simple - Find drive ID from site URL
try {
    // Auto-resolves credentials from config
    $helper = new SharePointHelper();
    
    $siteUrl = 'https://contoso.sharepoint.com/sites/demo';
    $driveId = $helper->getDriveIdFromSiteUrl($siteUrl);
    
    echo "Drive ID for {$siteUrl}: {$driveId}\n";
    
} catch (Exception $e) {
    echo "Error: {$e->getMessage()}\n";
}

// Example 2: List all drives for a site
try {
    $helper = new SharePointHelper();
    
    $siteUrl = 'https://contoso.sharepoint.com/sites/demo';
    $drives = $helper->getAllDrivesForSite($siteUrl);
    
    echo "\nDocument libraries on the site:\n";
    foreach ($drives as $drive) {
        echo "  - {$drive['name']}: {$drive['id']}\n";
    }
    
} catch (Exception $e) {
    echo "Error: {$e->getMessage()}\n";
}

// Example 3: Test drive access
try {
    $helper = new SharePointHelper();
    
    $driveId = 'b!ABC123...';
    if ($helper->testDriveAccess($driveId)) {
        echo "\n✅ Has access to drive: {$driveId}\n";
    } else {
        echo "\n❌ No access to drive: {$driveId}\n";
    }
} catch (Exception $e) {
    echo "Error: {$e->getMessage()}\n";
}

// Example 4: Advanced - With custom Graph client (optional)
use KalnaLab\FlysystemMicrosoftGraph\TokenManager;
use Microsoft\Graph\Graph;

try {
    // If you want to use custom credentials (not from config)
    $tokenManager = new TokenManager(
        null, // Auto-resolve cache
        'custom-client-id',
        'custom-client-secret',
        'custom-tenant-id'
    );
    
    $graph = new Graph();
    $graph->setAccessToken($tokenManager->getAccessToken());
    
    // Pass custom Graph client
    $helper = new SharePointHelper($graph);
    $driveId = $helper->getDriveIdFromSiteUrl('https://contoso.sharepoint.com/sites/demo');
    
    echo "\nDrive ID (custom credentials): {$driveId}\n";
    
} catch (Exception $e) {
    echo "Error: {$e->getMessage()}\n";
}
