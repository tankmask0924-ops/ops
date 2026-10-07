<?php

declare(strict_types=1);

namespace App\Service;

use App\Support\HttpError;

/**
 * 平台密码加解密：AES-256-GCM，密钥是 .env 的 PASSWORD_KEY（64 位十六进制）。
 * 密文格式 v1:base64(iv 12 字节 + tag 16 字节 + 密文)
 */
final class Crypto
{
    private const CIPHER = 'aes-256-gcm';

    private const PREFIX = 'v1:';

    public static function encrypt(string $plain): string
    {
        $iv = random_bytes(12);
        $cipher = openssl_encrypt($plain, self::CIPHER, self::key(), OPENSSL_RAW_DATA, $iv, $tag);
        if ($cipher === false) {
            throw new HttpError('密码加密失败', 500);
        }

        return self::PREFIX . base64_encode($iv . $tag . $cipher);
    }

    public static function decrypt(string $encrypted): string
    {
        $raw = str_starts_with($encrypted, self::PREFIX) ? base64_decode(substr($encrypted, strlen(self::PREFIX)), true) : false;
        $plain = $raw === false || strlen($raw) < 28
            ? false
            : openssl_decrypt(substr($raw, 28), self::CIPHER, self::key(), OPENSSL_RAW_DATA, substr($raw, 0, 12), substr($raw, 12, 16));
        if ($plain === false) {
            throw new HttpError('密码解密失败：服务器的 PASSWORD_KEY 和保存密码时用的不一致', 500);
        }

        return $plain;
    }

    private static function key(): string
    {
        $hex = $_ENV['PASSWORD_KEY'] ?? '';
        if (!preg_match('/^[0-9a-fA-F]{64}$/', $hex)) {
            throw new HttpError('服务端未配置 PASSWORD_KEY（64 位十六进制）', 500);
        }

        return hex2bin($hex);
    }
}
