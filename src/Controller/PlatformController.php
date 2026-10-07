<?php

declare(strict_types=1);

namespace App\Controller;

use App\Model\PasswordViewLog;
use App\Model\Platform;
use App\Model\User;
use App\Support\HttpError;
use App\Support\Json;
use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Database\Eloquent\Builder;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * 平台：所有人都能看（普通账号只能看到分配给自己的）；新增、修改、删除只有管理员能做
 */
final class PlatformController
{
    public function list(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        /** @var User $user */
        $user = $request->getAttribute('user');
        $keyword = trim((string) ($request->getQueryParams()['keyword'] ?? ''));
        // 关键字里的 % 和 _ 按普通字符搜索
        $like = '%' . addcslashes($keyword, '%_\\') . '%';

        $platforms = Platform::query()
            ->when($user->is_admin, fn (Builder $q) => $q->with('users:id'))
            ->unless($user->is_admin, fn (Builder $q) => $q->whereIn('id', DB::table('user_platforms')->where('user_id', $user->id)->select('platform_id')))
            ->when($keyword !== '', fn (Builder $q) => $q->where(fn (Builder $q) => $q
                ->where('title', 'like', $like)
                ->orWhere('category', 'like', $like)
                ->orWhere('url', 'like', $like)
                ->orWhere('account', 'like', $like)
                ->orWhere('description', 'like', $like)))
            ->orderBy('id')
            ->get()
            ->map(fn (Platform $p) => self::present($p, $user->is_admin));

        return Json::write($response, $platforms);
    }

    /** 查看密码明文：每次都记一条查看记录 */
    public function password(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        /** @var User $user */
        $user = $request->getAttribute('user');
        $platform = self::find($args['id']);
        if (!$user->canSee($platform)) {
            throw new HttpError('平台不存在', 404);
        }

        $password = $platform->password();
        PasswordViewLog::query()->create([
            'user_id' => $user->id,
            'platform_id' => $platform->id,
            'ip' => (string) ($request->getServerParams()['REMOTE_ADDR'] ?? ''),
        ]);

        return Json::write($response, ['password' => $password]);
    }

    public function create(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $body = Json::body($request);
        $platform = new Platform(self::validPlatform($body));
        $platform->setPassword(self::validPassword($body['password'] ?? ''));
        $platform->created_by = $platform->updated_by = $request->getAttribute('user')->id;

        DB::connection()->transaction(function () use ($platform, $body) {
            $platform->save();
            $platform->users()->sync(self::validUserIds($body['user_ids'] ?? []));
        });

        return Json::write($response, ['id' => $platform->id], 201);
    }

    /** 修改；password 不传或传空字符串表示不修改密码，clear_password=true 才清空 */
    public function update(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $platform = self::find($args['id']);
        $body = Json::body($request);
        $platform->fill(self::validPlatform($body));
        $password = self::validPassword($body['password'] ?? '');
        if ($password !== '' || !empty($body['clear_password'])) {
            $platform->setPassword($password);
        }
        $platform->updated_by = $request->getAttribute('user')->id;

        DB::connection()->transaction(function () use ($platform, $body) {
            $platform->save();
            if (array_key_exists('user_ids', $body)) {
                $platform->users()->sync(self::validUserIds($body['user_ids']));
            }
        });

        return Json::write($response, ['id' => $platform->id]);
    }

    public function delete(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $platform = self::find($args['id']);
        DB::connection()->transaction(function () use ($platform) {
            $platform->users()->detach();
            $platform->delete();
        });

        return Json::write($response, ['message' => '已删除']);
    }

    private static function present(Platform $p, bool $isAdmin): array
    {
        $data = [
            'id' => $p->id,
            'title' => $p->title,
            'category' => $p->category,
            'url' => $p->url,
            'account' => $p->account,
            'has_password' => $p->password_encrypted !== '',
            'description' => $p->description,
            'updated_at' => $p->updated_at?->format('Y-m-d H:i:s'),
        ];
        if ($isAdmin) {
            $data['user_ids'] = $p->users->pluck('id')->all();
        }

        return $data;
    }

    private static function find(string $id): Platform
    {
        $platform = Platform::query()->find((int) $id);
        if ($platform === null) {
            throw new HttpError('平台不存在', 404);
        }

        return $platform;
    }

    private static function validPlatform(array $body): array
    {
        $title = trim((string) ($body['title'] ?? ''));
        if ($title === '' || mb_strlen($title) > 100) {
            throw new HttpError('标题不能为空，最多 100 个字');
        }
        $category = trim((string) ($body['category'] ?? ''));
        if (mb_strlen($category) > 50) {
            throw new HttpError('分类最多 50 个字');
        }
        $url = trim((string) ($body['url'] ?? ''));
        if ($url !== '' && !preg_match('#^https?://#i', $url)) {
            // 只填了域名时补上 https://，卡片点击才能正常跳转
            $url = 'https://' . $url;
        }
        if (mb_strlen($url) > 500 || ($url !== '' && !preg_match('#^https?://[^\s/]+\S*$#iu', $url))) {
            throw new HttpError('网址格式不正确');
        }
        $account = trim((string) ($body['account'] ?? ''));
        if (mb_strlen($account) > 191) {
            throw new HttpError('账号最多 191 个字');
        }
        $description = trim((string) ($body['description'] ?? ''));
        if (mb_strlen($description) > 1000) {
            throw new HttpError('网站描述最多 1000 个字');
        }

        return ['title' => $title, 'category' => $category, 'url' => $url, 'account' => $account, 'description' => $description];
    }

    private static function validPassword(mixed $password): string
    {
        $password = is_string($password) ? $password : '';
        if (mb_strlen($password) > 500) {
            throw new HttpError('密码最多 500 个字');
        }

        return $password;
    }

    /** 只保留存在的普通账号（管理员本来就能看到全部，不用分配） */
    private static function validUserIds(mixed $ids): array
    {
        $ids = is_array($ids) ? array_map('intval', $ids) : [];

        return $ids === [] ? [] : User::query()->whereIn('id', $ids)->where('is_admin', false)->pluck('id')->all();
    }
}
