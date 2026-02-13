# 🚀 Quick Reference - Simple Usage

## Before (Old API)

```php
// ❌ Cumbersome - had to inject everything manually
use KalnaLab\FlysystemMicrosoftGraph\TokenManager;
use KalnaLab\FlysystemMicrosoftGraph\Helpers\SharePointHelper;
use Microsoft\Graph\Graph;

$tokenManager = new TokenManager(
    app('cache.store'),
    config('filesystems.disks.sharepoint.clientId'),
    config('filesystems.disks.sharepoint.clientSecret'),
    config('filesystems.disks.sharepoint.tenantId')
);

$graph = new Graph();
$graph->setAccessToken($tokenManager->getAccessToken());

$helper = new SharePointHelper($graph);
$driveId = $helper->getDriveIdFromSiteUrl($siteUrl);
```

## After (New API)

```php
// ✅ Super simple - automatically fetches from config
use KalnaLab\FlysystemMicrosoftGraph\Helpers\SharePointHelper;

$helper = new SharePointHelper();
$driveId = $helper->getDriveIdFromSiteUrl($siteUrl);
```

## 📋 Examples

### 1. Find Drive ID

```php
use KalnaLab\FlysystemMicrosoftGraph\Helpers\SharePointHelper;

$helper = new SharePointHelper();
$driveId = $helper->getDriveIdFromSiteUrl('https://contoso.sharepoint.com/sites/demo');

echo $driveId; // b!ABC123...
```

### 2. List All Drives

```php
$helper = new SharePointHelper();
$drives = $helper->getAllDrivesForSite('https://contoso.sharepoint.com/sites/demo');

foreach ($drives as $drive) {
    echo "{$drive['name']}: {$drive['id']}\n";
}
```

### 3. Test Drive Access

```php
$helper = new SharePointHelper();

if ($helper->testDriveAccess('b!ABC123...')) {
    echo "✅ Has access\n";
} else {
    echo "❌ No access\n";
}
```

### 4. In a Controller

```php
namespace App\Http\Controllers;

use KalnaLab\FlysystemMicrosoftGraph\Helpers\SharePointHelper;
use Illuminate\Http\Request;

class SharePointController extends Controller
{
    public function findDrive(Request $request)
    {
        $request->validate(['site_url' => 'required|url']);
        
        $helper = new SharePointHelper();
        $driveId = $helper->getDriveIdFromSiteUrl($request->site_url);
        
        return response()->json(['drive_id' => $driveId]);
    }
}
```

### 5. In a Livewire Component

```php
namespace App\Livewire;

use Livewire\Component;
use KalnaLab\FlysystemMicrosoftGraph\Helpers\SharePointHelper;

class SharePointSetup extends Component
{
    public $siteUrl = '';
    public $driveId = '';
    
    public function findDrive()
    {
        $helper = new SharePointHelper();
        $this->driveId = $helper->getDriveIdFromSiteUrl($this->siteUrl);
    }
}
```

### 6. In a Service Class

```php
namespace App\Services;

use KalnaLab\FlysystemMicrosoftGraph\Helpers\SharePointHelper;

class SharePointService
{
    private SharePointHelper $helper;
    
    public function __construct()
    {
        $this->helper = new SharePointHelper();
    }
    
    public function setupTenant(string $siteUrl): string
    {
        return $this->helper->getDriveIdFromSiteUrl($siteUrl);
    }
}
```

## 🔧 Advanced: Custom Credentials

If you need to use different credentials than those in config:

```php
use KalnaLab\FlysystemMicrosoftGraph\TokenManager;
use KalnaLab\FlysystemMicrosoftGraph\Helpers\SharePointHelper;
use Microsoft\Graph\Graph;

// TokenManager with custom credentials
$tokenManager = new TokenManager(
    null, // Auto-resolve cache
    'custom-client-id',
    'custom-client-secret',
    'custom-tenant-id'
);

// Graph client
$graph = new Graph();
$graph->setAccessToken($tokenManager->getAccessToken());

// Helper with custom Graph client
$helper = new SharePointHelper($graph);
$driveId = $helper->getDriveIdFromSiteUrl($siteUrl);
```

## 📍 Config Lookup Order

`TokenManager` and `SharePointHelper` search for credentials in the following order:

1. **Constructor parameters** (if provided)
2. **filesystems.disks.sharepoint.*** config
3. **flysystem-msgraph.defaults.*** config
4. **Throw exception** if not found

So you can have credentials in either:

```php
// config/filesystems.php
'disks' => [
    'sharepoint' => [
        'clientId' => env('MSGRAPH_CLIENT_ID'),
        'clientSecret' => env('MSGRAPH_CLIENT_SECRET'),
        'tenantId' => env('MSGRAPH_TENANT_ID'),
    ],
],

// OR config/flysystem-msgraph.php
'defaults' => [
    'client_id' => env('MSGRAPH_CLIENT_ID'),
    'client_secret' => env('MSGRAPH_CLIENT_SECRET'),
    'tenant_id' => env('MSGRAPH_TENANT_ID'),
],
```

## ✅ TL;DR

**Before:** Had to manually create TokenManager → Graph → Helper  
**Now:** Just `new SharePointHelper()` and it works! 🎉

All credentials are fetched automatically from config, but you can still override them if needed.
