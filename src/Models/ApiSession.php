<?php

declare(strict_types=1);

namespace Tipbr\RestfulToolkit\Models;

use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Member;

class ApiSession extends DataObject
{
    private static string $table_name = 'ApiSession';

    private static array $db = [
        'RefreshToken' => 'Varchar(512)',
        'UserAgent' => 'Varchar(255)',
        'IPAddress' => 'Varchar(45)',
        'DeviceName' => 'Varchar(255)',
        'LastUsed' => 'DBDatetime',
        'ExpiresAt' => 'DBDatetime',
    ];

    private static array $has_one = [
        'Member' => Member::class,
    ];

    private static array $indexes = [
        'RefreshToken' => true,
        'MemberID' => true,
        'ExpiresAt' => true,
    ];

    private static array $summary_fields = [
        'ID',
        'DeviceName',
        'IPAddress',
        'LastUsed',
        'Created',
    ];
}
