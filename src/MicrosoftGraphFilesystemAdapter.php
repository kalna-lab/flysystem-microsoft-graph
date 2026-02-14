<?php

namespace KalnaLab\FlysystemMicrosoftGraph;

use Illuminate\Filesystem\FilesystemAdapter;
use League\Flysystem\FileAttributes;
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
     */
    public function metadata($path): FileAttributes
    {
        // Use reflection to access private getMetadata method
        $reflection = new \ReflectionMethod($this->adapter, 'getMetadata');
        $reflection->setAccessible(true);

        return $reflection->invoke($this->adapter, $path);
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
        return $metadata->extraMetadata()['item_id'] ?? null;
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
        return $metadata->extraMetadata()['web_url'] ?? null;
    }
}
