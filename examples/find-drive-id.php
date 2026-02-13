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
    // Auto-creates GraphServiceClient from config
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

// Example 4: Advanced - With custom GraphServiceClient
use Microsoft\Graph\GraphServiceClient;
use Microsoft\Kiota\Authentication\Oauth\ClientCredentialContext;

try {
    // Create custom authentication context
    $tokenRequestContext = new ClientCredentialContext(
        'custom-tenant-id',
        'custom-client-id',
        'custom-client-secret'
    );
    
    // Create GraphServiceClient
    $graphServiceClient = new GraphServiceClient($tokenRequestContext);
    
    // Pass custom client to helper
    $helper = new SharePointHelper($graphServiceClient);
    $driveId = $helper->getDriveIdFromSiteUrl('https://contoso.sharepoint.com/sites/demo');
    
    echo "\nDrive ID (custom credentials): {$driveId}\n";
    
} catch (Exception $e) {
    echo "Error: {$e->getMessage()}\n";
}
