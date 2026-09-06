<?php
/**
 * setup 路由定义
 */
declare(strict_types=1);

namespace App;

Router::get('/api/setup/status', 'SetupController@status');
Router::post('/api/setup/install', 'SetupController@install');