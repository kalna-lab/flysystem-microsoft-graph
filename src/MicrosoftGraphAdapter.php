<?php

namespace KalnaLab\FlysystemMicrosoftGraph;

use GuzzleHttp\Exception\ClientException;
use League\Flysystem\Config;
use League\Flysystem\DirectoryAttributes;
use League\Flysystem\FileAttributes;
use League\Flysystem\FilesystemAdapter;
use League\Flysystem\PathPrefixer;
use League\Flysystem\UnableToCopyFile;
use League\Flysystem\UnableToCreateDirectory;
use League\Flysystem\UnableToDeleteDirectory;
use League\Flysystem\UnableToDeleteFile;
use League\Flysystem\UnableToGenerateTemporaryUrl;
use League\Flysystem\UnableToMoveFile;
use League\Flysystem\UnableToReadFile;
use League\Flysystem\UnableToRetrieveMetadata;
use League\Flysystem\UnableToSetVisibility;
use League\Flysystem\UnableToWriteFile;
use League\Flysystem\UrlGeneration\TemporaryUrlGenerator;

class MicrosoftGraphAdapter implements FilesystemAdapter, TemporaryUrlGenerator
{
    private GraphClient $graph;
    private string $driveId;
    private PathPrefixer $prefixer;

    /**
     * @param GraphClient $graph Microsoft Graph client instance
     * @param string $driveId Drive ID (SharePoint document library or OneDrive)
     * @param string $prefix Optional path prefix within the drive
     */
    public function __construct(GraphClient $graph, string $driveId, string $prefix = '')
    {
        $this->graph = $graph;
        $this->driveId = $driveId;
        $this->prefixer = new PathPrefixer($prefix);
    }

    /**
     * Get the GraphClient instance
     *
     * @return GraphClient
     */
    public function getGraphClient(): GraphClient
    {
        return $this->graph;
    }

    /**
     * Get the drive ID
     *
     * @return string
     */
    public function getDriveId(): string
    {
        return $this->driveId;
    }

    /**
     * Get the path prefixer
     *
     * @return PathPrefixer
     */
    public function getPrefixer(): PathPrefixer
    {
        return $this->prefixer;
    }

    public function fileExists(string $path): bool
    {
        try {
            $this->getMetadata($path);
            return true;
        } catch (UnableToRetrieveMetadata $e) {
            return false;
        }
    }

    public function directoryExists(string $path): bool
    {
        try {
            $metadata = $this->getMetadata($path);
            return $metadata->isDir();
        } catch (UnableToRetrieveMetadata $e) {
            return false;
        }
    }

    public function write(string $path, string $contents, Config $config): void
    {
        $this->upload($path, $contents);
    }

    public function writeStream(string $path, $contents, Config $config): void
    {
        if (!is_resource($contents)) {
            throw UnableToWriteFile::atLocation($path, 'Contents must be a resource');
        }

        $stream = stream_get_contents($contents);
        $this->upload($path, $stream);
    }

    public function read(string $path): string
    {
        try {
            $prefixedPath = $this->prefixer->prefixPath($path);
            $endpoint = "/drives/{$this->driveId}/root:/{$prefixedPath}:/content";

            $response = $this->graph->createRequest('GET', $endpoint)
                ->getStream();

            return $response->getContents();
        } catch (ClientException $e) {
            throw UnableToReadFile::fromLocation($path, $e->getMessage(), $e);
        } catch (\Exception $e) {
            throw UnableToReadFile::fromLocation($path, $e->getMessage(), $e);
        }
    }

    public function readStream(string $path)
    {
        try {
            $contents = $this->read($path);
            $stream = fopen('php://temp', 'r+');
            fwrite($stream, $contents);
            rewind($stream);
            return $stream;
        } catch (\Exception $e) {
            throw UnableToReadFile::fromLocation($path, $e->getMessage(), $e);
        }
    }

    public function delete(string $path): void
    {
        try {
            $prefixedPath = $this->prefixer->prefixPath($path);
            $endpoint = "/drives/{$this->driveId}/root:/{$prefixedPath}";

            $this->graph->createRequest('DELETE', $endpoint)->execute();
        } catch (ClientException $e) {
            if ($e->getCode() === 404) {
                // File doesn't exist, consider it deleted
                return;
            }
            throw UnableToDeleteFile::atLocation($path, $e->getMessage(), $e);
        } catch (\Exception $e) {
            throw UnableToDeleteFile::atLocation($path, $e->getMessage(), $e);
        }
    }

    public function deleteDirectory(string $path): void
    {
        try {
            $prefixedPath = $this->prefixer->prefixPath($path);
            $endpoint = "/drives/{$this->driveId}/root:/{$prefixedPath}";

            $this->graph->createRequest('DELETE', $endpoint)->execute();
        } catch (ClientException $e) {
            if ($e->getCode() === 404) {
                // Directory doesn't exist, consider it deleted
                return;
            }
            throw UnableToDeleteDirectory::atLocation($path, $e->getMessage(), $e);
        } catch (\Exception $e) {
            throw UnableToDeleteDirectory::atLocation($path, $e->getMessage(), $e);
        }
    }

    public function createDirectory(string $path, Config $config): void
    {
        try {
            $prefixedPath = $this->prefixer->prefixPath($path);
            $parts = explode('/', trim($prefixedPath, '/'));
            $folderName = array_pop($parts);
            $parentPath = implode('/', $parts);

            $endpoint = $parentPath
                ? "/drives/{$this->driveId}/root:/{$parentPath}:/children"
                : "/drives/{$this->driveId}/root/children";

            $this->graph->createRequest('POST', $endpoint)
                ->attachBody([
                    'name' => $folderName,
                    'folder' => (object)[],
                    '@microsoft.graph.conflictBehavior' => 'fail'
                ])
                ->execute();
        } catch (ClientException $e) {
            if ($e->getCode() === 409) {
                // Directory already exists, that's fine
                return;
            }
            throw UnableToCreateDirectory::atLocation($path, $e->getMessage(), $e);
        } catch (\Exception $e) {
            throw UnableToCreateDirectory::atLocation($path, $e->getMessage(), $e);
        }
    }

    public function setVisibility(string $path, string $visibility): void
    {
        throw UnableToSetVisibility::atLocation($path, 'Microsoft Graph does not support visibility');
    }

    public function visibility(string $path): FileAttributes
    {
        throw UnableToRetrieveMetadata::visibility($path, 'Microsoft Graph does not support visibility');
    }

    public function mimeType(string $path): FileAttributes
    {
        $metadata = $this->getMetadata($path);

        if ($metadata->mimeType() === null) {
            throw UnableToRetrieveMetadata::mimeType($path);
        }

        return $metadata;
    }

    public function lastModified(string $path): FileAttributes
    {
        return $this->getMetadata($path);
    }

    public function fileSize(string $path): FileAttributes
    {
        return $this->getMetadata($path);
    }

    public function listContents(string $path, bool $deep): iterable
    {
        try {
            $prefixedPath = $this->prefixer->prefixPath($path);

            $endpoint = $prefixedPath
                ? "/drives/{$this->driveId}/root:/{$prefixedPath}:/children"
                : "/drives/{$this->driveId}/root/children";

            $response = $this->graph->createRequest('GET', $endpoint)
                ->execute();

            $items = $response['value'] ?? [];
            foreach ($items as $item) {
                $parentPath = $item['parentReference']['path'] ?? '';
                $itemName = $item['name'] ?? '';

                // Strip drive prefix from path (e.g., /drives/xxx/root:)
                $parentPath = preg_replace('#^/drives/[^/]+/root:#', '', $parentPath);

                $itemPath = $this->prefixer->stripPrefix(
                    ltrim($parentPath . '/' . $itemName, '/')
                );

                if (isset($item['folder'])) {
                    yield new DirectoryAttributes($itemPath);

                    if ($deep) {
                        yield from $this->listContents($itemPath, true);
                    }
                } else {
                    yield new FileAttributes(
                        $itemPath,
                        $item['size'] ?? null,
                        null,
                        isset($item['lastModifiedDateTime']) ? strtotime($item['lastModifiedDateTime']) : null,
                        $item['file']['mimeType'] ?? null
                    );
                }
            }
        } catch (ClientException $e) {
            if ($e->getCode() === 404) {
                // Directory doesn't exist, return empty
                return;
            }
            throw $e;
        }
    }

    public function move(string $source, string $destination, Config $config): void
    {
        try {
            $prefixedSource = $this->prefixer->prefixPath($source);
            $prefixedDestination = $this->prefixer->prefixPath($destination);

            $destParts = explode('/', trim($prefixedDestination, '/'));
            $newName = array_pop($destParts);
            $newParentPath = implode('/', $destParts);

            // Get parent reference
            $parentEndpoint = $newParentPath
                ? "/drives/{$this->driveId}/root:/{$newParentPath}"
                : "/drives/{$this->driveId}/root";

            $parentItem = $this->graph->createRequest('GET', $parentEndpoint)
                ->execute();

            // Move the file
            $endpoint = "/drives/{$this->driveId}/root:/{$prefixedSource}";

            $this->graph->createRequest('PATCH', $endpoint)
                ->attachBody([
                    'parentReference' => [
                        'id' => $parentItem['id']
                    ],
                    'name' => $newName
                ])
                ->execute();
        } catch (\Exception $e) {
            throw UnableToMoveFile::fromLocationTo($source, $destination, $e);
        }
    }

    public function copy(string $source, string $destination, Config $config): void
    {
        try {
            $prefixedSource = $this->prefixer->prefixPath($source);
            $prefixedDestination = $this->prefixer->prefixPath($destination);

            $destParts = explode('/', trim($prefixedDestination, '/'));
            $newName = array_pop($destParts);
            $newParentPath = implode('/', $destParts);

            // Get parent reference
            $parentEndpoint = $newParentPath
                ? "/drives/{$this->driveId}/root:/{$newParentPath}"
                : "/drives/{$this->driveId}/root";

            $parentItem = $this->graph->createRequest('GET', $parentEndpoint)
                ->execute();

            // Copy the file
            $endpoint = "/drives/{$this->driveId}/root:/{$prefixedSource}:/copy";

            $this->graph->createRequest('POST', $endpoint)
                ->attachBody([
                    'parentReference' => [
                        'driveId' => $this->driveId,
                        'id' => $parentItem['id']
                    ],
                    'name' => $newName
                ])
                ->execute();

            // Copy operation is async in Graph API
            // For small files, we wait a bit
            sleep(1);
        } catch (\Exception $e) {
            throw UnableToCopyFile::fromLocationTo($source, $destination, $e);
        }
    }

    /**
     * Upload file contents to Microsoft Graph
     */
    private function upload(string $path, string $contents): void
    {
        try {
            $prefixedPath = $this->prefixer->prefixPath($path);
            $size = strlen($contents);

            // For files under 4MB, use simple upload
            if ($size < 4 * 1024 * 1024) {
                $endpoint = "/drives/{$this->driveId}/root:/{$prefixedPath}:/content";

                $this->graph->createRequest('PUT', $endpoint)
                    ->attachBody($contents)
                    ->execute();
            } else {
                // For larger files, use resumable upload
                $this->resumableUpload($prefixedPath, $contents);
            }
        } catch (\Exception $e) {
            throw UnableToWriteFile::atLocation($path, $e->getMessage(), $e);
        }
    }

    /**
     * Resumable upload for large files (4MB+)
     */
    private function resumableUpload(string $path, string $contents): void
    {
        $size = strlen($contents);

        // Create upload session
        $endpoint = "/drives/{$this->driveId}/root:/{$path}:/createUploadSession";
        $session = $this->graph->createRequest('POST', $endpoint)
            ->attachBody([
                'item' => [
                    '@microsoft.graph.conflictBehavior' => 'replace'
                ]
            ])
            ->execute();

        $uploadUrl = $session['uploadUrl'] ?? null;

        if (!$uploadUrl) {
            throw new \RuntimeException('Failed to create upload session: no uploadUrl returned');
        }

        // Upload in chunks of 5MB
        $chunkSize = 5 * 1024 * 1024;
        $offset = 0;

        while ($offset < $size) {
            $chunkEnd = min($offset + $chunkSize, $size) - 1;
            $chunk = substr($contents, $offset, $chunkSize);

            $headers = [
                'Content-Length' => strlen($chunk),
                'Content-Range' => "bytes {$offset}-{$chunkEnd}/{$size}"
            ];

            $client = new \GuzzleHttp\Client();
            $client->put($uploadUrl, [
                'headers' => $headers,
                'body' => $chunk
            ]);

            $offset += $chunkSize;
        }
    }

    /**
     * Get file/directory metadata
     */
    private function getMetadata(string $path): FileAttributes
    {
        try {
            $prefixedPath = $this->prefixer->prefixPath($path);
            $endpoint = "/drives/{$this->driveId}/root:/{$prefixedPath}";

            $item = $this->graph->createRequest('GET', $endpoint)
                ->execute();

            $isDir = isset($item['folder']);
            $size = $isDir ? null : ($item['size'] ?? null);
            $lastModified = isset($item['lastModifiedDateTime'])
                ? strtotime($item['lastModifiedDateTime'])
                : null;
            $mimeType = $isDir ? null : ($item['file']['mimeType'] ?? null);

            return new FileAttributes(
                $path,
                $size,
                null,
                $lastModified,
                $mimeType,
                [
                    'type' => $isDir ? 'dir' : 'file',
                    'timestamp' => $lastModified
                ]
            );
        } catch (ClientException $e) {
            if ($e->getCode() === 404) {
                throw UnableToRetrieveMetadata::create($path, 'metadata', 'File not found', $e);
            }
            throw UnableToRetrieveMetadata::create($path, 'metadata', $e->getMessage(), $e);
        } catch (\Exception $e) {
            throw UnableToRetrieveMetadata::create($path, 'metadata', $e->getMessage(), $e);
        }
    }

    /**
     * Generate a temporary URL for a file
     *
     * Supports two modes (configurable via config or parameter):
     * - 'share': Creates anonymous SharePoint sharing link (requires sharing enabled)
     * - 'download': Creates signed download route via application (default, more secure)
     *
     * @param string $path File path
     * @param \DateTimeInterface $expiresAt Expiration time
     * @param Config $config Additional configuration (mode: 'share'|'download' overrides config)
     * @return string Temporary URL
     */
    public function temporaryUrl(string $path, \DateTimeInterface $expiresAt, Config $config): string
    {
        // Get mode from parameter or fall back to config
        $mode = $config->get('mode') ?? config('flysystem-msgraph.temporary_url_type', 'share');

        if ($mode === 'share') {
            return $this->createSharingLink($path, $expiresAt);
        }

        return $this->createDownloadUrl($path, $expiresAt);
    }

    /**
     * Create SharePoint sharing link (requires sharing enabled on site)
     */
    private function createSharingLink(string $path, \DateTimeInterface $expiresAt): string
    {
        try {
            $prefixedPath = $this->prefixer->prefixPath($path);

            // Get the item to get its ID
            $itemEndpoint = "/drives/{$this->driveId}/root:/{$prefixedPath}";
            $item = $this->graph->createRequest('GET', $itemEndpoint)
                ->execute();

            if (!isset($item['id'])) {
                throw UnableToGenerateTemporaryUrl::noGeneratorConfigured($path);
            }

            // Create a sharing link with expiration
            $createLinkEndpoint = "/drives/{$this->driveId}/items/{$item['id']}/createLink";

            $response = $this->graph->createRequest('POST', $createLinkEndpoint)
                ->attachBody([
                    'type' => 'view',
                    'scope' => 'anonymous',
                    'expirationDateTime' => $expiresAt->format('Y-m-d\TH:i:s\Z')
                ])
                ->execute();

            if (!isset($response['link']['webUrl'])) {
                throw UnableToGenerateTemporaryUrl::noGeneratorConfigured($path);
            }

            return $response['link']['webUrl'];

        } catch (ClientException $e) {
            throw UnableToGenerateTemporaryUrl::dueToError($path, $e);
        } catch (\Exception $e) {
            throw UnableToGenerateTemporaryUrl::dueToError($path, $e);
        }
    }

    /**
     * Create signed download URL via application route
     */
    private function createDownloadUrl(string $path, \DateTimeInterface $expiresAt): string
    {
        try {
            $prefixedPath = $this->prefixer->prefixPath($path);

            // Get the item to get its ID
            $itemEndpoint = "/drives/{$this->driveId}/root:/{$prefixedPath}";
            $item = $this->graph->createRequest('GET', $itemEndpoint)
                ->execute();

            if (!isset($item['id'])) {
                throw UnableToGenerateTemporaryUrl::noGeneratorConfigured($path);
            }

            // Create signed URL with expiration
            // Format: /sharepoint/download/{itemId}?expires={timestamp}&signature={hash}
            $expires = $expiresAt->getTimestamp();
            $signature = hash_hmac('sha256', $item['id'] . $expires, config('app.key'));

            // Build URL with query parameters
            $baseUrl = url("/sharepoint/download/{$item['id']}");
            $queryString = http_build_query([
                'expires' => $expires,
                'signature' => $signature,
            ]);

            return $baseUrl . '?' . $queryString;

        } catch (ClientException $e) {
            throw UnableToGenerateTemporaryUrl::dueToError($path, $e);
        } catch (\Exception $e) {
            throw UnableToGenerateTemporaryUrl::dueToError($path, $e);
        }
    }
}
