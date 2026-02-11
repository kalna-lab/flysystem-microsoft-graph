<?php

namespace KalnaLab\FlysystemMicrosoftGraph;

use League\Flysystem\Config;
use League\Flysystem\FileAttributes;
use League\Flysystem\FilesystemAdapter;
use League\Flysystem\PathPrefixer;
use League\Flysystem\UnableToCheckFileExistence;
use League\Flysystem\UnableToCopyFile;
use League\Flysystem\UnableToCreateDirectory;
use League\Flysystem\UnableToDeleteDirectory;
use League\Flysystem\UnableToDeleteFile;
use League\Flysystem\UnableToMoveFile;
use League\Flysystem\UnableToReadFile;
use League\Flysystem\UnableToRetrieveMetadata;
use League\Flysystem\UnableToSetVisibility;
use League\Flysystem\UnableToWriteFile;
use League\Flysystem\DirectoryAttributes;
use Microsoft\Graph\Graph;
use Microsoft\Graph\Model\DriveItem;
use GuzzleHttp\Psr7\Stream;
use GuzzleHttp\Exception\ClientException;

class MicrosoftGraphAdapter implements FilesystemAdapter
{
    private Graph $graph;
    private string $driveId;
    private PathPrefixer $prefixer;
    
    /**
     * @param Graph $graph Microsoft Graph client instance
     * @param string $driveId Drive ID (SharePoint document library or OneDrive)
     * @param string $prefix Optional path prefix within the drive
     */
    public function __construct(Graph $graph, string $driveId, string $prefix = '')
    {
        $this->graph = $graph;
        $this->driveId = $driveId;
        $this->prefixer = new PathPrefixer($prefix);
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
                ->setReturnType(Stream::class)
                ->execute();

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
                    'folder' => new \stdClass(),
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
                ->setReturnType(DriveItem::class)
                ->execute();

            foreach ($response as $item) {
                $itemPath = $this->prefixer->stripPrefix(
                    ltrim($item->getParentReference()->getPath() . '/' . $item->getName(), '/')
                );

                if ($item->getFolder() !== null) {
                    yield new DirectoryAttributes($itemPath);
                    
                    if ($deep) {
                        yield from $this->listContents($itemPath, true);
                    }
                } else {
                    yield new FileAttributes(
                        $itemPath,
                        $item->getSize(),
                        null,
                        $item->getLastModifiedDateTime() 
                            ? strtotime($item->getLastModifiedDateTime()->format('c'))
                            : null,
                        $item->getFile()?->getMimeType()
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
                ->setReturnType(DriveItem::class)
                ->execute();

            // Move the file
            $endpoint = "/drives/{$this->driveId}/root:/{$prefixedSource}";
            
            $this->graph->createRequest('PATCH', $endpoint)
                ->attachBody([
                    'parentReference' => [
                        'id' => $parentItem->getId()
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
                ->setReturnType(DriveItem::class)
                ->execute();

            // Copy the file
            $endpoint = "/drives/{$this->driveId}/root:/{$prefixedSource}:/copy";
            
            $this->graph->createRequest('POST', $endpoint)
                ->attachBody([
                    'parentReference' => [
                        'driveId' => $this->driveId,
                        'id' => $parentItem->getId()
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

        $uploadUrl = $session->getUploadUrl();
        
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
                ->setReturnType(DriveItem::class)
                ->execute();

            $isDir = $item->getFolder() !== null;
            $size = $isDir ? null : $item->getSize();
            $lastModified = $item->getLastModifiedDateTime()
                ? strtotime($item->getLastModifiedDateTime()->format('c'))
                : null;
            $mimeType = $isDir ? null : $item->getFile()?->getMimeType();

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
}
