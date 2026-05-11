<?php

declare(strict_types=1);

namespace App\Api\Middleware;

use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\Control\Middleware\HTTPMiddleware;

class CorsMiddleware implements HTTPMiddleware
{
    /**
     * @var array<int, string>
     */
    private static array $allowed_origins = [];

    public function process(HTTPRequest $request, callable $delegate): HTTPResponse
    {
        if (strtoupper($request->httpMethod()) === 'OPTIONS') {
            $response = HTTPResponse::create('', 204);
            return $this->applyCorsHeaders($request, $response);
        }

        $response = $delegate($request);
        return $this->applyCorsHeaders($request, $response);
    }

    private function applyCorsHeaders(HTTPRequest $request, HTTPResponse $response): HTTPResponse
    {
        $origin = (string)$request->getHeader('Origin');
        $allowedOrigins = self::config()->get('allowed_origins') ?? [];

        if (in_array('*', $allowedOrigins, true)) {
            $response->addHeader('Access-Control-Allow-Origin', '*');
        } elseif ($origin !== '' && in_array($origin, $allowedOrigins, true)) {
            $response->addHeader('Access-Control-Allow-Origin', $origin);
            $response->addHeader('Vary', 'Origin');
            $response->addHeader('Access-Control-Allow-Credentials', 'true');
        }

        $response->addHeader('Access-Control-Allow-Headers', 'Authorization, Content-Type, Accept');
        $response->addHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS');

        return $response;
    }
}
