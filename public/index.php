<?php

declare(strict_types=1);

// 用 PHP 内置服务器（docker-compose）运行时：静态文件直接返回，后台页面走前端路由
if (PHP_SAPI === 'cli-server') {
    $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    if ($path !== '/' && is_file(__DIR__ . $path)) {
        return false;
    }
    if ($path === '/') {
        header('Location: /admin/');

        return true;
    }
    if ($path === '/admin' || str_starts_with($path, '/admin/')) {
        $index = __DIR__ . '/admin/index.html';
        if (is_file($index)) {
            header('Content-Type: text/html; charset=utf-8');
            readfile($index);

            return true;
        }
    }
}

(require __DIR__ . '/../bootstrap/app.php')->run();
