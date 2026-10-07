<?php

declare(strict_types=1);

namespace App\Controller;

use App\Model\User;
use App\Service\AuthService;
use App\Support\HttpError;
use App\Support\Json;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class AuthController
{
    public function login(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $body = Json::body($request);
        $username = trim((string) ($body['username'] ?? ''));
        $password = (string) ($body['password'] ?? '');
        if ($username === '' || $password === '') {
            throw new HttpError('请输入账号和密码');
        }

        return Json::write($response, AuthService::login($username, $password));
    }

    public function me(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        /** @var User $user */
        $user = $request->getAttribute('user');

        return Json::write($response, [
            'id' => $user->id,
            'username' => $user->username,
            'name' => $user->name,
            'is_admin' => $user->is_admin,
        ]);
    }

    public function changePassword(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        /** @var User $user */
        $user = $request->getAttribute('user');
        $body = Json::body($request);
        if (!password_verify((string) ($body['old_password'] ?? ''), $user->password_hash)) {
            throw new HttpError('原密码错误');
        }
        AuthService::changePassword($user, (string) ($body['new_password'] ?? ''));

        // 其他地方的登录全部失效，当前页面换成新令牌继续使用
        return Json::write($response, ['token' => AuthService::issueToken($user)]);
    }
}
