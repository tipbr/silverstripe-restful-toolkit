<?php

declare(strict_types=1);

namespace App\Api\Extensions;

use App\Api\Services\SharingService;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Extension;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Member;

class ShareableObjectExtension extends Extension
{
    private static string $share_owner_field = 'OwnerID';

    public function canManageShares(?Member $member = null): bool
    {
        if (!$member || !$this->owner instanceof DataObject) {
            return false;
        }

        if ($member->inGroup('administrators')) {
            return true;
        }

        $owner = $this->getShareOwner();
        if ($owner && (int)$owner->ID === (int)$member->ID) {
            return true;
        }

        return $this->owner->canEdit($member);
    }

    public function getShareOwner(): ?Member
    {
        if (!$this->owner instanceof DataObject) {
            return null;
        }

        $ownerField = (string)Config::inst()->get($this->owner::class, 'share_owner_field');
        if ($ownerField === '') {
            $ownerField = 'OwnerID';
        }

        if (!$this->owner->hasField($ownerField)) {
            return null;
        }

        $ownerId = (int)$this->owner->getField($ownerField);
        if ($ownerId <= 0) {
            return null;
        }

        /** @var Member|null $owner */
        $owner = Member::get()->byID($ownerId);

        return $owner;
    }

    public function updateCanView(?bool &$result, ?Member $member = null, mixed ...$args): void
    {
        if ($result !== null || !$member || !$this->owner instanceof DataObject) {
            return;
        }

        /** @var SharingService $sharingService */
        $sharingService = Injector::inst()->get(SharingService::class);
        if ($sharingService->memberHasPermission($this->owner, $member, 'read')) {
            $result = true;
        }
    }

    public function updateCanEdit(?bool &$result, ?Member $member = null, mixed ...$args): void
    {
        if ($result !== null || !$member || !$this->owner instanceof DataObject) {
            return;
        }

        /** @var SharingService $sharingService */
        $sharingService = Injector::inst()->get(SharingService::class);
        if ($sharingService->memberHasPermission($this->owner, $member, 'write')) {
            $result = true;
        }
    }
}
