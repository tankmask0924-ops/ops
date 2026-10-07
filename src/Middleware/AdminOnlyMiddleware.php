<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Model\User;
use App\Support\HttpError;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * 只有管理员能访问。必须放在 AuthMiddleware 之后执行
 */
final class AdminOnlyMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        /** @var User $user */
        $user = $request->getAttribute('user');
        if (!$user->is_admin) {
            throw new HttpError('只有管理员可以操作', 403);
        }

        return $handler->handle($request);
    }
}
