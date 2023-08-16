<?php

declare(strict_types=1);

namespace Filacare\Flysystem\Oss\StreamEncryption;

use Generator;
use function feof;
use function fread;

use InvalidArgumentException;
use Filacare\Flysystem\Oss\Contracts\StreamGenerator;
use Filacare\Flysystem\Oss\Exceptions\EncryptionException;

class NormalStreamGenerator implements StreamGenerator
{
    public const MIN_CHUNK_SIZE = 1;
    public const MAX_CHUNK_SIZE = 8192;

    private int $chunkSize;

    private function __construct(int $chunkSize)
    {
        if ($chunkSize < self::MIN_CHUNK_SIZE || $chunkSize > self::MAX_CHUNK_SIZE) {
            throw new InvalidArgumentException('Invalid chunk size');
        }
        $this->chunkSize = $chunkSize;
    }

    public static function factory(int $chunkSize): self
    {
        return new self(
            $chunkSize
        );
    }

    /**
     * @param resource $resource
     *
     * @return Generator<string>
     */
    public function encryptResourceToGenerator($resource): Generator
    {
        do {
            $chunk = fread($resource, $this->chunkSize);
            if ($chunk === false) {
                throw new EncryptionException('Cannot read string from resource');
            }

            yield $chunk;
        } while (!feof($resource));
    }

    /**
     * @param resource $resource
     *
     * @return Generator<string>
     */
    public function decryptResourceToGenerator($resource): Generator
    {
        do {
            $chunk = fread($resource, $this->chunkSize);
            if ($chunk === false) {
                throw new EncryptionException('Cannot read string from resource');
            }
            yield $chunk;
        } while (!feof($resource));
    }

    public function encryptedStreamStat($stream): array|bool
    {
        return fstat($stream);
    }
}
