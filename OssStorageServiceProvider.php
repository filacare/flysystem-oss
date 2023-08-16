<?php

/*
 * This file is part of the filacare/flysystem-oss.
 *
 * (c) Filacare
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Filacare\Flysystem\Oss;

use League\Flysystem\Filesystem;
use Illuminate\Support\ServiceProvider;
use Illuminate\Filesystem\FilesystemAdapter;
use Filacare\Flysystem\Oss\Exceptions\EncryptionException;
use Filacare\Flysystem\Oss\StreamEncryption\SodiumStreamGenerator;

/**
 * Class OssStorageServiceProvider
 *
 * @author Filacare
 */
class OssStorageServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     *
     * @return void
     */
    public function boot()
    {
        app('filesystem')->extend('oss', function ($app, $config) {

            $encryptionKey = $config['encryptionKey'] ?? null;
            $adapter = new OssAdapter($config);

            if ($encryptionKey) {
                if(strlen($encryptionKey) == 32) {
                    $adapter = new OssAdapterEncryptionDecorator(
                        $adapter,
                        SodiumStreamGenerator::factory($encryptionKey, $config['chunkSize'] ?? 4096),
                    );
                } else {
                    throw new EncryptionException('Encryption key must be 32 bytes long');
                }
            }

            return new FilesystemAdapter(new Filesystem($adapter), $adapter, $config);
        });
    }
}
