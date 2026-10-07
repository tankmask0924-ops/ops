<?php

declare(strict_types=1);

namespace App\Controller;

use App\Model\Platform;
use App\Model\User;
use App\Service\AuthService;
use App\Support\HttpError;
use App\Support\Json;
use Carbon\Carbon;
use Illuminate\Database\Capsule\Manager as DB;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * 账号管理（只有管理员能用）：账号 + 能看到哪些平台
 */
final class UserController
{
    public function list(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $users = User::query()->with('platforms:id')->orderByDesc('is_admin')->orderBy('id')->get()
            ->map(fn (User $u) => [
                'id' => $u->id,
                'username' => $u->username,
                'name' => $u->name,
                'is_admin' => $u->is_admin,
                'status' => $u->status,
                'platform_ids' => $u->platforms->pluck('id')->all(),
                'last_login_at' => $u->last_login_at?->format('Y-m-d H:i:s'),
                'created_at' => $u->created_at?->format('Y-m-d H:i:s'),
            ]);

        return Json::write($response, $users);
    }

    public function create(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $body = Json::body($request);
        $username = trim((string) ($body['username'] ?? ''));
        if (!preg_match('/^[A-Za-z0-9_.@-]{3,50}$/', $username)) {
            throw new HttpError('账号只能包含字母、数字和 _.@-，3～50 位');
        }
        if (User::query()->where('username', $username)->exists()) {
            throw new HttpError('账号已存在');
        }
        AuthService::assertPasswordStrength($body['password'] ?? null);

        $user = new User(['username' => $username] + self::validProfile($body));
        $user->setPassword($body['password']);
        DB::connection()->transaction(function () use ($user, $body) {
            $user->save();
            $user->platforms()->sync($user->is_admin ? [] : self::validPlatformIds($body['platform_ids'] ?? []));
        });

        return Json::write($response, ['id' => $user->id], 201);
    }

    /** 修改资料和可见平台；传了 password 就重置密码，该账号已登录的会话随之失效 */
    public function update(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $user = self::find($args['id']);
        $body = Json::body($request);
        $profile = self::validProfile($body);

        /** @var User $me */
        $me = $request->getAttribute('user');
        if ($user->id === $me->id && ($profile['status'] !== User::STATUS_ENABLED || !$profile['is_admin'])) {
            throw new HttpError('不能禁用自己或取消自己的管理员');
        }
        $this->assertKeepsAdmin($user, $profile);

        $user->fill($profile);
        if (isset($body['password']) && $body['password'] !== '') {
            AuthService::assertPasswordStrength($body['password']);
            $user->setPassword($body['password']);
            $user->password_changed_at = Carbon::now()->subSecond();
            $user->login_failures = 0;
            $user->locked_until = null;
        }
        DB::connection()->transaction(function () use ($user, $body) {
            $user->save();
            if ($user->is_admin) {
                $user->platforms()->detach();
            } elseif (array_key_exists('platform_ids', $body)) {
                $user->platforms()->sync(self::validPlatformIds($body['platform_ids']));
            }
        });

        return Json::write($response, ['id' => $user->id]);
    }

    public function delete(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $user = self::find($args['id']);
        if ($user->id === $request->getAttribute('user')->id) {
            throw new HttpError('不能删除自己');
        }
        $this->assertKeepsAdmin($user, ['status' => User::STATUS_DISABLED, 'is_admin' => false]);
        DB::connection()->transaction(function () use ($user) {
            $user->platforms()->detach();
            $user->delete();
        });

        return Json::write($response, ['message' => '已删除']);
    }

    /** 至少保留一个启用的管理员，防止所有人被锁在账号管理外面 */
    private function assertKeepsAdmin(User $user, array $profile): void
    {
        $wasAdmin = $user->is_admin && $user->status === User::STATUS_ENABLED;
        $staysAdmin = $profile['is_admin'] && $profile['status'] === User::STATUS_ENABLED;
        if (!$wasAdmin || $staysAdmin) {
            return;
        }
        $others = User::query()->where('is_admin', true)
            ->where('status', User::STATUS_ENABLED)->where('id', '!=', $user->id)->exists();
        if (!$others) {
            throw new HttpError('至少要保留一个启用状态的管理员');
        }
    }

    private static function find(string $id): User
    {
        $user = User::query()->find((int) $id);
        if ($user === null) {
            throw new HttpError('账号不存在', 404);
        }

        return $user;
    }

    private static function validProfile(array $body): array
    {
        return [
            'name' => mb_substr(trim((string) ($body['name'] ?? '')), 0, 50),
            'is_admin' => (bool) ($body['is_admin'] ?? false),
            'status' => (int) ($body['status'] ?? User::STATUS_ENABLED) === User::STATUS_ENABLED
                ? User::STATUS_ENABLED
                : User::STATUS_DISABLED,
        ];
    }

    private static function validPlatformIds(mixed $ids): array
    {
        $ids = is_array($ids) ? array_map('intval', $ids) : [];

        return $ids === [] ? [] : Platform::query()->whereIn('id', $ids)->pluck('id')->all();
    }
}
