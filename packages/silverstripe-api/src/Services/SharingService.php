<?php

declare(strict_types=1);

namespace App\Api\Services;

use App\Api\Extensions\ShareableObjectExtension;
use App\Api\Models\ObjectShare;
use RuntimeException;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\ORM\DB;
use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Member;

class SharingService
{
    use Configurable;

    private static int $default_share_expiry = 604800;
    private static bool $block_reinvite_after_decline = true;
    private static bool $allow_self_invite = false;

    /**
     * @var array<int, string>
     */
    private static array $allowed_permissions = ['read', 'write'];

    private static string $default_permission = 'read';

    /**
     * @var array<string, class-string<DataObject>>
     */
    private static array $resources = [];

    private static string $default_resource_namespace = 'App\\Model\\';
    private ?IdObfuscationService $idObfuscation = null;

    public function resolveClassFromResource(string $resource): ?string
    {
        $normalized = strtolower(trim($resource));
        if ($normalized === '') {
            return null;
        }

        /** @var array<string, class-string<DataObject>> $resourceMap */
        $resourceMap = self::config()->get('resources') ?? [];

        if (isset($resourceMap[$normalized]) && is_a($resourceMap[$normalized], DataObject::class, true)) {
            return $resourceMap[$normalized];
        }

        $namespace = (string)self::config()->get('default_resource_namespace');
        $candidate = $namespace . str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', $normalized)));

        return is_a($candidate, DataObject::class, true) ? $candidate : null;
    }

    public function resolveObjectFromResource(string $resource, int $objectId): ?DataObject
    {
        $className = $this->resolveClassFromResource($resource);
        if (!$className || $objectId <= 0) {
            return null;
        }

        /** @var DataObject|null $record */
        $record = $className::get()->byID($objectId);

        return $record;
    }

    public function createInvite(
        DataObject $object,
        Member $invitedBy,
        Member $sharedWith,
        ?string $permission = null,
        ?int $expiresInSeconds = null,
        ?string $message = null,
    ): ObjectShare {
        $this->assertShareable($object);

        if (!$this->isValidPermission($permission)) {
            throw new RuntimeException('Invalid permission level.');
        }

        if (!$this->isAllowSelfInvite() && (int)$invitedBy->ID === (int)$sharedWith->ID) {
            throw new RuntimeException('You cannot invite yourself.');
        }

        if ($this->isBlockReinviteAfterDecline() && $this->wasDeclinedPreviously($object, $sharedWith)) {
            throw new RuntimeException('This member has previously declined this share invitation.');
        }

        $existing = $this->findCurrentShare($object, $sharedWith);
        if ($existing && in_array((string)$existing->Status, ['pending', 'accepted'], true)) {
            throw new RuntimeException('An active share already exists for this member.');
        }

        $share = ObjectShare::create();
        $share->SharedObjectClass = $object::class;
        $share->SharedObjectID = (int)$object->ID;
        $share->InvitedByID = (int)$invitedBy->ID;
        $share->SharedWithID = (int)$sharedWith->ID;
        $share->PermissionLevel = $permission ?: $this->getDefaultPermission();
        $share->Status = 'pending';
        $share->InviteToken = $this->generateInviteToken();
        $share->InviteMessage = trim((string)$message);
        $share->ExpiresAt = date('Y-m-d H:i:s', time() + $this->resolveExpiry($expiresInSeconds));
        $share->write();

        return $share;
    }

    public function acceptInvite(ObjectShare $share, Member $member): ObjectShare
    {
        $this->assertRecipient($share, $member);

        $share->markExpiredIfNeeded();
        if ((string)$share->Status !== 'pending') {
            throw new RuntimeException('Share invitation is no longer pending.');
        }

        $share->Status = 'accepted';
        $share->RespondedAt = DB::get_conn()->now();
        $share->write();

        return $share;
    }

    public function declineInvite(ObjectShare $share, Member $member, ?string $reason = null): ObjectShare
    {
        $this->assertRecipient($share, $member);

        $share->markExpiredIfNeeded();
        if ((string)$share->Status !== 'pending') {
            throw new RuntimeException('Share invitation is no longer pending.');
        }

        $share->Status = 'declined';
        $share->DeclineReason = mb_substr(trim((string)$reason), 0, 255);
        $share->RespondedAt = DB::get_conn()->now();
        $share->write();

        return $share;
    }

    public function revokeInvite(ObjectShare $share): ObjectShare
    {
        if (!in_array((string)$share->Status, ['pending', 'accepted'], true)) {
            throw new RuntimeException('Only pending or accepted shares can be revoked.');
        }

        $share->Status = 'revoked';
        $share->RespondedAt = DB::get_conn()->now();
        $share->write();

        return $share;
    }

    public function memberHasPermission(DataObject $object, Member $member, string $requiredPermission): bool
    {
        $this->assertShareable($object);

        if ((int)$member->ID <= 0) {
            return false;
        }

        /** @var ObjectShare|null $share */
        $share = ObjectShare::get()
            ->filter('SharedObjectClass', $object::class)
            ->filter('SharedObjectID', (int)$object->ID)
            ->filter('SharedWithID', (int)$member->ID)
            ->filter('Status', 'accepted')
            ->sort('Created', 'DESC')
            ->first();

        if (!$share) {
            return false;
        }

        $share->markExpiredIfNeeded();
        if ((string)$share->Status !== 'accepted') {
            return false;
        }

        $permission = (string)$share->PermissionLevel;

        if ($requiredPermission === 'read') {
            return in_array($permission, ['read', 'write'], true);
        }

        return $requiredPermission === 'write' && $permission === 'write';
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listSharesForObject(DataObject $object): array
    {
        $this->assertShareable($object);

        $shares = [];
        foreach (ObjectShare::get()
            ->filter('SharedObjectClass', $object::class)
            ->filter('SharedObjectID', (int)$object->ID)
            ->sort('Created', 'DESC') as $share
        ) {
            $share->markExpiredIfNeeded();
            $shares[] = $this->serializeShare($share);
        }

        return $shares;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listSharesForMember(Member $member): array
    {
        $shares = [];

        foreach (ObjectShare::get()->filter('SharedWithID', (int)$member->ID)->sort('Created', 'DESC') as $share) {
            $share->markExpiredIfNeeded();
            $shares[] = $this->serializeShare($share);
        }

        return $shares;
    }

    /**
     * @return array<string, mixed>
     */
    public function serializeShare(ObjectShare $share): array
    {
        $idObfuscation = $this->getIdObfuscation();

        return [
            'id' => $idObfuscation->encode(ObjectShare::class, (int)$share->ID),
            'shared_object_class' => (string)$share->SharedObjectClass,
            'shared_object_id' => $idObfuscation->encode((string)$share->SharedObjectClass, (int)$share->SharedObjectID),
            'permission' => (string)$share->PermissionLevel,
            'status' => (string)$share->Status,
            'invited_by_id' => $idObfuscation->encode(Member::class, (int)$share->InvitedByID),
            'shared_with_id' => $idObfuscation->encode(Member::class, (int)$share->SharedWithID),
            'invite_message' => (string)$share->InviteMessage,
            'decline_reason' => (string)$share->DeclineReason,
            'expires_at' => (string)$share->ExpiresAt,
            'responded_at' => (string)$share->RespondedAt,
            'created' => (string)$share->Created,
        ];
    }

    private function getIdObfuscation(): IdObfuscationService
    {
        if ($this->idObfuscation === null) {
            $this->idObfuscation = Injector::inst()->get(IdObfuscationService::class);
        }

        return $this->idObfuscation;
    }

    private function assertShareable(DataObject $object): void
    {
        if (!$object->hasExtension(ShareableObjectExtension::class)) {
            throw new RuntimeException(sprintf(
                '%s is not shareable. Apply %s to this DataObject class.',
                $object::class,
                ShareableObjectExtension::class
            ));
        }
    }

    private function assertRecipient(ObjectShare $share, Member $member): void
    {
        if ((int)$share->SharedWithID !== (int)$member->ID) {
            throw new RuntimeException('You are not allowed to respond to this invitation.');
        }
    }

    private function isValidPermission(?string $permission): bool
    {
        $value = $permission ?: $this->getDefaultPermission();
        $allowed = self::config()->get('allowed_permissions') ?? [];

        return in_array($value, $allowed, true);
    }

    private function getDefaultPermission(): string
    {
        return (string)self::config()->get('default_permission');
    }

    private function resolveExpiry(?int $expiresInSeconds): int
    {
        $default = max(60, (int)self::config()->get('default_share_expiry'));
        if ($expiresInSeconds === null) {
            return $default;
        }

        return max(60, $expiresInSeconds);
    }

    private function isBlockReinviteAfterDecline(): bool
    {
        return (bool)self::config()->get('block_reinvite_after_decline');
    }

    private function isAllowSelfInvite(): bool
    {
        return (bool)self::config()->get('allow_self_invite');
    }

    private function wasDeclinedPreviously(DataObject $object, Member $sharedWith): bool
    {
        return ObjectShare::get()
            ->filter('SharedObjectClass', $object::class)
            ->filter('SharedObjectID', (int)$object->ID)
            ->filter('SharedWithID', (int)$sharedWith->ID)
            ->filter('Status', 'declined')
            ->exists();
    }

    private function findCurrentShare(DataObject $object, Member $sharedWith): ?ObjectShare
    {
        /** @var ObjectShare|null $share */
        $share = ObjectShare::get()
            ->filter('SharedObjectClass', $object::class)
            ->filter('SharedObjectID', (int)$object->ID)
            ->filter('SharedWithID', (int)$sharedWith->ID)
            ->sort('Created', 'DESC')
            ->first();

        if ($share) {
            $share->markExpiredIfNeeded();
        }

        return $share;
    }

    private function generateInviteToken(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    }
}
