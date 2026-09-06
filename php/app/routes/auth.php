<?php
/**
 * auth 路由定义
 */
declare(strict_types=1);

namespace App;

Router::post('/api/auth/login', 'AuthController@login');
Router::post('/api/auth/register', 'AuthController@register');
Router::post('/api/auth/logout', 'AuthController@logout');
Router::post('/api/auth/send-verify-code', 'AuthController@sendVerifyCode');
Router::post('/api/auth/test-mail', 'AuthController@testMail');