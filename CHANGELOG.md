# Changelog

All notable changes to `kalna-lab/flysystem-microsoft-graph` will be documented in this file.

## [1.0.0] - 2024-02-11

### Added
- Initial release
- Flysystem v3 adapter for Microsoft Graph API v2.5
- Support for SharePoint and OneDrive
- Client credentials OAuth2 flow
- Automatic token management and caching
- Resumable uploads for large files (4MB+)
- Laravel 11+ service provider with auto-discovery
- Comprehensive documentation and examples
- Full Flysystem interface implementation:
  - `fileExists()` - Check file existence
  - `directoryExists()` - Check directory existence
  - `write()` - Write file contents
  - `writeStream()` - Write from stream
  - `read()` - Read file contents
  - `readStream()` - Read as stream
  - `delete()` - Delete file
  - `deleteDirectory()` - Delete directory
  - `createDirectory()` - Create directory
  - `listContents()` - List directory contents
  - `move()` - Move/rename file
  - `copy()` - Copy file
  - `lastModified()` - Get last modified timestamp
  - `fileSize()` - Get file size
  - `mimeType()` - Get MIME type

### Notes
- Visibility methods throw `UnableToSetVisibility` as Microsoft Graph does not support visibility
- Copy operations are asynchronous in Microsoft Graph API
- Tokens are cached for 58 minutes (expire at 60 minutes)
- Supports files up to 250GB using resumable upload

## [Unreleased]

### Planned
- PHPUnit test suite
- GitHub Actions CI/CD pipeline
- Support for shared permissions
- Batch operations for bulk file handling
- Streaming optimizations for very large files
