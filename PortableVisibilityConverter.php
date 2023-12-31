<?php

declare(strict_types=1);

namespace Filacare\Flysystem\Oss;

use League\Flysystem\Visibility;
use Filacare\Flysystem\Oss\Contracts\VisibilityConverter;

class PortableVisibilityConverter implements VisibilityConverter
{
    private const PUBLIC_GRANTEE_URI = 'http://acs.aliyun.com/groups/global/AllUsers';
    private const PUBLIC_GRANTS_PERMISSION = 'READ';
    private const PUBLIC_ACL = 'public-read';
    private const PRIVATE_ACL = 'private';

    public function __construct(private string $defaultForDirectories = Visibility::PUBLIC)
    {
    }

    public function visibilityToAcl(string $visibility): string
    {
        if ($visibility === Visibility::PUBLIC) {
            return self::PUBLIC_ACL;
        }

        return self::PRIVATE_ACL;
    }

    public function aclToVisibility(array $grants): string
    {
        foreach ($grants as $grant) {
            $granteeUri = $grant['Grantee']['URI'] ?? null;
            $permission = $grant['Permission'] ?? null;

            if ($granteeUri === self::PUBLIC_GRANTEE_URI && $permission === self::PUBLIC_GRANTS_PERMISSION) {
                return Visibility::PUBLIC;
            }
        }

        return Visibility::PRIVATE;
    }

    public function defaultForDirectories(): string
    {
        return $this->defaultForDirectories;
    }
}