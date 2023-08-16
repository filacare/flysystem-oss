<?php

declare(strict_types=1);

namespace Filacare\Flysystem\Oss;

use function fopen;
use function fwrite;
use function rewind;
use League\Flysystem\Config;
use League\Flysystem\FilesystemAdapter;
use Filacare\Flysystem\Oss\Concerns\DecoratorTrait;
use Filacare\Flysystem\Oss\Contracts\StreamGenerator;
use Filacare\Flysystem\Oss\Exceptions\EncryptionException;
use Filacare\Flysystem\Oss\StreamEncryption\GeneratorReadStreamWrapper;

final class OssAdapterEncryptionDecorator implements FilesystemAdapter
{
    use DecoratorTrait;

    /**
     * @param FilesystemAdapter $adapter
     * @param array $config
     */
    public function __construct(
        private FilesystemAdapter $adapter,
        private StreamGenerator $streamGenerator
    ) {
    }

    /**
     * @return FilesystemAdapter
     */
    protected function getDecoratedAdapter(): FilesystemAdapter
    {
        return $this->adapter;
    }

    /**
     * @throws UnableToWriteFile
     * @throws FilesystemException
     */
    public function write(string $path, string $contents, Config $config): void
    {
        $encryptedContent = $this->streamGenerator->encryptResourceToGenerator(
            $this->createTemporaryStreamFromContents($contents)
        );

        $content = '';
        foreach ($encryptedContent as $chunk) {
            $content .= $chunk;
        }

        $this->getDecoratedAdapter()->write($path, $content, $config);
    }

    /**
     * @param string $path
     *
     * @return string
     */
    public function read(string $path): string
    {
        $encryptedContent = $this->getDecoratedAdapter()->readStream($path);
        if ($encryptedContent === false) {
            return false;
        }

        $decryptedContent = $this->streamGenerator->decryptResourceToGenerator($encryptedContent);

        $content = '';
        foreach ($decryptedContent as $chunk) {
            $content .= $chunk;
        }

        return $content;
    }

    /**
     * @param resource $contents
     *
     * @throws UnableToWriteFile
     * @throws FilesystemException
     */
    public function writeStream(string $path, $contents, Config $config): void
    {
        $this->getDecoratedAdapter()->writeStream(
            $path,
            GeneratorReadStreamWrapper::createStreamFromGenerator(
                $this->streamGenerator->encryptResourceToGenerator($contents),
                $this->streamGenerator->encryptedStreamStat($contents)
            ),
            $config
        );
    }

    /**
     * @return resource
     *
     * @throws UnableToReadFile
     * @throws FilesystemException
     */
    public function readStream($path)
    {
        $stream = $this->getDecoratedAdapter()->readStream($path);
        if ($stream === false) {
            return false;
        }

        return GeneratorReadStreamWrapper::createStreamFromGenerator(
            $this->streamGenerator->decryptResourceToGenerator($stream),
            fstat($stream)
        );
    }

    /**
     * @return resource
     */
    private function createTemporaryStreamFromContents(string $contents)
    {
        $source = fopen('php://memory', 'wb+');
        if ($source === false) {
            throw new EncryptionException();
        }

        fwrite($source, $contents);
        rewind($source);

        return $source;
    }

    public function __call($name, $arguments)
    {
        return $this->getDecoratedAdapter()->$name(...$arguments);
    }
}
