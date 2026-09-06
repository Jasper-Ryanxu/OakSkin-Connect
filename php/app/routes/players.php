<?php
/**
 * players 路由定义
 */
declare(strict_types=1);

namespace App;

Router::get('/api/user/player/list', 'PlayerController@list');
Router::post('/api/user/player', 'PlayerController@add');
Router::put('/api/user/player/:pid/name', 'PlayerController@rename');
Router::put('/api/user/player/:pid/textures', 'PlayerController@setTextures');
Router::delete('/api/user/player/:pid/textures', 'PlayerController@clearTextures');
Router::delete('/api/user/player/:pid', 'PlayerController@delete');