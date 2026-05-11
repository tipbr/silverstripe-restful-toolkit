<?php

declare(strict_types=1);

namespace App\Api\Models;

use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Member;

class ObjectShare extends DataObject
{
    private static string $table_name = 'ObjectShare';

    private static array $db = [
        'SharedObjectClass' => 'Varchar(255)',
        'SharedObjectID' => 'Int',
        'PermissionLevel' => "Enum('read,write', 'read')",
        'Status' => "Enum('pending,accepted,declined,revoked,expired', 'pending')",
        'InviteToken' => 'Varchar(64)',
        'InviteMessage' => 'Text',
        'DeclineReason' => 'Varchar(255)',
        'RespondedAt' => 'DBDatetime',
        'ExpiresAt' => 'DBDatetime',
    ];

    private static array $has_one = [
        'InvitedBy' => Member::class,
        'SharedWith' => Member::class,
    ];

    private static array $indexes = [
        'InviteToken' => true,
        'SharedObjectLookup' => [
            'type' => 'index',
            'columns' => ['SharedObjectClass', 'SharedObjectID'],
        ],
        'SharedWithID' => true,
        'Status' => true,
    ];

    private static array $summary_fields = [
        'ID',
        'SharedObjectClass',
        'SharedObjectID',
        'PermissionLevel',
        'Status',
        'SharedWith.Email',
        'InvitedBy.Email',
        'Created',
    ];

    public function getSharedObject(): ?DataObject
    {
        $className = (string)$this->SharedObjectClass;
        if ($className === '' || !is_a($className, DataObject::class, true)) {
            return null;
        }

        /** @var DataObject|null $record */
        $record = $className::get()->byID((int)$this->SharedObjectID);

        return $record;
    }

    public function isExpired(): bool
    {
        if (!$this->ExpiresAt) {
            return false;
        }

        return strtotime((string)$this->ExpiresAt) <= time();
    }

    public function markExpiredIfNeeded(): void
    {
        if ($this->isExpired() && in_array((string)$this->Status, ['pending', 'accepted'], true)) {
            $this->Status = 'expired';
            $this->write();
        }
    }
}
