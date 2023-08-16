<?php

declare(strict_types=1);

namespace Filacare\Flysystem\Oss\StreamEncryption;

use Exception;
use Generator;
use function feof;

use function fread;
use InvalidArgumentException;
use Filacare\Flysystem\Oss\Contracts\StreamGenerator;
use Filacare\Flysystem\Oss\Exceptions\EncryptionException;

class SodiumStreamGenerator implements StreamGenerator
{
    public const MIN_CHUNK_SIZE = SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_HEADERBYTES + 1;
    public const MAX_CHUNK_SIZE = 8192;

    private function __construct(
        private string $key,
        private int $chunkSize = 4096
    ) {
    }

    public static function factory(string $key, int $chunkSize): self
    {
        if ($chunkSize < self::MIN_CHUNK_SIZE || $chunkSize > self::MAX_CHUNK_SIZE) {
            throw new InvalidArgumentException('Invalid chunk size');
        }

        return new self(
            $key,
            $chunkSize
        );
    }

    public function encryptResourceToGenerator($resource): Generator
    {
        [$stream, $header] = sodium_crypto_secretstream_xchacha20poly1305_init_push($this->key);

        yield $header;

        $tag = SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_MESSAGE;
        do {
            $chunk = fread($resource, $this->chunkSize);
            if ($chunk === false) {
                throw new Exception('Cannot encrypt file');
            }

            if (feof($resource)) {
                $tag = SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_FINAL;
            }

            yield sodium_crypto_secretstream_xchacha20poly1305_push($stream, $chunk, '', $tag);
        } while ($tag !== SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_FINAL);
    }

    public function decryptResourceToGenerator($resource): Generator
    {
        $header = fread($resource, SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_HEADERBYTES);
        if ($header === false) {
            throw new Exception('Cannot encrypt file');
        }

        $stream = sodium_crypto_secretstream_xchacha20poly1305_init_pull($header, $this->key);

        do {
            $chunk = fread($resource, $this->chunkSize + SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_ABYTES);
            if ($chunk === false) {
                throw new Exception('Cannot encrypt file');
            }

            [$decryptedChunk, $tag] = sodium_crypto_secretstream_xchacha20poly1305_pull($stream, $chunk);

            yield $decryptedChunk;
        } while (!feof($resource) && $tag !== SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_FINAL);

        $ok = feof($resource);

        if (!$ok) {
            throw new EncryptionException('Cannot decrypt the file');
        }
    }

    public function encryptedStreamStat($stream): array|bool
    {
        $stat = fstat($stream);
        if ($stat == false || $stat['size'] == 0) {
            return $stat;
        }

        $header_size = SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_HEADERBYTES;
        $block_size = $this->chunkSize;
        $tag_size = SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_ABYTES;
        $n_blocks = ceil($stat['size'] / $block_size);
        // $stat['size'] = $header_size + $n_blocks * ($block_size + $tag_size); // + $tag_size;
        $stat['size'] = $header_size + $stat['size'] + $n_blocks * $tag_size;

        return $stat;
    }
}
