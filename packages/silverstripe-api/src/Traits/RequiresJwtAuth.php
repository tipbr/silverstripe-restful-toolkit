<?php

declare(strict_types=1);

namespace App\Api\Traits;

use SilverStripe\Control\HTTPResponse_Exception;
use SilverStripe\Security\Member;

trait RequiresJwtAuth
{
    protected function getCurrentMember(): ?Member
    {
        $request = $this->getRequest();
        if (!$request) {
            return null;
        }

        $member = $request->param('member');

        if ($member instanceof Member) {
            return $member;
        }

        if (is_numeric($member)) {
            /** @var Member|null $found */
            $found = Member::get()->byID((int)$member);
            return $found;
        }

        return null;
    }

    protected function requireAuth(): Member
    {
        $member = $this->getCurrentMember();
        if (!$member) {
            throw new HTTPResponse_Exception($this->apiError('Unauthorized', 401));
        }

        return $member;
    }
}
