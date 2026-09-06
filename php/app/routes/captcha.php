<?php
/**
 * 验证码路由
 */
declare(strict_types=1);

namespace App;

Router::get('/api/captcha/generate', 'CaptchaController@generate');
Router::post('/api/captcha/verify', 'CaptchaController@verify');