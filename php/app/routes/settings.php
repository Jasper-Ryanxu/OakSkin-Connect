<?php
/**
 * settings 路由定义
 */
declare(strict_types=1);

namespace App;

Router::get('/api/settings/public', 'SettingsController@publicSettings');