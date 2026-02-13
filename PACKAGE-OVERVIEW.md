# kalna-lab/flysystem-microsoft-graph - Package Overview

## 📦 Package Structure

```
flysystem-microsoft-graph/
├── .github/
│   └── workflows/
│       └── ci.yml                    # GitHub Actions CI/CD pipeline
├── config/
│   └── flysystem-msgraph.php         # Package configuration
├── src/
│   ├── MicrosoftGraphAdapter.php     # Core Flysystem v3 adapter
│   ├── TokenManager.php              # OAuth2 token management
│   └── MicrosoftGraphServiceProvider.php  # Laravel service provider
├── tests/
│   └── Unit/
│       └── TokenManagerTest.php      # Unit tests
├── .gitignore                        # Git ignore rules
├── CHANGELOG.md                      # Version history
├── composer.json                     # Package dependencies
├── CONTRIBUTING.md                   # Contribution guidelines
├── INSTALLATION.md                   # Detailed installation guide
├── LICENSE                           # MIT License
├── phpstan.neon                      # PHPStan static analysis config
├── phpunit.xml.dist                  # PHPUnit testing config
└── README.md                         # Main documentation
```

## 🚀 How to Publish to Packagist

### Step 1: Create GitHub Repository

1. Go to https://github.com/kalna-lab
2. Create new repository: `flysystem-microsoft-graph`
3. Make it public
4. Don't initialize with README (we have our own files)

### Step 2: Push Code to GitHub

```bash
cd flysystem-microsoft-graph

# Initialize git
git init

# Add all files
git add .

# Initial commit
git commit -m "Initial release v1.0.0"

# Add remote
git remote add origin https://github.com/kalna-lab/flysystem-microsoft-graph.git

# Push to main branch
git branch -M main
git push -u origin main

# Create release tag
git tag -a v1.0.0 -m "Release version 1.0.0"
git push origin v1.0.0
```

### Step 3: Publish to Packagist

1. Go to https://packagist.org/
2. Log in (or create account)
3. Click "Submit" in top menu
4. Enter repository URL: `https://github.com/kalna-lab/flysystem-microsoft-graph`
5. Click "Check"
6. Click "Submit" to add package

### Step 4: Set Up Auto-Update Hook

1. In Packagist, go to your package page
2. Click on your username → "Your packages"
3. Find `kalna-lab/flysystem-microsoft-graph`
4. Click package name
5. Copy the API token shown
6. Go to GitHub repository → Settings → Webhooks
7. Click "Add webhook"
8. Fill in:
   - **Payload URL**: `https://packagist.org/api/github?username=kalna-lab`
   - **Content type**: `application/json`
   - **Secret**: (the API token from Packagist)
   - **Which events**: Just the push event
9. Click "Add webhook"

Now Packagist will automatically update when you push to GitHub!

## 📊 Package Features

### Core Components

**MicrosoftGraphAdapter** (`src/MicrosoftGraphAdapter.php`)
- Implements Flysystem v3 `FilesystemAdapter` interface
- Full CRUD operations for files and directories
- Smart upload strategy (simple < 4MB, resumable >= 4MB)
- Comprehensive error handling with typed exceptions
- Support for streaming large files

**TokenManager** (`src/TokenManager.php`)
- OAuth2 client credentials flow
- Automatic token caching (58 minute TTL)
- Token refresh on expiry
- Configurable cache backend via Laravel

**MicrosoftGraphServiceProvider** (`src/MicrosoftGraphServiceProvider.php`)
- Auto-discovery in Laravel 11+
- Registers both 'msgraph' and 'sharepoint' drivers
- Configuration publishing
- Dependency injection setup

### Supported Operations

All standard Flysystem v3 operations:

✅ `fileExists()` - Check file existence  
✅ `directoryExists()` - Check directory existence  
✅ `write()` - Write file contents  
✅ `writeStream()` - Write from stream (memory efficient)  
✅ `read()` - Read file contents  
✅ `readStream()` - Read as stream  
✅ `delete()` - Delete file  
✅ `deleteDirectory()` - Delete directory recursively  
✅ `createDirectory()` - Create directory  
✅ `listContents()` - List directory contents (with deep option)  
✅ `move()` - Move/rename file  
✅ `copy()` - Copy file  
✅ `lastModified()` - Get last modified timestamp  
✅ `fileSize()` - Get file size in bytes  
✅ `mimeType()` - Get MIME type  

❌ `visibility()` - Not supported (Microsoft Graph limitation)  
❌ `setVisibility()` - Not supported (Microsoft Graph limitation)

## 🧪 Testing Strategy

### Unit Tests
- TokenManager cache key generation
- Token clearing functionality
- Configuration validation

### Integration Tests (TODO)
- Full file lifecycle (upload → read → delete)
- Directory operations
- Large file uploads (resumable)
- Error scenarios

### Manual Testing Checklist

Before release, test manually:

- [ ] Upload small file (< 4MB)
- [ ] Upload large file (> 4MB)
- [ ] Read file
- [ ] Delete file
- [ ] Create directory
- [ ] List directory contents
- [ ] Move file
- [ ] Copy file
- [ ] Token auto-refresh after expiry

## 📈 Version Roadmap

### v1.0.0 (Current)
- ✅ Core Flysystem adapter
- ✅ OAuth2 token management
- ✅ Laravel 11+ support
- ✅ Documentation
- ✅ Basic tests

### v1.1.0 (Planned)
- [ ] Comprehensive test suite
- [ ] Performance optimizations
- [ ] Batch operations
- [ ] Better error messages

### v1.2.0 (Future)
- [ ] Support for shared permissions
- [ ] Webhook support for file changes
- [ ] Advanced caching strategies
- [ ] Retry logic with exponential backoff

## 🔧 Maintenance

### Dependency Updates

Check for updates monthly:

```bash
composer outdated
```

Update dependencies:

```bash
composer update
composer test
```

### Security

Monitor for security vulnerabilities:

```bash
composer audit
```

### Compatibility

Test with new Laravel versions:
- Laravel 12 (when released)
- PHP 8.4 (when released)

## 📞 Support Channels

- **GitHub Issues**: Bug reports and feature requests
- **Email**: dev@kalna-lab.com
- **Documentation**: README.md and INSTALLATION.md

## 📄 License

MIT License - See LICENSE file

## 🙏 Credits

- Built on Flysystem v3 by Frank de Jonge
- Uses Microsoft Graph API v2.5
- Inspired by the Laravel community

---

**Ready to publish? Follow the publishing steps above!**
