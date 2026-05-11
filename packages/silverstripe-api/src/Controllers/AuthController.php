<?php

declare(strict_types=1);

namespace App\Api\Controllers;

use App\Api\Models\ApiSession;
use App\Api\Services\IdObfuscationService;
use App\Api\Services\JwtService;
use App\Api\Traits\RequiresJwtAuth;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\ORM\DB;
use SilverStripe\ORM\ValidationResult;
use SilverStripe\Security\Member;
use SilverStripe\Security\PasswordValidator;

class AuthController extends ApiController
{
    use RequiresJwtAuth;

    private static int $min_password_length = 8;

    private static array $allowed_actions = [
        'register',
        'login',
        'checkEmail',
        'checkPassword',
        'refresh',
        'logout',
        'logoutAll',
        'sessions',
        'sessionById',
        'forgotPassword',
        'resetPassword',
        'changePassword',
        'me',
    ];

    private JwtService $jwtService;
    private IdObfuscationService $idObfuscation;

    protected function init(): void
    {
        parent::init();
        $this->jwtService = Injector::inst()->get(JwtService::class);
        $this->idObfuscation = Injector::inst()->get(IdObfuscationService::class);
    }

    public function register(HTTPRequest $request): HTTPResponse
    {
        if (strtoupper($request->httpMethod()) !== 'POST') {
            return $this->apiError('Method not allowed', 405);
        }

        $data = $this->getBodyParams();
        $this->requireFields(['email', 'password', 'first_name', 'last_name'], $data);

        $email = strtolower(trim((string)$data['email']));
        if (Member::get()->filter('Email', $email)->exists()) {
            return $this->apiError('Email is already registered', 409);
        }

        $member = Member::create();
        $member->Email = $email;
        $member->FirstName = trim((string)$data['first_name']);
        $member->Surname = trim((string)$data['last_name']);
        $passwordValidation = $this->validatePasswordAgainstPolicy((string)$data['password']);
        if (!$passwordValidation->isValid()) {
            return $this->apiError($this->getPrimaryValidationMessage($passwordValidation), 400);
        }
        $member->changePassword((string)$data['password']);
        $member->write();

        $tokens = $this->jwtService->issueTokens($member, [
            'user_agent' => (string)$request->getHeader('User-Agent'),
            'ip_address' => (string)$request->getIP(),
            'device_name' => $data['device_name'] ?? null,
        ]);
        $tokens['session_id'] = $this->idObfuscation->encode(ApiSession::class, (int)$tokens['session_id']);

        return $this->apiResponse($tokens, 201);
    }

    public function login(HTTPRequest $request): HTTPResponse
    {
        if (strtoupper($request->httpMethod()) !== 'POST') {
            return $this->apiError('Method not allowed', 405);
        }

        $data = $this->getBodyParams();
        $this->requireFields(['email', 'password'], $data);

        $email = strtolower(trim((string)$data['email']));
        /** @var Member|null $member */
        $member = Member::get()->filter('Email', $email)->first();

        if (!$member || !$member->checkPassword((string)$data['password'])->isValid()) {
            return $this->apiError('Invalid credentials', 401);
        }

        $tokens = $this->jwtService->issueTokens($member, [
            'user_agent' => (string)$request->getHeader('User-Agent'),
            'ip_address' => (string)$request->getIP(),
            'device_name' => $data['device_name'] ?? null,
        ]);
        $tokens['session_id'] = $this->idObfuscation->encode(ApiSession::class, (int)$tokens['session_id']);

        return $this->apiResponse($tokens);
    }

    public function refresh(HTTPRequest $request): HTTPResponse
    {
        if (strtoupper($request->httpMethod()) !== 'POST') {
            return $this->apiError('Method not allowed', 405);
        }

        $data = $this->getBodyParams();
        $this->requireFields(['refresh_token'], $data);

        $sessionId = null;
        if (isset($data['session_id'])) {
            $sessionId = $this->idObfuscation->decode(ApiSession::class, $data['session_id']);
            if ($sessionId === null) {
                return $this->apiError('Invalid session ID', 400);
            }
        }
        $session = $this->jwtService->validateRefreshToken((string)$data['refresh_token'], $sessionId);

        if (!$session || !$session->MemberID) {
            return $this->apiError('Invalid refresh token', 401);
        }

        $session->LastUsed = DB::get_conn()->now();
        $session->write();

        $member = $session->Member();
        if (!$member) {
            return $this->apiError('Invalid session member', 401);
        }

        $response = [
            'access_token' => $this->jwtService->generateAccessToken($member),
            'session_id' => $this->idObfuscation->encode(ApiSession::class, (int)$session->ID),
        ];

        if ($this->jwtService->shouldRotateRefreshTokens()) {
            $response['refresh_token'] = $this->jwtService->rotateRefreshToken($session);
        }

        return $this->apiResponse($response);
    }

    public function checkEmail(HTTPRequest $request): HTTPResponse
    {
        if (strtoupper($request->httpMethod()) !== 'POST') {
            return $this->apiError('Method not allowed', 405);
        }

        $data = $this->getBodyParams();
        $this->requireFields(['email'], $data);

        $email = strtolower(trim((string)$data['email']));
        $formatValid = filter_var($email, FILTER_VALIDATE_EMAIL) !== false;

        $mxValid = false;
        if ($formatValid) {
            $domain = (string)substr(strrchr($email, '@') ?: '', 1);
            if ($domain !== '') {
                $mxValid = function_exists('checkdnsrr') && checkdnsrr($domain, 'MX');
            }
        }

        return $this->apiResponse([
            'email' => $email,
            'format_valid' => $formatValid,
            'mx_valid' => $mxValid,
            'valid' => $formatValid && $mxValid,
        ]);
    }

    public function checkPassword(HTTPRequest $request): HTTPResponse
    {
        if (strtoupper($request->httpMethod()) !== 'POST') {
            return $this->apiError('Method not allowed', 405);
        }

        $data = $this->getBodyParams();
        $this->requireFields(['password'], $data);

        $password = (string)$data['password'];
        $validation = $this->validatePasswordAgainstPolicy($password);

        return $this->apiResponse([
            'valid' => $validation->isValid(),
            'errors' => $this->getValidationMessages($validation),
            'strength' => $this->getPasswordStrength($password),
        ]);
    }

    public function logout(HTTPRequest $request): HTTPResponse
    {
        if (strtoupper($request->httpMethod()) !== 'POST') {
            return $this->apiError('Method not allowed', 405);
        }

        $member = $this->requireAuth();
        $data = $this->getBodyParams();
        $this->requireFields(['session_id'], $data);

        $sessionId = $this->idObfuscation->decode(ApiSession::class, $data['session_id']);
        if ($sessionId === null) {
            return $this->apiError('Invalid session ID', 400);
        }

        /** @var ApiSession|null $session */
        $session = ApiSession::get()->byID($sessionId);
        if (!$session || (int)$session->MemberID !== (int)$member->ID) {
            return $this->apiError('Session not found', 404);
        }

        $this->jwtService->revokeSession($session);

        return $this->apiSuccess('Logged out');
    }

    public function logoutAll(HTTPRequest $request): HTTPResponse
    {
        if (strtoupper($request->httpMethod()) !== 'POST') {
            return $this->apiError('Method not allowed', 405);
        }

        $member = $this->requireAuth();
        $this->jwtService->revokeAllSessions($member);

        return $this->apiSuccess('Logged out from all devices');
    }

    public function sessions(HTTPRequest $request): HTTPResponse
    {
        if (strtoupper($request->httpMethod()) !== 'GET') {
            return $this->apiError('Method not allowed', 405);
        }

        $member = $this->requireAuth();

        $sessions = $this->jwtService->listSessions($member);
        $mapped = array_map(function (array $session): array {
            $session['id'] = $this->idObfuscation->encode(ApiSession::class, (int)$session['id']);
            return $session;
        }, $sessions);

        return $this->apiResponse([
            'data' => $mapped,
        ]);
    }

    public function sessionById(HTTPRequest $request): HTTPResponse
    {
        if (strtoupper($request->httpMethod()) !== 'DELETE') {
            return $this->apiError('Method not allowed', 405);
        }

        $member = $this->requireAuth();
        $id = $this->idObfuscation->decode(ApiSession::class, (string)$request->param('ID'));
        if ($id === null) {
            return $this->apiError('Invalid session ID', 400);
        }

        /** @var ApiSession|null $session */
        $session = ApiSession::get()->byID($id);

        if (!$session || (int)$session->MemberID !== (int)$member->ID) {
            return $this->apiError('Session not found', 404);
        }

        $this->jwtService->revokeSession($session);

        return $this->apiSuccess('Session revoked');
    }

    public function forgotPassword(HTTPRequest $request): HTTPResponse
    {
        if (strtoupper($request->httpMethod()) !== 'POST') {
            return $this->apiError('Method not allowed', 405);
        }

        $data = $this->getBodyParams();
        $this->requireFields(['email'], $data);

        $email = strtolower(trim((string)$data['email']));
        /** @var Member|null $member */
        $member = Member::get()->filter('Email', $email)->first();

        if ($member) {
            $this->jwtService->sendPasswordResetEmail($member);
        }

        return $this->apiSuccess('If the email exists, a password reset link has been sent.');
    }

    public function resetPassword(HTTPRequest $request): HTTPResponse
    {
        if (strtoupper($request->httpMethod()) !== 'POST') {
            return $this->apiError('Method not allowed', 405);
        }

        $data = $this->getBodyParams();
        $this->requireFields(['token', 'email', 'password'], $data);

        $member = $this->jwtService->validatePasswordResetToken((string)$data['token'], (string)$data['email']);
        if (!$member) {
            return $this->apiError('Invalid reset token', 400);
        }

        $passwordValidation = $this->validatePasswordAgainstPolicy((string)$data['password']);
        if (!$passwordValidation->isValid()) {
            return $this->apiError($this->getPrimaryValidationMessage($passwordValidation), 400);
        }
        $member->changePassword((string)$data['password']);
        $member->write();

        $this->jwtService->revokeAllSessions($member);

        return $this->apiSuccess('Password reset successful');
    }

    public function changePassword(HTTPRequest $request): HTTPResponse
    {
        if (strtoupper($request->httpMethod()) !== 'POST') {
            return $this->apiError('Method not allowed', 405);
        }

        $member = $this->requireAuth();
        $data = $this->getBodyParams();
        $this->requireFields(['current_password', 'new_password'], $data);

        if (!$member->checkPassword((string)$data['current_password'])->isValid()) {
            return $this->apiError('Current password is incorrect', 400);
        }

        $passwordValidation = $this->validatePasswordAgainstPolicy((string)$data['new_password']);
        if (!$passwordValidation->isValid()) {
            return $this->apiError($this->getPrimaryValidationMessage($passwordValidation), 400);
        }
        $member->changePassword((string)$data['new_password']);
        $member->write();

        return $this->apiSuccess('Password changed');
    }

    public function me(HTTPRequest $request): HTTPResponse
    {
        $member = $this->requireAuth();
        $method = strtoupper($request->httpMethod());

        if ($method === 'GET') {
            return $this->apiResponse([
                'data' => $this->serializeMember($member),
            ]);
        }

        if ($method === 'PUT' || $method === 'PATCH') {
            $data = $this->getBodyParams();
            if (isset($data['first_name'])) {
                $member->FirstName = trim((string)$data['first_name']);
            }
            if (isset($data['last_name'])) {
                $member->Surname = trim((string)$data['last_name']);
            }
            $member->write();

            return $this->apiResponse([
                'message' => 'Profile updated',
                'data' => $this->serializeMember($member),
            ]);
        }

        return $this->apiError('Method not allowed', 405);
    }

    /**
     * @return array{id:int|string,email:string,first_name:string,last_name:string}
     */
    private function serializeMember(Member $member): array
    {
        return [
            'id' => $this->idObfuscation->encode(Member::class, (int)$member->ID),
            'email' => (string)$member->Email,
            'first_name' => (string)$member->FirstName,
            'last_name' => (string)$member->Surname,
        ];
    }

    private function isPasswordStrong(string $password): bool
    {
        return mb_strlen($password) >= $this->getMinPasswordLength();
    }

    private function validatePasswordAgainstPolicy(string $password): ValidationResult
    {
        $validator = null;

        if (method_exists(Member::class, 'password_validator')) {
            $candidate = Member::password_validator();
            if ($candidate instanceof PasswordValidator) {
                $validator = $candidate;
            }
        }

        if (!$validator) {
            try {
                $candidate = Injector::inst()->get(PasswordValidator::class);
                if ($candidate instanceof PasswordValidator) {
                    $validator = $candidate;
                }
            } catch (\Throwable) {
                // Fall back to minimum-length policy below.
            }
        }

        if ($validator) {
            return $validator->validate($password, null);
        }

        $fallback = ValidationResult::create();
        if (!$this->isPasswordStrong($password)) {
            $fallback->addError(sprintf('Password must be at least %d characters', $this->getMinPasswordLength()));
        }

        return $fallback;
    }

    /**
     * @return array<int, string>
     */
    private function getValidationMessages(ValidationResult $validation): array
    {
        $messages = [];
        foreach ($validation->getMessages() as $message) {
            if (is_string($message)) {
                $messages[] = trim($message);
                continue;
            }

            if (is_array($message) && isset($message['message']) && is_string($message['message'])) {
                $messages[] = trim($message['message']);
                continue;
            }

            if (is_object($message) && method_exists($message, 'getMessage')) {
                $value = $message->getMessage();
                if (is_string($value)) {
                    $messages[] = trim($value);
                }
            }
        }

        $uniqueMessages = array_unique($messages);
        $filteredMessages = array_filter($uniqueMessages, static fn (string $value): bool => $value !== '');
        $messages = array_values($filteredMessages);

        if ($messages === [] && !$validation->isValid()) {
            $messages[] = sprintf('Password must be at least %d characters', $this->getMinPasswordLength());
        }

        return $messages;
    }

    private function getPrimaryValidationMessage(ValidationResult $validation): string
    {
        $messages = $this->getValidationMessages($validation);
        if ($messages === []) {
            return 'Password is invalid';
        }

        return $messages[0];
    }

    /**
     * @return array{score:int,label:string}
     */
    private function getPasswordStrength(string $password): array
    {
        $length = mb_strlen($password);
        $hasLower = preg_match('/[a-z]/', $password) === 1;
        $hasUpper = preg_match('/[A-Z]/', $password) === 1;
        $hasDigit = preg_match('/\d/', $password) === 1;
        $hasSymbol = preg_match('/[^a-zA-Z\d]/', $password) === 1;
        $characterSets = ($hasLower ? 1 : 0) + ($hasUpper ? 1 : 0) + ($hasDigit ? 1 : 0) + ($hasSymbol ? 1 : 0);

        $score = 0;
        if ($length >= 8) {
            $score++;
        }
        if ($length >= 12) {
            $score++;
        }
        if ($characterSets >= 3) {
            $score++;
        }
        if ($characterSets === 4 && $length >= 14) {
            $score++;
        }

        $label = 'weak';
        if ($score >= 3) {
            $label = 'strong';
        } elseif ($score >= 2) {
            $label = 'medium';
        }

        return [
            'score' => $score,
            'label' => $label,
        ];
    }

    private function getMinPasswordLength(): int
    {
        return max(8, (int)Config::inst()->get(self::class, 'min_password_length'));
    }
}
