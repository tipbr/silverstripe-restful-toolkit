<?php

declare(strict_types=1);

namespace App\Api\Traits;

use App\Api\Services\JwtService;
use SilverStripe\Control\HTTPResponse_Exception;
use SilverStripe\Core\Injector\Injector;
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

        $authHeader = (string)$request->getHeader('Authorization');
        if (!preg_match('/^Bearer\\s+(.+)$/i', $authHeader, $matches)) {
            return null;
        }

        /** @var JwtService $jwtService */
        $jwtService = Injector::inst()->get(JwtService::class);
        $claims = $jwtService->decodeAccessToken($matches[1]);

        if ($claims === null || !isset($claims->sub) || !is_numeric($claims->sub)) {
            return null;
        }

        /** @var Member|null $resolved */
        $resolved = Member::get()->byID((int)$claims->sub);
        if (!$resolved) {
            return null;
        }

        $routeParams = $request->getRouteParams();
        $routeParams['member'] = $resolved;
        $request->setRouteParams($routeParams);

        return $resolved;
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
