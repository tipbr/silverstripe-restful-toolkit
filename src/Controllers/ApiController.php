<?php

declare(strict_types=1);

namespace Tipbr\RestfulToolkit\Controllers;

use SilverStripe\Control\Controller;
use SilverStripe\Control\HTTPResponse_Exception;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\Model\List\PaginatedList;
use SilverStripe\ORM\DataList;

class ApiController extends Controller
{
    private static int $max_page_size = 100;

    protected function apiResponse(array $data, int $status = 200): HTTPResponse
    {
        $response = HTTPResponse::create(json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}', $status);
        $response->addHeader('Content-Type', 'application/json');

        return $response;
    }

    protected function apiError(string $message, int $status = 400): HTTPResponse
    {
        return $this->apiResponse([
            'error' => $message,
            'status' => $status,
        ], $status);
    }

    protected function apiSuccess(string $message, array $extra = []): HTTPResponse
    {
        return $this->apiResponse(array_merge([
            'success' => true,
            'message' => $message,
        ], $extra));
    }

    /**
     * @return array{data: array<int, mixed>, meta: array{total:int,page:int,per_page:int,last_page:int}}
     */
    protected function getPaginatedList(DataList $list, int $defaultPageSize = 20): array
    {
        $request = $this->getRequest();
        $page = max(1, (int)($request->getVar('page') ?? 1));
        $maxPageSize = max(1, (int)self::config()->get('max_page_size'));
        $perPage = min($maxPageSize, max(1, (int)($request->getVar('per_page') ?? $defaultPageSize)));

        $paginated = PaginatedList::create($list, $request)
            ->setPageLength($perPage)
            ->setPageStart(($page - 1) * $perPage);

        return [
            'data' => $paginated->toArray(),
            'meta' => [
                'total' => (int)$paginated->getTotalItems(),
                'page' => $page,
                'per_page' => $perPage,
                'last_page' => (int)max(1, (int)ceil($paginated->getTotalItems() / $perPage)),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function getBodyParams(): array
    {
        $request = $this->getRequest();
        $body = trim((string)$request->getBody());

        if ($body !== '') {
            $decoded = json_decode($body, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        $postVars = $request->postVars();
        return is_array($postVars) ? $postVars : [];
    }

    /**
     * @param array<int, string> $fields
     * @param array<string, mixed> $data
     */
    protected function requireFields(array $fields, array $data): void
    {
        $missing = [];
        foreach ($fields as $field) {
            if (!array_key_exists($field, $data) || $data[$field] === null || $data[$field] === '') {
                $missing[] = $field;
            }
        }

        if ($missing !== []) {
            throw new HTTPResponse_Exception($this->apiError('Missing required fields: ' . implode(', ', $missing), 400));
        }
    }
}
