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
}
