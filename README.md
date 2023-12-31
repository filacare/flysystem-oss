## Introduction
Flysystem for Aliyun oss, support Laravel 9/10  
- CDN or custom domain name
- Bucket switch (at the same endpoint)
- Object ACL
- Custom Aliyun options
- OSS directly and verify
- Compatible with Laravel 
- Client side encryption

## Require
- PHP 8.0.2+

## Install
```bash
composer require filacare/flysystem-oss
```

## Basic Usage  
```php
use League\Flysystem\Filesystem;
use Filacare\Flysystem\Oss\OssAdapter;

$config = [
    'accessKeyId'     => 'yourAccessKeyId',      // required
    'accessKeySecret' => 'yourAccessKeySecret',  // required
    'endpoint'        => 'yourEndpoint',         // required
    'isCName'         => false,
    'securityToken'   => null,
    'bucket'          => 'bucketName',           // required
    'root'            => '',  // Global directory prefix
    'cdnUrl'          => '',  // https://yourdomain.com if use cdn
    'options'         => [],  // Custom oss request header options
    'visibility'      => 'public',
    'encryptionKey'   => ''  // Must be 32 characters or ''
];
$adapter = new OssAdapter($config);
$driver = new Filesystem($adapter);

// use dirver
$driver->writeStream(...);
// use adapter
$adapter->getUrl($path);   // get web visit url
$adapter->bucket($bucket); // switch bucket in same endpoint
$adapter->cdnUrl($cdnUrl); // If you use cdn, don't forget to switch the cdn
// use client
$adapter->getClient()->uploadStream(...);
```
For details, please check:  
https://flysystem.thephpleague.com/docs/usage/filesystem-api/  
https://help.aliyun.com/zh/oss/developer-reference/getting-started-1

## Laravel  
1. Add in the disks of the config/filesystems.php configuration file
```php
'oss' => [
    'driver'          => 'oss',
    'accessKeyId'     => env('OSS_ACCESS_KEY', ''),  // required
    'accessKeySecret' => env('OSS_SECRET_KEY', ''),  // required
    'endpoint'        => env('OSS_ENDPOINT', ''),    // required
    'isCName'         => env('OSS_IS_CNAME', false),
    'securityToken'   => null,
    'bucket'          => env('OSS_BUCKET', ''),      // required
    'root'            => env('OSS_ROOT', ''),  // Global directory prefix 
    'cdnUrl'          => '',  // https://yourdomain.com if use cdn
    'options'         => [],  // Custom oss request header options
    'visibility'      => env('OSS_VISIBILITY', 'public'),
    'encryptionKey'   => '' // Must be 32 characters or ''
],
```
2. Usage in Laravel File
```php
use Illuminate\Support\Facades\Storage;
Storage::disk('oss')->put($path, $contents, $options = []);
```
For details, please check https://laravel.com/docs/10.x/filesystem  

## Security Setting (Optional)  
> Default object visibility acl is public(Equivalent to public_read), a default visibility setting can be added, this works without passing any visibility options.
```php
'visibility' => 'private' // Optional, default visibility acl
```
or higher priority:  
```php
$driver->writeStream(string $path, $contents, ['visibility' => 'private']);
Storage::disk('oss')->put($path, $contents, 'private');
```
Override option, the above settings will be invalid.
```php
'options' => [
    'x-oss-object-acl' => 'private' // public or private
]
```

## SpatieMediaLibrary
If the default policy is private and you want SpatieMediaLibrary to default to public_read, please add it to remote.extra_headers in the config/media-library.php file.
```bash
php artisan vendor:publish --provider="Spatie\MediaLibrary\MediaLibraryServiceProvider" --tag="config"
```
```php
'visibility' => 'public',
```

## Client side encryption
Libsodium Stream encryption. Implemented using generators, high performance.  
Upload automatic encryption, download automatic decryption.  
> EncryptionKey and chunkSize cannot be changed after use, otherwise they cannot be decrypted. Please decrypt the storage before modifying or keep this option in mind.  
```php
'encryptionKey'   => '', // Must be 32 characters or ''
'chunkSize'       => '', // Default is 4096
```

## Fork
Refactoring based on https://github.com/iiDestiny/flysystem-oss, thanks to the author for his contribution.
