<?php

namespace Filacare\Flysystem\Oss\Concerns;

use League\Flysystem\Config;
use League\Flysystem\FileAttributes;
use Filacare\Flysystem\Oss\OssAdapter;
use League\Flysystem\FilesystemAdapter;

trait DecoratorTrait
{
    /**
     * Get the decorated adapter.
     *
     * @return OssAdapter|FilesystemAdapter
     */
    abstract protected function getDecoratedAdapter();

    /**
     * @throws FilesystemException
     * @throws UnableToCheckExistence
     */
    public function fileExists(string $path): bool
    {
        return $this->getDecoratedAdapter()->fileExists($path);
    }

    /**
     * @throws FilesystemException
     * @throws UnableToCheckExistence
     */
    public function directoryExists(string $path): bool
    {
        return $this->getDecoratedAdapter()->directoryExists($path);
    }

    /**
     * @throws UnableToDeleteFile
     * @throws FilesystemException
     */
    public function delete(string $path): void
    {
        $this->getDecoratedAdapter()->delete($path);
    }

    /**
     * @throws UnableToDeleteDirectory
     * @throws FilesystemException
     */
    public function deleteDirectory(string $path): void
    {
        $this->getDecoratedAdapter()->deleteDirectory($path);
    }

    /**
     * @throws UnableToCreateDirectory
     * @throws FilesystemException
     */
    public function createDirectory(string $path, Config $config): void
    {
        $this->getDecoratedAdapter()->createDirectory($path, $config);
    }

    /**
     * @throws InvalidVisibilityProvided
     * @throws FilesystemException
     */
    public function setVisibility(string $path, string $visibility): void
    {
        $this->getDecoratedAdapter()->setVisibility($path, $visibility);
    }

    /**
     * @throws UnableToRetrieveMetadata
     * @throws FilesystemException
     */
    public function visibility(string $path): FileAttributes
    {
        return $this->getDecoratedAdapter()->visibility($path);
    }

    /**
     * @throws UnableToRetrieveMetadata
     * @throws FilesystemException
     */
    public function mimeType(string $path): FileAttributes
    {
        return $this->getDecoratedAdapter()->mimeType($path);
    }

    /**
     * @throws UnableToRetrieveMetadata
     * @throws FilesystemException
     */
    public function lastModified(string $path): FileAttributes
    {
        return $this->getDecoratedAdapter()->lastModified($path);
    }

    /**
     * @throws UnableToRetrieveMetadata
     * @throws FilesystemException
     */
    public function fileSize(string $path): FileAttributes
    {
        return $this->getDecoratedAdapter()->fileSize($path);
    }

    /**
     * @return iterable<StorageAttributes>
     *
     * @throws FilesystemException
     */
    public function listContents(string $path, bool $deep): iterable
    {
        return $this->getDecoratedAdapter()->listContents($path, $deep);
    }

    /**
     * @throws UnableToMoveFile
     * @throws FilesystemException
     */
    public function move(string $source, string $destination, Config $config): void
    {
        $this->getDecoratedAdapter()->move($source, $destination, $config);
    }

    /**
     * @throws UnableToCopyFile
     * @throws FilesystemException
     */
    public function copy(string $source, string $destination, Config $config): void
    {
        $this->getDecoratedAdapter()->copy($source, $destination, $config);
    }

    public function getUrl(string $path): string
    {
        return $this->getDecoratedAdapter()->getUrl($path);
    }

    public function getTemporaryUrl($path, \DateTimeInterface $expiration, array $options = [], string $method = 'GET')
    {
        return $this->getDecoratedAdapter()->getTemporaryUrl($path, $expiration, $options, $method);
    }
}
