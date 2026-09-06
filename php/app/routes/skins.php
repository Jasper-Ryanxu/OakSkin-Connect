<?php
/**
 * skins 路由定义（/skinlib 与 /texture）
 */
declare(strict_types=1);

namespace App;

// 皮肤库
Router::get('/api/skinlib/list', 'SkinController@list');
Router::get('/api/skinlib/show/:tid', 'SkinController@show');
Router::get('/api/skinlib/stats', 'SkinController@stats');

// 材质操作
Router::post('/api/texture', 'SkinController@upload');
Router::put('/api/texture/:tid/type', 'SkinController@updateType');
Router::put('/api/texture/:tid/name', 'SkinController@updateName');
Router::put('/api/texture/:tid/privacy', 'SkinController@updatePrivacy');
Router::delete('/api/texture/:tid', 'SkinController@delete');
Router::get('/api/texture/download/:tid', 'SkinController@download');