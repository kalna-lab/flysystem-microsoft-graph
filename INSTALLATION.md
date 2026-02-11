# Installation Guide

This guide will walk you through installing and configuring `kalna-lab/flysystem-microsoft-graph` in your Laravel 11+ application.

## Prerequisites

Before installing, ensure you have:

- PHP 8.3 or higher
- Laravel 11.x or higher
- Composer
- Access to Azure Portal (to create an app registration)
- Admin access to your SharePoint site or OneDrive

## Step-by-Step Installation

### 1. Install the Package

```bash
composer require kalna-lab/flysystem-microsoft-graph
```

The package will be auto-discovered by Laravel.

### 2. Publish Configuration (Optional)

If you want to customize the configuration:

```bash
php artisan vendor:publish --tag=flysystem-msgraph-config
```

This creates `config/flysystem-msgraph.php`.

### 3. Azure AD Setup

#### Create Application Registration

1. Go to [Azure Portal](https://portal.azure.com/)
2. Navigate to: **Azure Active Directory** → **App registrations**
3. Click **New registration**
4. Fill in:
   - **Name**: `Laravel SharePoint Integration` (or your preferred name)
   - **Supported account types**: `Accounts in this organizational directory only`
   - **Redirect URI**: Leave blank (not needed for client credentials flow)
5. Click **Register**

#### Note Important Values

After registration, note these values:

- **Application (client) ID** - This is your `MSGRAPH_CLIENT_ID`
- **Directory (tenant) ID** - This is your `MSGRAPH_TENANT_ID`

#### Create Client Secret

1. In your app registration, click **Certificates & secrets** (left menu)
2. Click **New client secret**
3. Fill in:
   - **Description**: `Laravel Production` (or environment-specific name)
   - **Expires**: Choose based on your security policy (6 months, 12 months, or 24 months)
4. Click **Add**
5. **⚠️ IMPORTANT**: Copy the secret **Value** immediately - This is your `MSGRAPH_CLIENT_SECRET`
   - You won't be able to see it again!
   - Store it securely

#### Grant API Permissions

1. In your app registration, click **API permissions** (left menu)
2. Click **Add a permission**
3. Select **Microsoft Graph**
4. Select **Application permissions** (not Delegated)
5. Find and add these permissions:
   - Expand **Files** → Select `Files.ReadWrite.All`
   - Expand **Sites** → Select `Sites.ReadWrite.All`
6. Click **Add permissions**
7. **⚠️ CRITICAL**: Click **Grant admin consent for [Your Organization]**
   - This requires admin privileges
   - Without this, the app won't work
8. Verify both permissions show green checkmarks under "Status"
9. **Wait 2-5 minutes** for permissions to propagate through Microsoft's systems

### 4. Get Your Drive ID

You need to find the ID of your SharePoint document library or OneDrive.

#### Method A: Microsoft Graph Explorer (Easiest)

1. Go to [Microsoft Graph Explorer](https://developer.microsoft.com/en-us/graph/graph-explorer)
2. Click **Sign in to Graph Explorer** (top right)
3. Sign in with your Microsoft 365 account

**For SharePoint:**

4. In the query box, enter:
   ```
   https://graph.microsoft.com/v1.0/sites?search=YourSiteName
   ```
   Replace `YourSiteName` with your SharePoint site name
5. Click **Run query**
6. Copy the `id` from the response (looks like: `contoso.sharepoint.com,guid,guid`)
7. Now get the drives for that site:
   ```
   https://graph.microsoft.com/v1.0/sites/SITE-ID-FROM-STEP-6/drives
   ```
8. Find your document library in the response
9. Copy its `id` - This is your `MSGRAPH_DRIVE_ID`

**For OneDrive:**

4. In the query box, enter:
   ```
   https://graph.microsoft.com/v1.0/me/drive
   ```
5. Click **Run query**
6. Copy the `id` from the response - This is your `MSGRAPH_DRIVE_ID`

#### Method B: PowerShell (For SharePoint)

```powershell
# Install PnP PowerShell if needed
Install-Module -Name PnP.PowerShell

# Connect to your SharePoint site
Connect-PnPOnline -Url "https://yourtenant.sharepoint.com/sites/yoursite" -Interactive

# List all document libraries
Get-PnPList | Where-Object {$_.BaseTemplate -eq 101} | Select-Object Title, Id

# The Id is your MSGRAPH_DRIVE_ID
```

### 5. Configure Environment

Add these to your `.env` file:

```env
# Microsoft Graph API Credentials
MSGRAPH_CLIENT_ID=12345678-1234-1234-1234-123456789abc
MSGRAPH_CLIENT_SECRET=your-secret-value-here
MSGRAPH_TENANT_ID=87654321-4321-4321-4321-cba987654321

# Drive Configuration
MSGRAPH_DRIVE_ID=b!ABC123...xyz789

# Optional: Path prefix
MSGRAPH_PREFIX=laravel/storage
```

**⚠️ Security Note**: Never commit `.env` to version control!

### 6. Configure Filesystem Disk

Edit `config/filesystems.php`:

```php
'disks' => [
    
    // ... your existing disks ...
    
    'sharepoint' => [
        'driver' => 'msgraph',
        'clientId' => env('MSGRAPH_CLIENT_ID'),
        'clientSecret' => env('MSGRAPH_CLIENT_SECRET'),
        'tenantId' => env('MSGRAPH_TENANT_ID'),
        'driveId' => env('MSGRAPH_DRIVE_ID'),
        'prefix' => env('MSGRAPH_PREFIX', ''),
    ],
],
```

Optionally, set as default:

```php
'default' => env('FILESYSTEM_DISK', 'sharepoint'),
```

### 7. Test the Connection

Create a test route to verify everything works:

```php
// routes/web.php
use Illuminate\Support\Facades\Storage;

Route::get('/test-sharepoint', function () {
    try {
        // Create a test file
        $content = 'Test file created at ' . now();
        Storage::disk('sharepoint')->put('test.txt', $content);
        
        // Verify it exists
        if (!Storage::disk('sharepoint')->exists('test.txt')) {
            return 'ERROR: File was not created';
        }
        
        // Read it back
        $retrieved = Storage::disk('sharepoint')->get('test.txt');
        
        // Clean up
        Storage::disk('sharepoint')->delete('test.txt');
        
        return "SUCCESS! Content: {$retrieved}";
        
    } catch (\Exception $e) {
        return "ERROR: {$e->getMessage()}";
    }
});
```

Visit `/test-sharepoint` in your browser. You should see "SUCCESS!" if everything is configured correctly.

### 8. Common Setup Issues

#### "Failed to obtain access token"

- Verify client ID and secret are correct
- Check tenant ID matches your Azure AD
- Ensure no extra spaces in `.env` values

#### "Access denied" / "403 Forbidden"

- Verify API permissions are granted
- Ensure admin consent was clicked
- Wait 2-5 minutes after granting permissions
- Clear Laravel cache: `php artisan cache:clear`

#### "Resource not found" / "itemNotFound"

- Verify drive ID is correct
- Ensure the drive exists and is accessible
- Check the app has permission to access the drive

## Environment-Specific Setup

### Development

Use a separate Azure app registration for development:

```env
# .env.development
MSGRAPH_CLIENT_ID=dev-client-id
MSGRAPH_CLIENT_SECRET=dev-client-secret
MSGRAPH_DRIVE_ID=dev-drive-id
```

### Staging

```env
# .env.staging
MSGRAPH_CLIENT_ID=staging-client-id
MSGRAPH_CLIENT_SECRET=staging-client-secret
MSGRAPH_DRIVE_ID=staging-drive-id
```

### Production

```env
# .env.production
MSGRAPH_CLIENT_ID=prod-client-id
MSGRAPH_CLIENT_SECRET=prod-client-secret
MSGRAPH_DRIVE_ID=prod-drive-id
```

## Next Steps

- Read the [Usage Documentation](README.md#usage)
- Review [Security Best Practices](README.md#security-best-practices)
- Set up [monitoring and logging](README.md#monitoring--logging)

## Need Help?

- Check [Troubleshooting Guide](README.md#troubleshooting)
- Open an [issue on GitHub](https://github.com/kalna-lab/flysystem-microsoft-graph/issues)
- Email: contact@kalna.it
