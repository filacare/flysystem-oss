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

use League\Flysystem\Config;
use League\Flysystem\DirectoryAttributes;
use League\Flysystem\PathPrefixer;
use League\Flysystem\Visibility;
use OSS\Core\OssException;
use OSS\OssClient;
use League\Flysystem\FileAttributes;
use League\Flysystem\FilesystemAdapter;
use League\Flysystem\UnableToCopyFile;
use League\Flysystem\UnableToCreateDirectory;
use League\Flysystem\UnableToDeleteDirectory;
use League\Flysystem\UnableToDeleteFile;
use League\Flysystem\UnableToMoveFile;
use League\Flysystem\UnableToReadFile;
use League\Flysystem\UnableToRetrieveMetadata;
use League\Flysystem\UnableToSetVisibility;
use League\Flysystem\UnableToWriteFile;

/**
 * Class OssAdapter.
 *
 * @author Filacare
 */
class OssAdapter implements FilesystemAdapter
{
    // Client Configs
    private string $accessKeyId;
    private string $accessKeySecret;
    private string $endpoint;
    private bool $isCName = false;
    private ?string $securityToken = null;

    private OssClient $client;
    private string $bucket;
    private array $options = [];

    private PathPrefixer $prefixer;
    private VisibilityConverter $visibility;

    // Feature
    private ?string $cdnUrl = null;
    private bool $useSSL = false;

    public function __construct(
        private array $config
    ) {
        $ossConfig = [
            'accessKeyId' => $config['accessKeyId'],
            'accessKeySecret' => $config['accessKeySecret'],
            'endpoint' => $config['endpoint'],
            'isCName' => $config['isCName'] ?? false,
            'securityToken' => $config['securityToken'] ?? null,
        ];

        foreach ($ossConfig as $key => $value) {
            $this->$key = $value;
        }

        $this->client = new OssClient(...array_values($ossConfig));
        $this->bucket = $config['bucket'];
        $this->options = $config['options'] ?? [];
        $this->cdnUrl = $config['cdnUrl'] ?? null;

        $this->prefixer = new PathPrefixer($config['root'] ?? '');
        $this->visibility = new PortableVisibilityConverter();

        $this->checkEndpoint();
    }

    public function fileExists(string $path): bool
    {
        $path = $this->prefixer->prefixPath($path);

        try {
            return $this->client->doesObjectExist($this->bucket, $path);
        } catch (OssException $exception) {
            return false;
        }
    }

    public function directoryExists(string $path): bool
    {
        try {
            return $this->client->doesObjectExist($this->bucket, $this->prefixer->prefixDirectoryPath($path));
        } catch (OssException $exception) {
            return false;
        }
    }

    public function write(string $path, string $contents, Config $config): void
    {
        $path = $this->prefixer->prefixPath($path);
        $options = $this->createOptionsFromConfig($config);

        try {
            $this->client->putObject($this->bucket, $path, $contents, $options);
        } catch (\Exception $exception) {
            throw UnableToWriteFile::atLocation($path, $exception->getMessage());
        }
    }

    public function writeStream(string $path, $contents, Config $config): void
    {
        $path = $this->prefixer->prefixPath($path);
        $options = $this->createOptionsFromConfig($config);

        try {
            $this->client->uploadStream($this->bucket, $path, $contents, $options);
        } catch (OssException $exception) {
            throw UnableToWriteFile::atLocation($path, $exception->getErrorCode(), $exception);
        }
    }

    public function read(string $path): string
    {
        $path = $this->prefixer->prefixPath($path);

        try {
            return $this->client->getObject($this->bucket, $path);
        } catch (\Exception $exception) {
            throw UnableToReadFile::fromLocation($path, $exception->getMessage());
        }
    }

    public function readStream(string $path)
    {
        $stream = fopen('php://temp', 'w+b');

        try {
            fwrite($stream, $this->client->getObject($this->bucket, $path, [OssClient::OSS_FILE_DOWNLOAD => $stream]));
        } catch (OssException $exception) {
            fclose($stream);
            throw UnableToReadFile::fromLocation($path, $exception->getMessage());
        }
        rewind($stream);

        return $stream;
    }

    public function delete(string $path): void
    {
        $path = $this->prefixer->prefixPath($path);

        try {
            $this->client->deleteObject($this->bucket, $path);
        } catch (OssException $ossException) {
            throw UnableToDeleteFile::atLocation($path);
        }
    }

    public function deleteDirectory(string $path): void
    {
        try {
            $contents = $this->listContents($path, false);
            $files = [];
            foreach ($contents as $i => $content) {
                if ($content instanceof DirectoryAttributes) {
                    $this->deleteDirectory($content->path());
                    continue;
                }
                $files[] = $this->prefixer->prefixPath($content->path());
                if ($i && 0 == $i % 100) {
                    $this->client->deleteObjects($this->bucket, $files);
                    $files = [];
                }
            }
            !empty($files) && $this->client->deleteObjects($this->bucket, $files);
            $this->client->deleteObject($this->bucket, $this->prefixer->prefixDirectoryPath($path));
        } catch (OssException $exception) {
            throw UnableToDeleteDirectory::atLocation($path, $exception->getErrorCode(), $exception);
        }
    }

    public function createDirectory(string $path, Config $config): void
    {
        try {
            $this->client->createObjectDir($this->bucket, $this->prefixer->prefixPath($path));
        } catch (OssException $exception) {
            throw UnableToCreateDirectory::dueToFailure($path, $exception);
        }
    }

    public function setVisibility(string $path, string $visibility): void
    {
        $object = $this->prefixer->prefixPath($path);

        $acl = Visibility::PUBLIC === $visibility ? OssClient::OSS_ACL_TYPE_PUBLIC_READ : OssClient::OSS_ACL_TYPE_PRIVATE;

        try {
            $this->client->putObjectAcl($this->bucket, $object, $acl);
        } catch (OssException $exception) {
            throw UnableToSetVisibility::atLocation($path, $exception->getMessage());
        }
    }

    public function visibility(string $path): FileAttributes
    {
        try {
            $acl = $this->client->getObjectAcl($this->bucket, $this->prefixer->prefixPath($path), []);
        } catch (OssException $exception) {
            throw UnableToRetrieveMetadata::visibility($path, $exception->getMessage());
        }

        return new FileAttributes($path, null, OssClient::OSS_ACL_TYPE_PRIVATE === $acl ? Visibility::PRIVATE : Visibility::PUBLIC);
    }

    public function mimeType(string $path): FileAttributes
    {
        $meta = $this->getMetadata($path);
        if (null === $meta->mimeType()) {
            throw UnableToRetrieveMetadata::mimeType($path);
        }

        return $meta;
    }

    public function lastModified(string $path): FileAttributes
    {
        $meta = $this->getMetadata($path);
        if (null === $meta->lastModified()) {
            throw UnableToRetrieveMetadata::lastModified($path);
        }

        return $meta;
    }

    public function fileSize(string $path): FileAttributes
    {
        $meta = $this->getMetadata($path);
        if (null === $meta->fileSize()) {
            throw UnableToRetrieveMetadata::fileSize($path);
        }

        return $meta;
    }

    public function listContents(string $path, bool $deep): iterable
    {
        $directory = $this->prefixer->prefixDirectoryPath($path);
        $nextMarker = '';
        while (true) {
            $options = [
                OssClient::OSS_PREFIX => $directory,
                OssClient::OSS_MARKER => $nextMarker,
            ];

            try {
                $listObjectInfo = $this->client->listObjects($this->bucket, $options);
                $nextMarker = $listObjectInfo->getNextMarker();
            } catch (OssException $exception) {
                throw new \Exception($exception->getErrorMessage(), 0, $exception);
            }

            $prefixList = $listObjectInfo->getPrefixList();
            foreach ($prefixList as $prefixInfo) {
                $subPath = $this->prefixer->stripDirectoryPrefix($prefixInfo->getPrefix());
                if ($subPath == $path) {
                    continue;
                }
                yield new DirectoryAttributes($subPath);
                if (true === $deep) {
                    $contents = $this->listContents($subPath, $deep);
                    foreach ($contents as $content) {
                        yield $content;
                    }
                }
            }

            $listObject = $listObjectInfo->getObjectList();
            if (!empty($listObject)) {
                foreach ($listObject as $objectInfo) {
                    $objectPath = $this->prefixer->stripPrefix($objectInfo->getKey());
                    $objectLastModified = strtotime($objectInfo->getLastModified());
                    if ('/' == substr($objectPath, -1, 1)) {
                        continue;
                    }
                    yield new FileAttributes($objectPath, $objectInfo->getSize(), null, $objectLastModified);
                }
            }

            if ('true' !== $listObjectInfo->getIsTruncated()) {
                break;
            }
        }
    }

    public function move(string $source, string $destination, Config $config): void
    {
        try {
            $this->copy($source, $destination, $config);
            $this->delete($source);
        } catch (\Exception $exception) {
            throw UnableToMoveFile::fromLocationTo($source, $destination);
        }
    }

    public function copy(string $source, string $destination, Config $config): void
    {
        $path = $this->prefixer->prefixPath($source);
        $newPath = $this->prefixer->prefixPath($destination);

        try {
            $this->client->copyObject($this->bucket, $path, $this->bucket, $newPath);
        } catch (OssException $exception) {
            throw UnableToCopyFile::fromLocationTo($source, $destination);
        }
    }

    // Laravel Call Adapter Method

    /**
     * Laravel Storage::disk()->url()
     */
    public function getUrl(string $path): string
    {
        $path = $this->prefixer->prefixPath($path);

        if (!is_null($this->cdnUrl)) {
            return rtrim($this->cdnUrl, '/') . '/' . ltrim($path, '/');
        }

        return $this->normalizeHost() . ltrim($path, '/');
    }

    /**
     * Get a temporary URL for the file at the given path
     *
     * Laravel Storage::disk()->temporaryUrl()
     */
    public function getTemporaryUrl($path, \DateTimeInterface $expiration, array $options = [], string $method = OssClient::OSS_HTTP_GET)
    {
        $path = $this->prefixer->prefixPath($path);

        $timeout = $expiration->getTimestamp() - time();

        try {
            $path = $this->client->signUrl($this->bucket, $path, $timeout, $method, $options);
        } catch (OssException $exception) {
            return false;
        }

        return $path;
    }

    // Support Laravel Upload Acl And Custom Options
    private function createOptionsFromConfig(Config $config): array
    {
        // ['x-oss-object-acl', 'visibility']
        $visibility = (string) $config->get(Config::OPTION_VISIBILITY, Visibility::PRIVATE);

        $options = [OssClient::OSS_HEADERS => [
            ...$this->options,
            OssClient::OSS_OBJECT_ACL => $this->visibility->visibilityToAcl($visibility),
        ]];

        return $options;
    }

    // 以下为附加特性/方法

    public function bucket(string $bucket): OssAdapter
    {
        $this->bucket = $bucket;
        return $this;
    }

    public function cdnUrl(?string $url): OssAdapter
    {
        $this->cdnUrl = $url;
        return $this;
    }

    public function getMetadata($path): FileAttributes
    {
        try {
            $result = $this->client->getObjectMeta($this->bucket, $this->prefixer->prefixPath($path));
        } catch (OssException $exception) {
            throw UnableToRetrieveMetadata::create($path, 'metadata', $exception->getErrorCode(), $exception);
        }

        $size = isset($result['content-length']) ? intval($result['content-length']) : 0;
        $timestamp = isset($result['last-modified']) ? strtotime($result['last-modified']) : 0;
        $mimetype = $result['content-type'] ?? '';

        return new FileAttributes($path, $size, null, $timestamp, $mimetype);
    }

    /**
     * get aliyun sdk kernel object
     */
    public function getClient(): OssClient
    {
        return $this->client;
    }


    /**
     * normalize Host.
     */
    protected function normalizeHost(): string
    {
        if ($this->isCName) {
            $domain = $this->endpoint;
        } else {
            $domain = $this->bucket . '.' . $this->endpoint;
        }

        if ($this->useSSL) {
            $domain = "https://{$domain}";
        } else {
            $domain = "http://{$domain}";
        }

        return rtrim($domain, '/') . '/';
    }

    /**
     * Check the endpoint to see if SSL can be used.
     */
    protected function checkEndpoint()
    {
        if (0 === strpos($this->endpoint, 'http://')) {
            $this->endpoint = substr($this->endpoint, strlen('http://'));
            $this->useSSL = false;
        } elseif (0 === strpos($this->endpoint, 'https://')) {
            $this->endpoint = substr($this->endpoint, strlen('https://'));
            $this->useSSL = true;
        }
    }

    /**
     * 验签.
     */
    public function verify(): array
    {
        // oss 前面header、公钥 header
        $authorizationBase64 = '';
        $pubKeyUrlBase64 = '';

        if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
            $authorizationBase64 = $_SERVER['HTTP_AUTHORIZATION'];
        }

        if (isset($_SERVER['HTTP_X_OSS_PUB_KEY_URL'])) {
            $pubKeyUrlBase64 = $_SERVER['HTTP_X_OSS_PUB_KEY_URL'];
        }

        // 验证失败
        if ('' == $authorizationBase64 || '' == $pubKeyUrlBase64) {
            return [false, ['CallbackFailed' => 'authorization or pubKeyUrl is null']];
        }

        // 获取OSS的签名
        $authorization = base64_decode($authorizationBase64);
        // 获取公钥
        $pubKeyUrl = base64_decode($pubKeyUrlBase64);
        // 请求验证
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $pubKeyUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        $pubKey = curl_exec($ch);

        if ('' == $pubKey) {
            return [false, ['CallbackFailed' => 'curl is fail']];
        }

        // 获取回调 body
        $body = file_get_contents('php://input');
        // 拼接待签名字符串
        $path = $_SERVER['REQUEST_URI'];
        $pos = strpos($path, '?');
        if (false === $pos) {
            $authStr = urldecode($path) . "\n" . $body;
        } else {
            $authStr = urldecode(substr($path, 0, $pos)) . substr($path, $pos, strlen($path) - $pos) . "\n" . $body;
        }
        // 验证签名
        $ok = openssl_verify($authStr, $authorization, $pubKey, OPENSSL_ALGO_MD5);

        if (1 !== $ok) {
            return [false, ['CallbackFailed' => 'verify is fail, Illegal data']];
        }

        parse_str($body, $data);

        return [true, $data];
    }

    /**
     * oss 直传配置.
     *
     * @param null $callBackUrl
     *
     * @return false|string
     *
     * @throws \Exception
     */

    // System Params
    const SYSTEM_FIELD = [
        'bucket' => '${bucket}',
        'etag' => '${etag}',
        'filename' => '${object}',
        'size' => '${size}',
        'mimeType' => '${mimeType}',
        'height' => '${imageInfo.height}',
        'width' => '${imageInfo.width}',
        'format' => '${imageInfo.format}',
    ];
    public function signatureConfig(string $prefix = '', $callBackUrl = null, array $customData = [], int $expire = 30, int $contentLengthRangeValue = 1048576000, array $systemData = [])
    {
        $prefix = $this->prefixer->prefixPath($prefix);

        // 系统参数
        $system = [];
        if (empty($systemData)) {
            $system = self::SYSTEM_FIELD;
        } else {
            foreach ($systemData as $key => $value) {
                if (!in_array($value, self::SYSTEM_FIELD)) {
                    throw new \InvalidArgumentException("Invalid oss system filed: {$value}");
                }
                $system[$key] = $value;
            }
        }

        // 自定义参数
        $callbackVar = [];
        $data = [];
        if (!empty($customData)) {
            foreach ($customData as $key => $value) {
                $callbackVar['x:' . $key] = $value;
                $data[$key] = '${x:' . $key . '}';
            }
        }

        $callbackParam = [
            'callbackUrl' => $callBackUrl,
            'callbackBody' => urldecode(http_build_query(array_merge($system, $data))),
            'callbackBodyType' => 'application/x-www-form-urlencoded',
        ];
        $callbackString = json_encode($callbackParam);
        $base64CallbackBody = base64_encode($callbackString);

        $now = time();
        $end = $now + $expire;
        $expiration = $this->gmt_iso8601($end);

        // 最大文件大小.用户可以自己设置
        $condition = [
            0 => 'content-length-range',
            1 => 0,
            2 => $contentLengthRangeValue,
        ];
        $conditions[] = $condition;

        $start = [
            0 => 'starts-with',
            1 => '$key',
            2 => $prefix,
        ];
        $conditions[] = $start;

        $arr = [
            'expiration' => $expiration,
            'conditions' => $conditions,
        ];
        $policy = json_encode($arr);
        $base64Policy = base64_encode($policy);
        $stringToSign = $base64Policy;
        $signature = base64_encode(hash_hmac('sha1', $stringToSign, $this->accessKeySecret, true));

        $response = [];
        $response['accessid'] = $this->accessKeyId;
        $response['host'] = $this->normalizeHost();
        $response['policy'] = $base64Policy;
        $response['signature'] = $signature;
        $response['expire'] = $end;
        $response['callback'] = $base64CallbackBody;
        $response['callback-var'] = $callbackVar;
        $response['dir'] = $prefix;  // 这个参数是设置用户上传文件时指定的前缀。

        return json_encode($response);
    }

    /**
     * Aliyun gmt Fix.
     *
     * @return string
     */
    public function gmt_iso8601($time)
    {
        // fix bug https://connect.console.aliyun.com/connect/detail/162632
        return (new \DateTime('now', new \DateTimeZone('UTC')))->setTimestamp($time)->format('Y-m-d\TH:i:s\Z');
    }
}
