<?php
/**
 * 验证码控制器
 */
declare(strict_types=1);

namespace App\Controllers;

use App\Captcha;
use App\Response;

class CaptchaController
{
    /** GET /api/captcha/generate */
    public function generate(): never
    {
        $result = Captcha::generate();
        Response::data([
            'code' => 0,
            'data' => $result,
        ]);
    }

    /** POST /api/captcha/verify */
    public function verify(): never
    {
        $body = json_body();
        $token = $body['token'] ?? '';
        $position = (int) ($body['position'] ?? -1);

        if (empty($token) || $position < 0 || $position > 100) {
            Response::error('参数无效', 422, 422);
        }

        $ok = Captcha::verify($token, $position);
        if (!$ok) {
            Response::data([
                'code' => 1,
                'message' => '验证失败，请重试',
            ]);
        }

        Response::data([
            'code' => 0,
            'message' => '验证通过',
            'data' => [
                'token' => 'captcha_ok_' . md5($token . time()),
            ],
        ]);
    }
}