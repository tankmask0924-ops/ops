<?php

declare(strict_types=1);

namespace App\Service;

use App\Model\User;
use App\Support\HttpError;
use Carbon\Carbon;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Throwable;

final class AuthService
{
    private const TOKEN_TTL = 12 * 3600;

    private const MAX_FAILURES = 5;

    private const LOCK_MINUTES = 15;

    public static function login(string $username, string $password): array
    {
        /** @var ?User $user */
        $user = User::query()->where('username', $username)->first();
        if ($user === null) {
            // 账号不存在也做一次哈希校验，避免通过响应时间探测账号是否存在
            password_hash($password, PASSWORD_DEFAULT);
            throw new HttpError('账号或密码错误', 401);
        }
        if ($user->locked_until !== null && $user->locked_until->isFuture()) {
            throw new HttpError(sprintf('密码错误次数过多，请 %d 分钟后再试', (int) ceil(Carbon::now()->diffInMinutes($user->locked_until))), 429);
        }
        if (!password_verify($password, $user->password_hash)) {
            $user->login_failures += 1;
            if ($user->login_failures >= self::MAX_FAILURES) {
                $user->login_failures = 0;
                $user->locked_until = Carbon::now()->addMinutes(self::LOCK_MINUTES);
            }
            $user->save();
            throw new HttpError('账号或密码错误', 401);
        }
        if ($user->status !== User::STATUS_ENABLED) {
            throw new HttpError('账号已被禁用', 403);
        }

        $user->login_failures = 0;
        $user->locked_until = null;
        $user->last_login_at = Carbon::now();
        $user->save();

        return ['token' => self::issueToken($user), 'expires_in' => self::TOKEN_TTL];
    }

    public static function issueToken(User $user): string
    {
        $now = time();

        return JWT::encode(['sub' => $user->id, 'iat' => $now, 'exp' => $now + self::TOKEN_TTL], self::secret(), 'HS256');
    }

    /** 校验令牌并返回当前账号；令牌无效、账号禁用、改过密码都视为未登录 */
    public static function userFromToken(string $token): User
    {
        try {
            $payload = JWT::decode($token, new Key(self::secret(), 'HS256'));
        } catch (Throwable) {
            throw new HttpError('登录已失效，请重新登录', 401);
        }

        /** @var ?User $user */
        $user = User::query()->find((int) $payload->sub);
        if ($user === null || $user->status !== User::STATUS_ENABLED) {
            throw new HttpError('账号不存在或已被禁用', 401);
        }
        if ($user->password_changed_at !== null && $payload->iat < $user->password_changed_at->getTimestamp()) {
            throw new HttpError('密码已修改，请重新登录', 401);
        }

        return $user;
    }

    public static function changePassword(User $user, string $password): void
    {
        self::assertPasswordStrength($password);
        $user->setPassword($password);
        // 让这一秒之前签发的令牌全部失效（JWT 的 iat 精度是秒，所以往前退一秒，保证改完后立刻签发的新令牌有效）
        $user->password_changed_at = Carbon::now()->subSecond();
        $user->save();
    }

    public static function assertPasswordStrength(mixed $password): void
    {
        if (!is_string($password) || strlen($password) < 8) {
            throw new HttpError('密码至少 8 位');
        }
    }

    private static function secret(): string
    {
        $secret = $_ENV['JWT_SECRET'] ?? '';
        if (strlen($secret) < 32) {
            throw new HttpError('服务端未配置 JWT_SECRET（至少 32 位）', 500);
        }

        return $secret;
    }
}
