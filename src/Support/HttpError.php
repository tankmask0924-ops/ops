<?php

declare(strict_types=1);

namespace App\Support;

use RuntimeException;

/**
 * 业务错误：由 ErrorHandler 转成 {"message": "..."} 和对应的 HTTP 状态码
 */
class HttpError extends RuntimeException
{
    public function __construct(string $message, public readonly int $status = 400)
    {
        parent::__construct($message);
    }
}
