<?php
/**
 * closet 路由定义
 */
declare(strict_types=1);

namespace App;

Router::get('/api/user/closet/list', 'ClosetController@list');
Router::get('/api/user/closet/ids', 'ClosetController@ids');
Router::post('/api/user/closet', 'ClosetController@add');
Router::put('/api/user/closet/:tid', 'ClosetController@rename');
Router::delete('/api/user/closet/:tid', 'ClosetController@remove');