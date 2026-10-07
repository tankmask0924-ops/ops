<?php

declare(strict_types=1);

namespace App\Support;

use PDOException;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Exception\HttpException;
use Slim\Exception\HttpMethodNotAllowedException;
use Slim\Exception\HttpNotFoundException;
use Throwable;

/**
 * 所有异常统一返回 JSON：{"message": "..."}
 */
final class ErrorHandler
{
    public function __construct(private readonly ResponseFactoryInterface $responseFactory)
    {
    }

    public function __invoke(ServerRequestInterface $request, Throwable $e, bool $displayErrorDetails): ResponseInterface
    {
        [$status, $message] = match (true) {
            $e instanceof HttpError => [$e->status, $e->getMessage()],
            $e instanceof HttpNotFoundException => [404, '接口不存在'],
            $e instanceof HttpMethodNotAllowedException => [405, '请求方法不允许'],
            $e instanceof HttpException => [$e->getCode(), $e->getMessage()],
            self::isDbConnectionError($e) => [503, '数据库连接失败，请检查服务器 .env 里的数据库配置（DB_HOST 等）'],
            default => [500, '服务器内部错误'],
        };

        if ($status >= 500 && !$e instanceof HttpError) {
            // 503（数据库连不上）也记日志，方便在服务器上排查具体原因
            error_log(sprintf('[%s] %s %s: %s', date('Y-m-d H:i:s'), $request->getMethod(), $request->getUri()->getPath(), $e));
        }

        $data = ['success' => false, 'message' => $message];
        if ($displayErrorDetails && $status >= 500 && !$e instanceof HttpError) {
            $data['error'] = $e->getMessage();
            $data['trace'] = explode("\n", $e->getTraceAsString());
        }

        return Json::write($this->responseFactory->createResponse(), $data, $status);
    }

    /** 连不上 MySQL、账号密码错、库不存在：Laravel 会包一层 QueryException，要沿着 previous 往里找 */
    private static function isDbConnectionError(Throwable $e): bool
    {
        for (; $e !== null; $e = $e->getPrevious()) {
            if ($e instanceof PDOException && in_array((int) $e->getCode(), [1044, 1045, 1049, 2002, 2003, 2005, 2006], true)) {
                return true;
            }
        }

        return false;
    }
}
