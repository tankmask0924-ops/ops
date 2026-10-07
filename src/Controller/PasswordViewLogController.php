<?php

declare(strict_types=1);

namespace App\Controller;

use App\Support\Json;
use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Database\Query\Builder;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * 密码查看记录（只有管理员能看）
 */
final class PasswordViewLogController
{
    public function list(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $params = $request->getQueryParams();
        $page = max(1, (int) ($params['page'] ?? 1));
        $pageSize = min(100, max(1, (int) ($params['page_size'] ?? 20)));
        $userId = (int) ($params['user_id'] ?? 0);
        $platformId = (int) ($params['platform_id'] ?? 0);

        // 账号、平台删除后记录还在，名字显示为空
        $query = DB::table('password_view_logs as l')
            ->leftJoin('users as u', 'u.id', '=', 'l.user_id')
            ->leftJoin('platforms as p', 'p.id', '=', 'l.platform_id')
            ->when($userId > 0, fn (Builder $q) => $q->where('l.user_id', $userId))
            ->when($platformId > 0, fn (Builder $q) => $q->where('l.platform_id', $platformId));

        $total = (clone $query)->count();
        $items = $query
            ->select(['l.id', 'l.user_id', 'l.platform_id', 'l.ip', 'l.created_at', 'u.username', 'u.name as user_name', 'p.title as platform_title'])
            ->orderByDesc('l.id')
            ->forPage($page, $pageSize)
            ->get();

        return Json::write($response, ['items' => $items, 'total' => $total]);
    }
}
