<?php

namespace Filacare\Flysystem\Oss\Contracts;

use Generator;
use Filacare\Flysystem\Oss\Exceptions\EncryptionException;

interface StreamGenerator
{
    /**
     * @param resource $resource
     *
     * @return Generator<string>
     *
     * @throws EncryptionException
     */
    public function encryptResourceToGenerator($resource): Generator;

    /**
     * @param resource $resource
     *
     * @return Generator<string>
     *
     * @throws EncryptionException
     */
    public function decryptResourceToGenerator($resource): Generator;

    /**
     * @param resource $resource
     *
     * @return array<string, mixed>|bool
     *
     * @throws EncryptionException
     */
    public function encryptedStreamStat($resource): array|bool;
}
