<?php

declare(strict_types=1);

namespace App\Api\Services;

use App\Api\Models\ApiSession;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use SilverStripe\Control\Email\Email;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\ORM\DB;
use SilverStripe\Security\Member;
use RuntimeException;
use stdClass;
use Throwable;

class JwtService
{
    use Configurable;

    private static string $secret = '';
    private static int $access_token_expiry = 900;
    private static int $refresh_token_expiry = 2592000;
    private static bool $rotate_refresh_tokens = true;

    public function generateAccessToken(Member $member): string
    {
        $issuedAt = time();
        $expiresAt = $issuedAt + $this->getAccessTokenExpiry();

        $payload = [
            'sub' => (int)$member->ID,
            'email' => (string)$member->Email,
            'iat' => $issuedAt,
            'exp' => $expiresAt,
            'jti' => $this->generateUuidV4(),
        ];

        return JWT::encode($payload, $this->getSecret(), 'HS256');
    }

    /**
     * @return array{access_token:string,refresh_token:string,session_id:int}
     */
    public function issueTokens(Member $member, array $context = []): array
    {
        $refreshToken = $this->generateRefreshToken();

        $session = ApiSession::create();
        $session->MemberID = (int)$member->ID;
        $session->RefreshToken = $this->hashRefreshToken($refreshToken);
        $session->UserAgent = mb_substr((string)($context['user_agent'] ?? ''), 0, 255);
        $session->IPAddress = mb_substr((string)($context['ip_address'] ?? ''), 0, 45);
        $session->DeviceName = mb_substr((string)($context['device_name'] ?? ''), 0, 255);
        $session->LastUsed = DB::get_conn()->now();
        $session->ExpiresAt = date('Y-m-d H:i:s', time() + $this->getRefreshTokenExpiry());
        $session->write();

        return [
            'access_token' => $this->generateAccessToken($member),
            'refresh_token' => $refreshToken,
            'session_id' => (int)$session->ID,
        ];
    }

    public function decodeAccessToken(string $token): ?stdClass
    {
        try {
            /** @var stdClass $claims */
            $claims = JWT::decode($token, new Key($this->getSecret(), 'HS256'));
            return $claims;
        } catch (Throwable) {
            return null;
        }
    }

    public function validateRefreshToken(string $refreshToken, ?int $sessionId = null): ?ApiSession
    {
        $hashed = $this->hashRefreshToken($refreshToken);

        $query = ApiSession::get()->filter('RefreshToken', $hashed)
            ->filter('ExpiresAt:GreaterThan', date('Y-m-d H:i:s'));

        if ($sessionId !== null) {
            $query = $query->filter('ID', $sessionId);
        }

        /** @var ApiSession|null $session */
        $session = $query->first();
        return $session;
    }

    public function rotateRefreshToken(ApiSession $session): string
    {
        $token = $this->generateRefreshToken();
        $session->RefreshToken = $this->hashRefreshToken($token);
        $session->LastUsed = DB::get_conn()->now();
        $session->ExpiresAt = date('Y-m-d H:i:s', time() + $this->getRefreshTokenExpiry());
        $session->write();

        return $token;
    }

    public function revokeSession(ApiSession $session): void
    {
        $session->delete();
    }

    public function revokeAllSessions(Member $member): void
    {
        foreach (ApiSession::get()->filter('MemberID', (int)$member->ID) as $session) {
            $session->delete();
        }
    }

    /**
     * @return array<int, array{id:int,device_name:string,ip:string,last_used:string,created:string}>
     */
    public function listSessions(Member $member): array
    {
        $payload = [];

        foreach (ApiSession::get()->filter('MemberID', (int)$member->ID)->sort('Created', 'DESC') as $session) {
            $payload[] = [
                'id' => (int)$session->ID,
                'device_name' => (string)$session->DeviceName,
                'ip' => (string)$session->IPAddress,
                'last_used' => (string)$session->LastUsed,
                'created' => (string)$session->Created,
            ];
        }

        return $payload;
    }

    public function sendPasswordResetEmail(Member $member): void
    {
        $token = $this->generatePasswordResetToken($member);
        $email = Email::create()
            ->setTo((string)$member->Email)
            ->setSubject('Reset your password')
            ->setBody(sprintf(
                "Use this token to reset your password:\n\n%s\n\nThis token expires in 1 hour.",
                $token
            ));

        $email->send();
    }

    public function generatePasswordResetToken(Member $member): string
    {
        $issuedAt = time();
        $expiresAt = $issuedAt + 3600;

        return JWT::encode([
            'sub' => (int)$member->ID,
            'email' => (string)$member->Email,
            'purpose' => 'password_reset',
            'iat' => $issuedAt,
            'exp' => $expiresAt,
            'jti' => $this->generateUuidV4(),
        ], $this->getSecret(), 'HS256');
    }

    public function validatePasswordResetToken(string $token, string $email): ?Member
    {
        try {
            /** @var stdClass $claims */
            $claims = JWT::decode($token, new Key($this->getSecret(), 'HS256'));
            if (($claims->purpose ?? null) !== 'password_reset') {
                return null;
            }

            if (!isset($claims->sub) || !is_numeric($claims->sub)) {
                return null;
            }

            /** @var Member|null $member */
            $member = Member::get()->byID((int)$claims->sub);
            if (!$member) {
                return null;
            }

            return strtolower((string)$member->Email) === strtolower($email) ? $member : null;
        } catch (Throwable) {
            return null;
        }
    }

    public function shouldRotateRefreshTokens(): bool
    {
        return (bool)self::config()->get('rotate_refresh_tokens');
    }

    private function getSecret(): string
    {
        $secret = trim((string)self::config()->get('secret'));
        if ($secret === '' || $secret === 'change-me') {
            throw new RuntimeException('JWT secret is not configured. Set App\\Api\\Services\\JwtService.secret.');
        }

        return $secret;
    }

    private function getAccessTokenExpiry(): int
    {
        return (int)self::config()->get('access_token_expiry');
    }

    private function getRefreshTokenExpiry(): int
    {
        return (int)self::config()->get('refresh_token_expiry');
    }

    private function generateRefreshToken(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(48)), '+/', '-_'), '=');
    }

    private function hashRefreshToken(string $token): string
    {
        return hash_hmac('sha256', $token, $this->getSecret());
    }

    private function generateUuidV4(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }
}
