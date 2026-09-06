<?php
/**
 * OAuth 路由定义
 */
declare(strict_types=1);

namespace App;

// 提供方：应用管理（管理员）
Router::get('/api/admin/oauth/clients', 'OAuthController@clients');
Router::post('/api/admin/oauth/clients', 'OAuthController@createClient');
Router::delete('/api/admin/oauth/clients/:clientId', 'OAuthController@deleteClient');

// 提供方：授权 / 令牌 / 用户信息
Router::get('/api/oauth/client-info', 'OAuthController@clientInfo');
Router::post('/api/oauth/authorize', 'OAuthController@authorize');
Router::post('/api/oauth/token', 'OAuthController@token');
Router::get('/api/oauth/userinfo', 'OAuthController@userinfo');

// 客户端：OakSkin 第三方登录
Router::get('/api/auth/oakskin/url', 'OAuthController@oakSkinLoginUrl');
Router::any('/callback/oakskin', 'OAuthController@oakSkinCallback');