<?php

return [
    
    /*
    |--------------------------------------------------------------------------
    | Microsoft Graph Filesystem Configuration
    |--------------------------------------------------------------------------
    |
    | This configuration is used by the kalna-lab/flysystem-microsoft-graph
    | package to connect to SharePoint or OneDrive via Microsoft Graph API.
    |
    */

    'defaults' => [
        /*
        | Azure AD Application Credentials
        | These can be found in Azure Portal > Azure Active Directory > App registrations
        */
        'client_id' => env('MSGRAPH_CLIENT_ID'),
        'client_secret' => env('MSGRAPH_CLIENT_SECRET'),
        'tenant_id' => env('MSGRAPH_TENANT_ID'),

        /*
        | Drive ID
        | The ID of the SharePoint document library or OneDrive to use.
        | Find this using Microsoft Graph Explorer or PowerShell.
        */
        'drive_id' => env('MSGRAPH_DRIVE_ID'),

        /*
        | Path Prefix (Optional)
        | A prefix to prepend to all file paths. Useful for organizing files
        | in a specific subfolder within the drive.
        */
        'prefix' => env('MSGRAPH_PREFIX', ''),
    ],

    /*
    |--------------------------------------------------------------------------
    | Token Cache Configuration
    |--------------------------------------------------------------------------
    |
    | Access tokens are cached to avoid unnecessary API calls.
    | Tokens are typically valid for 60 minutes and are cached for 58 minutes.
    |
    */
    
    'token_cache_ttl' => env('MSGRAPH_TOKEN_CACHE_TTL', 58 * 60), // 58 minutes in seconds

    /*
    |--------------------------------------------------------------------------
    | API Configuration
    |--------------------------------------------------------------------------
    */
    
    'api' => [
        // Request timeout in seconds
        'timeout' => env('MSGRAPH_API_TIMEOUT', 300), // 5 minutes for large file uploads
        
        // Connection timeout in seconds
        'connect_timeout' => env('MSGRAPH_API_CONNECT_TIMEOUT', 10),
    ],
];
