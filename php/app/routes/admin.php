<?php
/**
 * admin 路由定义
 */
declare(strict_types=1);

namespace App;

Router::get('/api/admin/stats', 'AdminController@stats');
Router::get('/api/admin/users', 'AdminController@users');
Router::put('/api/admin/users/:uid/permission', 'AdminController@updatePermission');
Router::get('/api/admin/settings', 'AdminController@settings');
Router::put('/api/admin/settings', 'AdminController@updateSettings');