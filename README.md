# Flysystem Microsoft Graph Adapter

[![Latest Version on Packagist](https://img.shields.io/packagist/v/kalna-lab/flysystem-microsoft-graph.svg?style=flat-square)](https://packagist.org/packages/kalna-lab/flysystem-microsoft-graph)
[![Total Downloads](https://img.shields.io/packagist/dt/kalna-lab/flysystem-microsoft-graph.svg?style=flat-square)](https://packagist.org/packages/kalna-lab/flysystem-microsoft-graph)
[![License](https://img.shields.io/packagist/l/kalna-lab/flysystem-microsoft-graph.svg?style=flat-square)](https://packagist.org/packages/kalna-lab/flysystem-microsoft-graph)

A **production-ready** Flysystem v3 adapter for Microsoft Graph API v2.5, enabling seamless SharePoint and OneDrive integration in Laravel 11+ applications.

## ✨ Features

- ✅ **Microsoft Graph API v2.5** - Built for the latest Graph API
- ✅ **Flysystem v3** - Full compatibility with Laravel 11+ Storage facade
- ✅ **Client Credentials Flow** - Secure server-side OAuth2 authentication
- ✅ **Automatic Token Management** - Smart caching and refresh handling
- ✅ **Large File Support** - Resumable uploads for files up to 250GB
- ✅ **SharePoint & OneDrive** - Works with both platforms
- ✅ **Laravel 11+** - Designed for modern Laravel applications
- ✅ **Zero Configuration** - Auto-discovery and sensible defaults
- ✅ **Production Ready** - Battle-tested error handling and retry logic

## 📋 Requirements

- PHP 8.1, 8.2, or 8.3
- Laravel 11.x or higher
- Microsoft Graph API v2.5
- Azure AD application with appropriate permissions

## 📦 Installation

Install via Composer:

```bash
composer require kalna-lab/flysystem-microsoft-graph
```

The service provider will be automatically registered via Laravel's package discovery.

## ⚙️ Configuration

### Step 1: Azure AD App Registration

1. Go to [Azure Portal](https://portal.azure.com/)
2. Navigate to **Azure Active Directory** → **App registrations**
3. Click **New registration**
4. Enter a name (e.g., "Laravel SharePoint Integration")
5. Click **Register**
6. Note your **Application (client) ID** and **Directory (tenant) ID**

### Step 2: Create Client Secret

1. In your app registration, go to **Certificates & secrets**
2. Click **New client secret**
3. Add a description and set expiration
4. Click **Add**
5. **⚠️ Copy the secret value immediately** (you won't see it again!)

### Step 3: Grant API Permissions

1. Go to **API permissions**
2. Click **Add a permission** → **Microsoft Graph** → **Application permissions**
3. Add these permissions:
   - `Files.ReadWrite.All` - Read and write files in all site collections
   - `Sites.ReadWrite.All` - Read and write items in all site collections
4. Click **Grant admin consent** (requires admin privileges)
5. **Wait 2-5 minutes** for permissions to propagate

### Step 4: Get Your Drive ID

You need the Drive ID of your SharePoint document library or OneDrive.

#### Option A: Using the Artisan Command (Easiest!)

After installing the package and configuring credentials (steps 1-3), run:

```bash
# Find default drive for a SharePoint site
php artisan msgraph:find-drive "https://contoso.sharepoint.com/sites/yoursite"

# List all drives for a site
php artisan msgraph:find-drive "https://contoso.sharepoint.com/sites/yoursite" --list-all
```

The command will output your Drive ID ready to copy into `.env`.

#### Option B: Using Microsoft Graph Explorer

1. Go to [Graph Explorer](https://developer.microsoft.com/en-us/graph/graph-explorer)
2. Sign in with your account
3. Find your site:
   ```
   GET https://graph.microsoft.com/v1.0/sites?search=YourSiteName
   ```
4. Get drives for that site (copy the site ID from step 3):
   ```
   GET https://graph.microsoft.com/v1.0/sites/{site-id}/drives
   ```
5. Copy the `id` of your desired document library

#### Option B: Using PowerShell

```powershell
Connect-PnPOnline -Url "https://yourtenant.sharepoint.com/sites/yoursite"
Get-PnPList | Where-Object {$_.BaseTemplate -eq 101}
```

### Step 5: Configure Laravel

Add these environment variables to your `.env`:

```env
# Microsoft Graph API Credentials
MSGRAPH_CLIENT_ID=your-application-client-id
MSGRAPH_CLIENT_SECRET=your-client-secret-value
MSGRAPH_TENANT_ID=your-tenant-id

# SharePoint/OneDrive Configuration
MSGRAPH_DRIVE_ID=your-drive-id

# Optional: Path prefix within the drive
MSGRAPH_PREFIX=my-app/documents
```

### Step 6: Register Filesystem Disk

Add the disk configuration to `config/filesystems.php`:

```php
'disks' => [
    
    // Your existing disks...
    's3' => [
        'driver' => 's3',
        // ... S3 config
    ],
    
    // Microsoft Graph / SharePoint disk
    'sharepoint' => [
        'driver' => 'msgraph', // or 'sharepoint' - both work
        'clientId' => env('MSGRAPH_CLIENT_ID'),
        'clientSecret' => env('MSGRAPH_CLIENT_SECRET'),
        'tenantId' => env('MSGRAPH_TENANT_ID'),
        'driveId' => env('MSGRAPH_DRIVE_ID'),
        'prefix' => env('MSGRAPH_PREFIX', ''),
    ],
],

// Optional: Set as default disk
'default' => env('FILESYSTEM_DISK', 'sharepoint'),
```

## 🚀 Usage

Once configured, use Laravel's Storage facade as you normally would:

### Basic File Operations

```php
use Illuminate\Support\Facades\Storage;

// Upload a file
Storage::disk('sharepoint')->put('documents/report.pdf', $contents);

// Upload from a stream (memory efficient for large files)
$stream = fopen('/path/to/large-file.zip', 'r');
Storage::disk('sharepoint')->writeStream('backups/large-file.zip', $stream);

// Read a file
$contents = Storage::disk('sharepoint')->get('documents/report.pdf');

// Read as stream
$stream = Storage::disk('sharepoint')->readStream('documents/report.pdf');

// Check if file exists
if (Storage::disk('sharepoint')->exists('documents/report.pdf')) {
    // File exists
}

// Delete a file
Storage::disk('sharepoint')->delete('documents/report.pdf');

// Delete multiple files
Storage::disk('sharepoint')->delete(['file1.pdf', 'file2.pdf']);

// Copy a file
Storage::disk('sharepoint')->copy('old.pdf', 'new.pdf');

// Move a file
Storage::disk('sharepoint')->move('old-location.pdf', 'new-location.pdf');
```

### Directory Operations

```php
// Create a directory
Storage::disk('sharepoint')->makeDirectory('documents/2024');

// List files in a directory
$files = Storage::disk('sharepoint')->files('documents');

// List all files recursively
$files = Storage::disk('sharepoint')->allFiles('documents');

// List directories
$directories = Storage::disk('sharepoint')->directories('documents');

// List all directories recursively
$directories = Storage::disk('sharepoint')->allDirectories('documents');

// Delete a directory
Storage::disk('sharepoint')->deleteDirectory('old-documents');
```

### File Metadata

```php
// Get file size
$size = Storage::disk('sharepoint')->size('documents/report.pdf');

// Get last modified time
$timestamp = Storage::disk('sharepoint')->lastModified('documents/report.pdf');

// Get MIME type
$mimeType = Storage::disk('sharepoint')->mimeType('documents/report.pdf');
```

### Working with Uploaded Files

```php
// Store an uploaded file
$path = $request->file('document')->store('uploads', 'sharepoint');

// Store with custom name
$path = $request->file('document')->storeAs(
    'uploads', 
    'custom-name.pdf', 
    'sharepoint'
);

// Download a file
return Storage::disk('sharepoint')->download('documents/report.pdf');

// Download with custom name
return Storage::disk('sharepoint')->download(
    'documents/report.pdf', 
    'my-report.pdf'
);
```

### Using as Default Disk

If you set SharePoint as your default disk in `.env`:

```env
FILESYSTEM_DISK=sharepoint
```

You can omit the disk parameter:

```php
// These now use SharePoint automatically
Storage::put('file.txt', 'contents');
Storage::get('file.txt');
Storage::delete('file.txt');
```

## 🔄 Migration from S3

If you're migrating from S3 to SharePoint:

```php
// artisan command to migrate files
use Illuminate\Support\Facades\Storage;

$files = Storage::disk('s3')->allFiles();

foreach ($files as $file) {
    $contents = Storage::disk('s3')->get($file);
    Storage::disk('sharepoint')->put($file, $contents);
    
    // Verify
    if (Storage::disk('sharepoint')->exists($file)) {
        echo "Migrated: {$file}\n";
    }
}
```

## 🔍 Finding Drive ID Programmatically

If you need to find Drive IDs programmatically (e.g., for multi-tenant setups):

```php
use KalnaLab\FlysystemMicrosoftGraph\Helpers\SharePointHelper;

// Simple - auto-resolves credentials from config
$helper = new SharePointHelper();

// Find Drive ID from site URL
$driveId = $helper->getDriveIdFromSiteUrl('https://contoso.sharepoint.com/sites/demo');

// List all drives for a site
$drives = $helper->getAllDrivesForSite('https://contoso.sharepoint.com/sites/demo');
foreach ($drives as $drive) {
    echo "{$drive['name']}: {$drive['id']}\n";
}

// Test drive access
if ($helper->testDriveAccess($driveId)) {
    echo "Access verified!\n";
}
```

### Advanced: Custom Credentials

If you need to use different credentials than those in config:

```php
use KalnaLab\FlysystemMicrosoftGraph\TokenManager;
use KalnaLab\FlysystemMicrosoftGraph\Helpers\SharePointHelper;
use Microsoft\Graph\Graph;

// Create token manager with custom credentials
$tokenManager = new TokenManager(
    null, // Auto-resolve cache
    'custom-client-id',
    'custom-client-secret',
    'custom-tenant-id'
);

// Create Graph client
$graph = new Graph();
$graph->setAccessToken($tokenManager->getAccessToken());

// Pass to helper
$helper = new SharePointHelper($graph);
$driveId = $helper->getDriveIdFromSiteUrl('https://contoso.sharepoint.com/sites/demo');
```

## 🧪 Testing

You can use Laravel's Storage fake for testing:

```php
use Illuminate\Support\Facades\Storage;

public function test_file_upload()
{
    Storage::fake('sharepoint');
    
    // Perform file operations
    Storage::disk('sharepoint')->put('test.txt', 'contents');
    
    // Assert file was stored
    Storage::disk('sharepoint')->assertExists('test.txt');
}
```

## 🔐 Security Best Practices

1. **Never commit credentials** - Keep `.env` in `.gitignore`
2. **Use environment-specific apps** - Separate Azure apps for dev/staging/production
3. **Rotate secrets regularly** - Set expiration dates on client secrets
4. **Monitor access logs** - Review app activity in Azure Portal
5. **Principle of least privilege** - Only grant necessary permissions
6. **Secure your `.env`** - Set proper file permissions: `chmod 600 .env`

## 📊 Performance Considerations

### File Size Handling

- **< 4MB**: Simple upload (single request)
- **4MB - 250GB**: Resumable upload (chunked in 5MB pieces)

### Token Caching

Access tokens are automatically cached for 58 minutes (tokens expire in 60 minutes). The cache is invalidated automatically when credentials change.

### Rate Limiting

Microsoft Graph has the following rate limits:
- 4000 requests per 20 seconds per app per tenant

The adapter handles 429 responses gracefully, but consider implementing exponential backoff for high-volume applications.

## 🐛 Troubleshooting

### Permission Errors

**Error:** "Access denied" or "403 Forbidden"

**Solutions:**
1. Verify permissions are granted in Azure Portal
2. Ensure admin consent is granted (green checkmarks)
3. Wait 2-5 minutes after granting consent
4. Clear Laravel cache: `php artisan cache:clear`

### Authentication Errors

**Error:** "Failed to obtain access token" or "invalid_client"

**Solutions:**
1. Verify `MSGRAPH_CLIENT_ID` is correct
2. Verify `MSGRAPH_CLIENT_SECRET` hasn't expired
3. Check `MSGRAPH_TENANT_ID` matches your directory
4. Ensure no extra spaces in `.env` file

### Drive Not Found

**Error:** "itemNotFound" or "Resource not found"

**Solutions:**
1. Verify `MSGRAPH_DRIVE_ID` is correct
2. Check the app has access to the specified drive
3. Ensure the drive exists and hasn't been deleted

### Clear Token Cache

If experiencing authentication issues:

```bash
php artisan cache:clear
```

Or clear specific Microsoft Graph tokens:

```bash
php artisan tinker
>>> Cache::forget('msgraph_token_*');
```

## 🤝 Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

### Development Setup

```bash
# Clone the repository
git clone https://github.com/kalna-lab/flysystem-microsoft-graph.git
cd flysystem-microsoft-graph

# Install dependencies
composer install

# Run tests
composer test

# Run static analysis
composer analyse
```

## 📝 Changelog

Please see [CHANGELOG.md](CHANGELOG.md) for recent changes.

## 📄 License

This package is open-sourced software licensed under the [MIT license](LICENSE).

## 💡 Credits

- Developed by [Kalna](https://github.com/kalna-lab)
- Built on [Flysystem](https://flysystem.thephpleague.com/) by Frank de Jonge
- Powered by [Microsoft Graph API](https://docs.microsoft.com/en-us/graph/)

## 🙏 Support

- **Issues**: [GitHub Issues](https://github.com/kalna-lab/flysystem-microsoft-graph/issues)
- **Email**: contact@kalna.it

---

**Made with ❤️ by Kalna**
