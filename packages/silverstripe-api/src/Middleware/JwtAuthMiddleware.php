<?php

declare(strict_types=1);

namespace App\Api\Middleware;

use App\Api\Services\JwtService;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\Control\Middleware\HTTPMiddleware;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Security\Member;

class JwtAuthMiddleware implements HTTPMiddleware
{
    public function process(HTTPRequest $request, callable $delegate): HTTPResponse
    {
        $authHeader = (string)$request->getHeader('Authorization');
        if (!preg_match('/^Bearer\\s+(.+)$/i', $authHeader, $matches)) {
            return $this->unauthorizedResponse('Missing bearer token');
        }

        /** @var JwtService $jwtService */
        $jwtService = Injector::inst()->get(JwtService::class);
        $claims = $jwtService->decodeAccessToken($matches[1]);

        if ($claims === null || !isset($claims->sub) || !is_numeric($claims->sub)) {
            return $this->unauthorizedResponse('Invalid or expired token');
        }

        /** @var Member|null $member */
        $member = Member::get()->byID((int)$claims->sub);
        if (!$member) {
            return $this->unauthorizedResponse('Authenticated member not found');
        }

        $routeParams = $request->getRouteParams();
        $routeParams['member'] = $member;
        $routeParams['jwt_claims'] = $claims;
        $request->setRouteParams($routeParams);

        return $delegate($request);
    }

    private function unauthorizedResponse(string $message): HTTPResponse
    {
        $response = HTTPResponse::create(json_encode([
            'error' => $message,
            'status' => 401,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}', 401);
        $response->addHeader('Content-Type', 'application/json');

        return $response;
    }
}
