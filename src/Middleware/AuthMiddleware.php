<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Service\AuthService;
use App\Support\HttpError;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * 后台登录校验：Authorization: Bearer <JWT>，通过后把当前账号放到请求属性 user
 */
final class AuthMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (!preg_match('/^Bearer\s+(\S+)$/i', $request->getHeaderLine('Authorization'), $m)) {
            throw new HttpError('请先登录', 401);
        }

        return $handler->handle($request->withAttribute('user', AuthService::userFromToken($m[1])));
    }
}
