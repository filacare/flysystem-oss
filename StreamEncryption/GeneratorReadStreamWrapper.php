<?php

declare(strict_types=1);

namespace Filacare\Flysystem\Oss\StreamEncryption;

use Generator;
use function strlen;
use function in_array;

use Psr\Log\NullLogger;
use Psr\Log\LoggerInterface;
use function stream_context_get_options;
use Filacare\Flysystem\Oss\Contracts\StreamWrapper;
use Filacare\Flysystem\Oss\Exceptions\StreamWrapperException;

class GeneratorReadStreamWrapper implements StreamWrapper
{
    public $context;

    public const PROTOCOL = 'generator';

    private string $buffer = '';

    private Generator $generator;
    private $stream_stat;
    private LoggerInterface $logger;

    /*
     * @throws StreamWrapperException
     * @return resource
     */
    public static function createStreamFromGenerator(
        Generator $generator,
        array|bool $stream_stat = [],
        LoggerInterface $logger = null
    ) {
        if (in_array(self::PROTOCOL, stream_get_wrappers(), true)) {
            stream_wrapper_unregister(self::PROTOCOL);
        }
        stream_wrapper_register(self::PROTOCOL, static::class);

        $options = [
            self::PROTOCOL => [
                'generator'   => $generator,
                'stream_stat' => $stream_stat,
                'logger'      => $logger ?? new NullLogger(),
            ],
        ];

        return fopen(self::PROTOCOL . '://', 'rb', false, stream_context_create($options));
    }

    /** @throws StreamWrapperException */
    public function stream_open(string $path, string $mode, int $options = STREAM_REPORT_ERRORS, string &$opened_path = null): bool
    {
        if (!in_array($mode, ['r', 'rb'], true)) {
            throw new StreamWrapperException('This stream is readonly');
        }

        $options = stream_context_get_options($this->context)[self::PROTOCOL];
        $this->generator = $options['generator'];
        $this->stream_stat = $options['stream_stat'];
        $this->logger = $options['logger'];

        return true;
    }

    public function stream_read(int $count): string
    {
        $this->logger->info("stream_read called asking for $count bytes");

        $out           = $this->buffer;
        $currentLength = strlen($this->buffer);

        while ($currentLength < $count && $this->generator->valid()) {
            $currentValue = $this->generator->current();
            $out          .= $currentValue;
            $this->generator->next();
            $currentLength += strlen($currentValue);
            $this->logger->info('loop read ' . strlen($currentValue) . ' bytes');
        }
        $this->logger->info("read $currentLength bytes");

        // grabbing the requested size from what has been read
        $returnValue = substr($out, 0, $count);

        // storing the rest of the read content into the buffer, so it gets picked up on the next iteration
        if (strlen($out) > $count) {
            $this->buffer = substr($out, $count);
        } else {
            $this->buffer = '';
        }

        $this->logger->info('buffer now contains ' . strlen($this->buffer));
        $this->logger->info('EOF?: ' . ($this->stream_eof() ? 'Y' : 'N'));

        return $returnValue;
    }

    public function stream_eof(): bool
    {
        return !$this->generator->valid() && $this->buffer === '';
    }

    public function stream_close(): void
    {
        $this->logger->info("stream closed. buffer is: $this->buffer");
    }

    public function stream_stat(): array|bool
    {
        return $this->stream_stat;
        return [
            'dev'     => 0,
            'ino'     => 0,
            'mode'    => 33060, // rb
            'nlink'   => 0,
            'uid'     => 0,
            'gid'     => 0,
            'rdev'    => 0,
            'size'    => 1,
            'atime'   => 0,
            'mtime'   => 0,
            'ctime'   => 0,
            'blksize' => 0,
            'blocks'  => 0,
        ];
    }
}
