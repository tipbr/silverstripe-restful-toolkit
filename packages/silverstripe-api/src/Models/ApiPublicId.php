<?php

declare(strict_types=1);

namespace App\Api\Models;

use SilverStripe\ORM\DataObject;

class ApiPublicId extends DataObject
{
    private static string $table_name = 'ApiPublicId';

    private static array $db = [
        'PublicID' => 'Varchar(36)',
        'ObjectClass' => 'Varchar(255)',
        'ObjectID' => 'Int',
    ];

    private static array $indexes = [
        'PublicID' => [
            'type' => 'unique',
            'columns' => ['PublicID'],
        ],
        'ObjectLookup' => [
            'type' => 'unique',
            'columns' => ['ObjectClass', 'ObjectID'],
        ],
    ];
}
