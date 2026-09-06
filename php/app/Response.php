<?php
/**
 * 响应辅助：JSON 输出与错误响应
 */
declare(strict_types=1);

namespace App;

class Response
{
    /** 输出 JSON 并结束 */
    public static function json(mixed $data, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }

    /** 成功响应 */
    public static function ok(mixed $data, string $message = 'ok'): never
    {
        self::json(['code' => 0, 'message' => $message, 'data' => $data, 'value' => $data]);
    }

    /** 纯数据响应（部分接口直接返回对象） */
    public static function data(mixed $data): never
    {
        self::json($data);
    }

    /** 错误响应 */
    public static function error(string $message, int $status = 400, int $code = 0): never
    {
        self::json([
            'code' => $code ?: $status,
            'message' => $message,
        ], $status);
    }

    public static function notFound(string $message = '资源不存在'): never
    {
        self::error($message, 404, 404);
    }

    public static function forbidden(string $message = '权限不足'): never
    {
        self::error($message, 403, 403);
    }

    public static function unauthorized(string $message = '未登录'): never
    {
        self::error($message, 401, 401);
    }
}