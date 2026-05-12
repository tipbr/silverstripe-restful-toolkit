<?php

declare(strict_types=1);

namespace Tipbr\RestfulToolkit\Services;

use Tipbr\RestfulToolkit\Extensions\ObfuscatableExtension;
use Ramsey\Uuid\Uuid;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\ORM\DataObject;

class IdObfuscationService
{
    use Configurable;

    private static bool $enabled = false;

    /**
     * @return int|string
     */
    public function encodeForObject(DataObject $object): int|string
    {
        if (!$this->isEnabled() || !$object->hasExtension(ObfuscatableExtension::class)) {
            return (int)$object->ID;
        }

        return (string)$object->PublicID;
    }

    /**
     * @param class-string<DataObject> $className
     * @return int|string
     */
    public function encode(string $className, int $id): int|string
    {
        if (!$this->isEnabled() || $id <= 0) {
            return $id;
        }

        /** @var DataObject|null $record */
        $record = $className::get()->byID($id);
        if (!$record || !$record->hasExtension(ObfuscatableExtension::class)) {
            return $id;
        }

        return (string)$record->PublicID;
    }

    /**
     * @param class-string<DataObject> $className
     */
    public function decode(string $className, mixed $value): ?int
    {
        if (!$this->isEnabled()) {
            if (is_int($value)) {
                return $value > 0 ? $value : null;
            }

            if (is_numeric($value)) {
                $id = (int)$value;
                return $id > 0 ? $id : null;
            }

            return null;
        }

        if (!is_string($value) && !is_numeric($value)) {
            return null;
        }

        $publicId = trim((string)$value);
        if ($publicId === '' || !Uuid::isValid($publicId)) {
            return null;
        }

        /** @var DataObject|null $record */
        $record = $className::get()->filter('PublicID', $publicId)->first();
        if (!$record) {
            return null;
        }

        return (int)$record->ID;
    }

    public function isEnabled(): bool
    {
        return (bool)self::config()->get('enabled');
    }
}
