<?php

declare(strict_types=1);

namespace Tipbr\RestfulToolkit\Extensions;

use Ramsey\Uuid\Uuid;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Extension;

class ObfuscatableExtension extends Extension
{
    use Configurable;

    private static string $uuid_type = 'v4';

    private static array $db = [
        'PublicID' => 'Varchar(36)',
    ];

    private static array $indexes = [
        'PublicID' => [
            'type' => 'unique',
            'columns' => ['PublicID'],
        ],
    ];

    public function onBeforeWrite(): void
    {
        if (!$this->owner->PublicID) {
            $this->owner->PublicID = $this->generateUuid();
        }
    }

    private function generateUuid(): string
    {
        $type = strtolower((string)self::config()->get('uuid_type'));

        return match ($type) {
            'v1' => Uuid::uuid1()->toString(),
            'v6' => Uuid::uuid6()->toString(),
            'v7' => Uuid::uuid7()->toString(),
            'v4' => Uuid::uuid4()->toString(),
            default => throw new \RuntimeException(sprintf(
                'Unsupported UUID type "%s". Use one of: v1, v4, v6, v7.',
                $type
            )),
        };
    }
}
