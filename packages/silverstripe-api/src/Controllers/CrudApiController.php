<?php

declare(strict_types=1);

namespace App\Api\Controllers;

use App\Api\Services\IdObfuscationService;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\ORM\DataList;
use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Member;

class CrudApiController extends ApiController
{
    private static array $allowed_actions = ['handle'];

    /**
     * @var array<string, class-string<DataObject>>
     */
    private static array $resources = [];

    private static string $default_resource_namespace = 'App\\Model\\';
    private IdObfuscationService $idObfuscation;

    protected function init(): void
    {
        parent::init();
        $this->idObfuscation = Injector::inst()->get(IdObfuscationService::class);
    }

    public function handle(HTTPRequest $request): HTTPResponse
    {
        $className = $this->resolveDataObjectClass($request);
        if ($className === null) {
            return $this->apiError('Unknown resource', 404);
        }

        $id = $request->param('ID');
        $resolvedId = null;
        if ($id !== null && $id !== '') {
            $resolvedId = $this->idObfuscation->decode($className, (string)$id);
            if ($resolvedId === null) {
                return $this->apiError('Invalid resource ID', 400);
            }
        }
        $method = strtoupper($request->httpMethod());

        return match ($method) {
            'GET' => $resolvedId !== null ? $this->show($className, $resolvedId) : $this->index($className),
            'POST' => $this->create($className),
            'PUT', 'PATCH' => $resolvedId !== null ? $this->update($className, $resolvedId) : $this->apiError('Resource ID is required', 400),
            'DELETE' => $resolvedId !== null ? $this->destroy($className, $resolvedId) : $this->apiError('Resource ID is required', 400),
            default => $this->apiError('Method not allowed', 405),
        };
    }

    protected function index(string $className): HTTPResponse
    {
        /** @var DataList<DataObject> $list */
        $list = $className::get();

        $paginated = $this->getPaginatedList($list);
        $paginated['data'] = array_map(
            fn (DataObject $record): array => $this->serializeRecord($record),
            $paginated['data']
        );

        return $this->apiResponse($paginated);
    }

    protected function show(string $className, int $id): HTTPResponse
    {
        /** @var DataObject|null $record */
        $record = $className::get()->byID($id);
        if (!$record) {
            return $this->apiError('Record not found', 404);
        }

        if (!$record->canView($this->getRequestMember())) {
            return $this->apiError('Forbidden', 403);
        }

        return $this->apiResponse(['data' => $this->serializeRecord($record)]);
    }

    protected function create(string $className): HTTPResponse
    {
        /** @var DataObject $record */
        $record = $className::create();

        if (!$record->canCreate($this->getRequestMember())) {
            return $this->apiError('Forbidden', 403);
        }

        $record->update($this->extractWriteableFields($className, $this->getBodyParams()));
        $record->write();

        return $this->apiResponse([
            'message' => 'Created',
            'data' => $this->serializeRecord($record),
        ], 201);
    }

    protected function update(string $className, int $id): HTTPResponse
    {
        /** @var DataObject|null $record */
        $record = $className::get()->byID($id);
        if (!$record) {
            return $this->apiError('Record not found', 404);
        }

        if (!$record->canEdit($this->getRequestMember())) {
            return $this->apiError('Forbidden', 403);
        }

        $record->update($this->extractWriteableFields($className, $this->getBodyParams()));
        $record->write();

        return $this->apiResponse([
            'message' => 'Updated',
            'data' => $this->serializeRecord($record),
        ]);
    }

    protected function destroy(string $className, int $id): HTTPResponse
    {
        /** @var DataObject|null $record */
        $record = $className::get()->byID($id);
        if (!$record) {
            return $this->apiError('Record not found', 404);
        }

        if (!$record->canDelete($this->getRequestMember())) {
            return $this->apiError('Forbidden', 403);
        }

        $record->delete();

        return $this->apiSuccess('Deleted');
    }

    /**
     * @return class-string<DataObject>|null
     */
    protected function resolveDataObjectClass(HTTPRequest $request): ?string
    {
        $resource = strtolower((string)$request->param('Resource'));
        if ($resource === '') {
            return null;
        }

        /** @var array<string, class-string<DataObject>> $resourceMap */
        $resourceMap = Config::inst()->get(self::class, 'resources') ?? [];

        if (isset($resourceMap[$resource]) && is_a($resourceMap[$resource], DataObject::class, true)) {
            return $resourceMap[$resource];
        }

        $namespace = (string)Config::inst()->get(self::class, 'default_resource_namespace');
        $candidate = $namespace . str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', $resource)));

        return is_a($candidate, DataObject::class, true) ? $candidate : null;
    }

    /**
     * @return array<string, mixed>
     */
    protected function serializeRecord(DataObject $record): array
    {
        $fields = $this->getApiFields($record::class);
        $payload = ['ID' => $this->idObfuscation->encodeForObject($record)];

        foreach ($fields as $field) {
            if ($record->hasField($field)) {
                $payload[$field] = $record->getField($field);
            }
        }

        return $payload;
    }

    /**
     * @param class-string<DataObject> $className
     * @return array<int, string>
     */
    protected function getApiFields(string $className): array
    {
        $configured = Config::inst()->get($className, 'api_fields');
        if (is_array($configured) && $configured !== []) {
            return array_values(array_filter($configured, 'is_string'));
        }

        /** @var DataObject $singleton */
        $singleton = singleton($className);
        return array_keys($singleton->config()->get('db') ?? []);
    }

    /**
     * Fields that may be written via POST / PUT / PATCH.
     * Falls back to {@see getApiFields} when `api_write_fields` is not configured.
     *
     * @param class-string<DataObject> $className
     * @return array<int, string>
     */
    protected function getApiWriteFields(string $className): array
    {
        $configured = Config::inst()->get($className, 'api_write_fields');
        if (is_array($configured) && $configured !== []) {
            return array_values(array_filter($configured, 'is_string'));
        }

        return $this->getApiFields($className);
    }

    /**
     * @param class-string<DataObject> $className
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    protected function extractWriteableFields(string $className, array $data): array
    {
        $allowed = array_flip($this->getApiWriteFields($className));
        $write = [];

        foreach ($data as $key => $value) {
            if (isset($allowed[$key])) {
                $write[$key] = $value;
            }
        }

        return $write;
    }

    protected function getRequestMember(): ?Member
    {
        $member = $this->getRequest()->param('member');

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
}
