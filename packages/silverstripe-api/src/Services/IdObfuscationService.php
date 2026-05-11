<?php

declare(strict_types=1);

namespace App\Api\Services;

use App\Api\Models\ApiPublicId;
use Ramsey\Uuid\Uuid;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\ORM\DataObject;
use RuntimeException;

class IdObfuscationService
{
    use Configurable;

    private static bool $enabled = false;
    private static string $uuid_type = 'v4';

    /**
     * @return int|string
     */
    public function encodeForObject(DataObject $object): int|string
    {
        return $this->encode($object::class, (int)$object->ID);
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

        /** @var ApiPublicId|null $mapping */
        $mapping = ApiPublicId::get()
            ->filter('ObjectClass', $className)
            ->filter('ObjectID', $id)
            ->first();

        if ($mapping) {
            return (string)$mapping->PublicID;
        }

        $mapping = ApiPublicId::create();
        $mapping->ObjectClass = $className;
        $mapping->ObjectID = $id;
        $mapping->PublicID = $this->generateUniquePublicId();
        $mapping->write();

        return (string)$mapping->PublicID;
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

        /** @var ApiPublicId|null $mapping */
        $mapping = ApiPublicId::get()
            ->filter('ObjectClass', $className)
            ->filter('PublicID', $publicId)
            ->first();

        if (!$mapping) {
            return null;
        }

        return (int)$mapping->ObjectID;
    }

    public function isEnabled(): bool
    {
        return (bool)self::config()->get('enabled');
    }

    private function generateUniquePublicId(): string
    {
        for ($i = 0; $i < 10; $i++) {
            $candidate = $this->generateUuid();
            if (!ApiPublicId::get()->filter('PublicID', $candidate)->exists()) {
                return $candidate;
            }
        }

        throw new RuntimeException('Failed to generate unique public ID.');
    }

    private function generateUuid(): string
    {
        return match (strtolower((string)self::config()->get('uuid_type'))) {
            'v1' => Uuid::uuid1()->toString(),
            'v6' => Uuid::uuid6()->toString(),
            'v7' => Uuid::uuid7()->toString(),
            default => Uuid::uuid4()->toString(),
        };
    }
}
