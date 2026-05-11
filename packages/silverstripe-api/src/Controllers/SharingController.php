<?php

declare(strict_types=1);

namespace App\Api\Controllers;

use App\Api\Extensions\ShareableObjectExtension;
use App\Api\Models\ObjectShare;
use App\Api\Services\SharingService;
use App\Api\Traits\RequiresJwtAuth;
use RuntimeException;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Member;

class SharingController extends ApiController
{
    use RequiresJwtAuth;

    private static array $allowed_actions = [
        'invite',
        'accept',
        'decline',
        'revoke',
        'mine',
        'objectShares',
    ];

    private SharingService $sharingService;

    protected function init(): void
    {
        parent::init();
        $this->sharingService = Injector::inst()->get(SharingService::class);
    }

    public function invite(HTTPRequest $request): HTTPResponse
    {
        if (strtoupper($request->httpMethod()) !== 'POST') {
            return $this->apiError('Method not allowed', 405);
        }

        $member = $this->requireAuth();
        $data = $this->getBodyParams();
        $this->requireFields(['resource', 'object_id', 'invitee_email'], $data);

        $object = $this->sharingService->resolveObjectFromResource((string)$data['resource'], (int)$data['object_id']);
        if (!$object) {
            return $this->apiError('Shareable object not found', 404);
        }

        if (!$this->canManageShares($object, $member)) {
            return $this->apiError('Forbidden', 403);
        }

        /** @var Member|null $invitee */
        $invitee = Member::get()->filter('Email', strtolower(trim((string)$data['invitee_email'])))->first();
        if (!$invitee) {
            return $this->apiError('Invitee account not found', 404);
        }

        try {
            $share = $this->sharingService->createInvite(
                $object,
                $member,
                $invitee,
                isset($data['permission']) ? (string)$data['permission'] : null,
                isset($data['expires_in_seconds']) ? (int)$data['expires_in_seconds'] : null,
                isset($data['message']) ? (string)$data['message'] : null,
            );
        } catch (RuntimeException $exception) {
            return $this->apiError($exception->getMessage(), 400);
        }

        return $this->apiResponse([
            'message' => 'Share invitation sent',
            'data' => $this->sharingService->serializeShare($share),
        ], 201);
    }

    public function accept(HTTPRequest $request): HTTPResponse
    {
        if (strtoupper($request->httpMethod()) !== 'POST') {
            return $this->apiError('Method not allowed', 405);
        }

        $member = $this->requireAuth();
        $share = $this->findShareByRequest($request);
        if (!$share) {
            return $this->apiError('Share invitation not found', 404);
        }

        try {
            $updated = $this->sharingService->acceptInvite($share, $member);
        } catch (RuntimeException $exception) {
            return $this->apiError($exception->getMessage(), 400);
        }

        return $this->apiResponse([
            'message' => 'Share invitation accepted',
            'data' => $this->sharingService->serializeShare($updated),
        ]);
    }

    public function decline(HTTPRequest $request): HTTPResponse
    {
        if (strtoupper($request->httpMethod()) !== 'POST') {
            return $this->apiError('Method not allowed', 405);
        }

        $member = $this->requireAuth();
        $share = $this->findShareByRequest($request);
        if (!$share) {
            return $this->apiError('Share invitation not found', 404);
        }

        $data = $this->getBodyParams();

        try {
            $updated = $this->sharingService->declineInvite(
                $share,
                $member,
                isset($data['reason']) ? (string)$data['reason'] : null,
            );
        } catch (RuntimeException $exception) {
            return $this->apiError($exception->getMessage(), 400);
        }

        return $this->apiResponse([
            'message' => 'Share invitation declined',
            'data' => $this->sharingService->serializeShare($updated),
        ]);
    }

    public function revoke(HTTPRequest $request): HTTPResponse
    {
        if (strtoupper($request->httpMethod()) !== 'DELETE') {
            return $this->apiError('Method not allowed', 405);
        }

        $member = $this->requireAuth();
        $share = $this->findShareByRequest($request);
        if (!$share) {
            return $this->apiError('Share invitation not found', 404);
        }

        $object = $share->getSharedObject();
        if (!$object || !$this->canManageShares($object, $member)) {
            return $this->apiError('Forbidden', 403);
        }

        try {
            $updated = $this->sharingService->revokeInvite($share);
        } catch (RuntimeException $exception) {
            return $this->apiError($exception->getMessage(), 400);
        }

        return $this->apiResponse([
            'message' => 'Share invitation revoked',
            'data' => $this->sharingService->serializeShare($updated),
        ]);
    }

    public function mine(HTTPRequest $request): HTTPResponse
    {
        if (strtoupper($request->httpMethod()) !== 'GET') {
            return $this->apiError('Method not allowed', 405);
        }

        $member = $this->requireAuth();

        return $this->apiResponse([
            'data' => $this->sharingService->listSharesForMember($member),
        ]);
    }

    public function objectShares(HTTPRequest $request): HTTPResponse
    {
        if (strtoupper($request->httpMethod()) !== 'GET') {
            return $this->apiError('Method not allowed', 405);
        }

        $member = $this->requireAuth();
        $resource = (string)$request->getVar('resource');
        $objectId = (int)$request->getVar('object_id');

        if ($resource === '' || $objectId <= 0) {
            return $this->apiError('resource and object_id are required query params', 400);
        }

        $object = $this->sharingService->resolveObjectFromResource($resource, $objectId);
        if (!$object) {
            return $this->apiError('Shareable object not found', 404);
        }

        if (!$this->canManageShares($object, $member)) {
            return $this->apiError('Forbidden', 403);
        }

        return $this->apiResponse([
            'data' => $this->sharingService->listSharesForObject($object),
        ]);
    }

    private function canManageShares(DataObject $object, Member $member): bool
    {
        if (!$object->hasExtension(ShareableObjectExtension::class)) {
            return false;
        }

        if (!$object->hasMethod('canManageShares')) {
            return false;
        }

        return (bool)$object->canManageShares($member);
    }

    private function findShareByRequest(HTTPRequest $request): ?ObjectShare
    {
        $id = (int)$request->param('ID');
        if ($id <= 0) {
            return null;
        }

        /** @var ObjectShare|null $share */
        $share = ObjectShare::get()->byID($id);

        return $share;
    }
}
