<?php
/**
 * user 路由定义
 */
declare(strict_types=1);

namespace App;

Router::get('/api/user', 'UserController@me');
Router::post('/api/user/profile', 'UserController@updateProfile');
Router::post('/api/user/avatar', 'UserController@setAvatar');
Router::get('/api/user/:uid/avatar', 'UserController@getAvatar');