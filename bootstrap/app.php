<?php

declare(strict_types=1);

use App\Support\ErrorHandler;
use Dotenv\Dotenv;
use Illuminate\Database\Capsule\Manager as Capsule;
use Slim\App;
use Slim\Factory\AppFactory;

require_once __DIR__ . '/../vendor/autoload.php';

define('BASE_PATH', dirname(__DIR__));

Dotenv::createImmutable(BASE_PATH)->safeLoad();
date_default_timezone_set($_ENV['APP_TIMEZONE'] ?? 'Asia/Shanghai');

$capsule = new Capsule();
$capsule->addConnection([
    'driver' => 'mysql',
    'host' => $_ENV['DB_HOST'] ?? '127.0.0.1',
    'port' => $_ENV['DB_PORT'] ?? '3306',
    'database' => $_ENV['DB_DATABASE'] ?? 'ops',
    'username' => $_ENV['DB_USERNAME'] ?? 'root',
    'password' => $_ENV['DB_PASSWORD'] ?? '',
    'charset' => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',
    'timezone' => '+08:00',
    // 连不上数据库时 5 秒就报错，不让请求一直挂到前端超时
    'options' => [PDO::ATTR_TIMEOUT => 5],
]);
$capsule->setAsGlobal();
$capsule->bootEloquent();

/** @var App $app */
$app = AppFactory::create();
$app->addBodyParsingMiddleware();
$app->addRoutingMiddleware();
$app->addErrorMiddleware(($_ENV['APP_DEBUG'] ?? 'false') === 'true', true, true)
    ->setDefaultErrorHandler(new ErrorHandler($app->getResponseFactory()));

(require BASE_PATH . '/config/routes.php')($app);

return $app;
