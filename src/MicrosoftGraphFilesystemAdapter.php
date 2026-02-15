<?php

namespace KalnaLab\FlysystemMicrosoftGraph;

use DateTimeInterface;
use Exception;
use Illuminate\Filesystem\FilesystemAdapter;
use League\Flysystem\Config;
use League\Flysystem\FilesystemOperator;
use ReflectionMethod;
use RuntimeException;

class MicrosoftGraphFilesystemAdapter extends FilesystemAdapter
{
    /**
     * @var MicrosoftGraphAdapter
     */
    protected $adapter;

    public function __construct(FilesystemOperator $driver, MicrosoftGraphAdapter $adapter, array $config = [])
    {
        parent::__construct($driver, $adapter, $config);
        $this->adapter = $adapter;
    }

    /**
     * Get a temporary URL for the file at the given path.
     *
     * @param string $path
     * @param DateTimeInterface $expiration
     * @param array $options
     * @return string
     *
     * @throws RuntimeException
     */
    public function temporaryUrl($path, $expiration, array $options = [])
    {
        return $this->adapter->temporaryUrl(
            $path,
            $expiration,
            new Config($options)
        );
    }

    /**
     * Get complete file metadata including SharePoint-specific fields
     *
     * @param string $path
     * @return array
     */
    public function metadata($path)
    {
        // Use reflection to access private getMetadata method
        $reflection = new ReflectionMethod($this->adapter, 'getMetadata');
        $reflection->setAccessible(true);

        $fileAttributes = $reflection->invoke($this->adapter, $path);

        $metadata = [
            'path' => $fileAttributes->path(),
            'size' => $fileAttributes->fileSize(),
            'timestamp' => $fileAttributes->lastModified(),
            'mime_type' => $fileAttributes->mimeType(),
            'extra' => $fileAttributes->extraMetadata(),
        ];
        $metadata['extra']['list_fields'] = [];

        // Try to get list item fields (Title, custom fields)
        try {
            $listFields = $this->adapter->getListItemFields($path);
            if ($listFields) {
                $metadata['extra']['list_fields'] = $listFields;
                // Also add Title directly for convenience
                if (isset($listFields['Title'])) {
                    $metadata['extra']['title'] = $listFields['Title'];
                }
            }
        } catch (Exception $e) {
            // List fields not available, continue without them
        }

        return $metadata;
    }

    /**
     * Get SharePoint item ID (GUID) for a file
     */
    public function getItemId(string $path): ?string
    {
        $metadata = $this->metadata($path);
        return $metadata['extra']['item_id'] ?? null;
    }

    /**
     * Get SharePoint web URL for a file
     */
    public function getWebUrl(string $path): ?string
    {
        $metadata = $this->metadata($path);
        return $metadata['extra']['web_url'] ?? null;
    }

    /**
     * Set SharePoint document title
     *
     * @throws Exception
     */
    public function setTitle(string $path, string $title): bool
    {
        return $this->adapter->setTitle($path, $title);
    }

    /**
     * Get SharePoint document title
     */
    public function getTitle(string $path): ?string
    {
        return $this->adapter->getDocumentTitle($path);
    }

    /**
     * Set multiple SharePoint metadata fields
     *
     * @throws Exception
     */
    public function setMetadataFields(string $path, array $fields): bool
    {
        return $this->adapter->setMetadataFields($path, $fields);
    }

    /**
     * Get all SharePoint list item fields (Title and custom fields)
     *
     * @param string $path
     * @return array|null
     */
    public function getListItemFields(string $path): ?array
    {
        return $this->adapter->getListItemFields($path);
    }

    /**
     * Get SharePoint web edit URL (opens in Word/Excel/PowerPoint Online)
     */
    public function getOnlineEditUrl(string $path): ?string
    {
        return $this->adapter->getOnlineEditUrl($path);
    }

    /**
     * Get desktop app edit URL (opens in desktop Word/Excel/PowerPoint)
     */
    public function getDesktopEditUrl(string $path): ?string
    {
        return $this->adapter->getDesktopEditUrl($path);
    }

    /**
     * Get all edit URLs for a document
     *
     * @return array ['view' => string, 'online' => string, 'desktop' => string]
     */
    public function getEditUrls(string $path): array
    {
        return $this->adapter->getEditUrls($path);
    }

    /**
     * Convert document to another format using SharePoint
     *
     * @param string $path
     * @param string $format Target format (e.g., 'pdf', 'jpg')
     * @return string|false Converted file contents
     * @throws Exception
     */
    public function convert(string $path, string $format = 'pdf'): string|false
    {
        return $this->adapter->convert($path, $format);
    }

    /**
     * Convert document by item ID
     *
     * @param string $itemId SharePoint item ID
     * @param string $format Target format (e.g., 'pdf')
     * @return string|false Converted file contents
     * @throws Exception
     */
    public function convertByItemId(string $itemId, string $format = 'pdf')
    {
        return $this->adapter->convertByItemId($itemId, $format);
    }
}
