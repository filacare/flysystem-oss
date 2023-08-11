## Introduction
Flysystem for Aliyun oss, support Laravel 9/10

Some features:
- **CDN** or custom domain name
- Bucket switch (at the same endpoint)
- Object ACL
- Custom Aliyun options
- OSS directly and verify
- Compatible with **Laravel**

## Install
```bash
composer require filacare/flysystem-oss
```

## Basic Usage
```php
use League\Flysystem\Filesystem;
use Filacare\Flysystem\Oss\OssAdapter;


$config = [
    'accessKeyId' => 'yourAccessKeyId',
    'accessKeySecret' => 'yourAccessKeySecret',
    'endpoint' => 'yourEndpoint',
    'isCName' => false,
    'securityToken' => null,
    'bucket' => 'bucketName',
    'root' => '', // Global directory prefix
    'cdnUrl' => 'https://yourdomain.com', // need setting oss cdn/domain
    'options' => [] // Custom oss request header options
];
$adapter = new OssAdapter($config);
$driver = new Filesystem($ossAdapter);

// use dirver
$driver->writeStream(...);
// use adapter
$adapter->writeStream(...);
$adapter->getUrl($path);
$adapter->bucket($bucket); // switch bucket in same endpoint
// use client
$adapter->getClient()->uploadStream(...);
```
For details, please check:  
https://flysystem.thephpleague.com/docs/usage/filesystem-api/  
https://help.aliyun.com/zh/oss/developer-reference/getting-started-1

## Laravel
1. Add in the disks of the config/filesystems.php configuration file
```php
'oss_or_other_name' => [
    'driver'      => 'oss',
    'accessKeyId' => 'yourAccessKeyId',
    'accessKeySecret' => 'yourAccessKeySecret',
    'endpoint' => 'yourEndpoint',
    'isCName' => false,
    'securityToken' => null,
    'bucket' => 'bucketName',
    'root' => '', // Global directory prefix
    'cdnUrl' => 'https://yourdomain.com', // need setting oss cdn/domain
    'options' => [] // Custom oss request header options
],
```
2. Usage in Laravel File
```php
use Illuminate\Support\Facades\Storage;
Storage::disk('oss_or_other_name')->put($path, $contents, $options = []);
```
For details, please check https://laravel.com/docs/10.x/filesystem

Refactoring based on https://github.com/iiDestiny/flysystem-oss, thanks to the author for his contribution.

