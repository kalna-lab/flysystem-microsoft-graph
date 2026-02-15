<?php

namespace KalnaLab\FlysystemMicrosoftGraph;

use Illuminate\Filesystem\FilesystemAdapter;
use League\Flysystem\FilesystemOperator;

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
     * @param \DateTimeInterface $expiration
     * @param array $options
     * @return string
     *
     * @throws \RuntimeException
     */
    public function temporaryUrl($path, $expiration, array $options = [])
    {
        return $this->adapter->temporaryUrl(
            $path,
            $expiration,
            new \League\Flysystem\Config($options)
        );
    }

    /**
     * Get complete file metadata including SharePoint-specific fields
     *
     * @param string $path
     * @return array ['path', 'size', 'timestamp', 'mime_type', 'extra' => ['type', 'timestamp', 'item_id', 'web_url', 'created_at', 'created_by', 'modified_at', 'modified_by', 'list_fields']]
     */
    public function metadata($path): array
    {
        // Use reflection to access private getMetadata method
        $reflection = new \ReflectionMethod($this->adapter, 'getMetadata');
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
        } catch (\Exception $e) {
            // List fields not available, continue without them
        }

        return $metadata;
    }

    /**
     * Get SharePoint item ID (GUID) for a file
     *
     * @param string $path
     * @return string|null
     */
    public function getItemId($path)
    {
        $metadata = $this->metadata($path);
        return $metadata['extra']['item_id'] ?? null;
    }

    /**
     * Get SharePoint web URL for a file
     *
     * @param string $path
     * @return string|null
     */
    public function getWebUrl($path)
    {
        $metadata = $this->metadata($path);
        return $metadata['extra']['web_url'] ?? null;
    }

    /**
     * Set SharePoint document title
     *
     * @param string $path
     * @param string $title
     * @return bool
     * @throws \Exception
     */
    public function setTitle($path, $title)
    {
        return $this->adapter->setTitle($path, $title);
    }

    /**
     * Get SharePoint document title
     *
     * @param string $path
     * @return string|null
     */
    public function getTitle($path)
    {
        return $this->adapter->getDocumentTitle($path);
    }

    /**
     * Set multiple SharePoint metadata fields
     *
     * @param string $path
     * @param array $fields
     * @return bool
     * @throws \Exception
     */
    public function setMetadataFields($path, array $fields)
    {
        return $this->adapter->setMetadataFields($path, $fields);
    }

    /**
     * Get all SharePoint list item fields (Title and custom fields)
     *
     * @param string $path
     * @return array|null
     */
    public function getListItemFields($path)
    {
        return $this->adapter->getListItemFields($path);
    }
}
