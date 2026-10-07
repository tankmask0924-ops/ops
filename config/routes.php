<?php

declare(strict_types=1);

use App\Controller\AuthController;
use App\Controller\PasswordViewLogController;
use App\Controller\PlatformController;
use App\Controller\UserController;
use App\Middleware\AdminOnlyMiddleware;
use App\Middleware\AuthMiddleware;
use App\Support\Json;
use Slim\App;
use Slim\Routing\RouteCollectorProxy as Group;

return static function (App $app): void {
    $app->get('/health', fn ($req, $res) => Json::write($res, ['ok' => true]));

    $app->post('/admin-api/auth/login', [AuthController::class, 'login']);

    $app->group('/admin-api', function (Group $g) {
        $g->get('/auth/me', [AuthController::class, 'me']);
        $g->post('/auth/password', [AuthController::class, 'changePassword']);

        // 平台：所有登录的人都能看（普通账号只看到分配给自己的）
        $g->get('/platforms', [PlatformController::class, 'list']);
        $g->get('/platforms/{id:\d+}/password', [PlatformController::class, 'password']);

        // 以下只有管理员能操作
        $g->group('', function (Group $g) {
            $g->post('/platforms', [PlatformController::class, 'create']);
            $g->put('/platforms/{id:\d+}', [PlatformController::class, 'update']);
            $g->delete('/platforms/{id:\d+}', [PlatformController::class, 'delete']);

            $g->get('/users', [UserController::class, 'list']);
            $g->post('/users', [UserController::class, 'create']);
            $g->put('/users/{id:\d+}', [UserController::class, 'update']);
            $g->delete('/users/{id:\d+}', [UserController::class, 'delete']);

            $g->get('/password-view-logs', [PasswordViewLogController::class, 'list']);
        })->add(new AdminOnlyMiddleware());
    })->add(new AuthMiddleware());
};
