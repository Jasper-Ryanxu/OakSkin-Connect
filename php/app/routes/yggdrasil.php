<?php
/**
 * Yggdrasil 外置登录路由定义（挂载于 /api/yggdrasil）
 */
declare(strict_types=1);

namespace App;

// 元数据
Router::get('/api/yggdrasil', 'YggdrasilController@meta');

// authserver
Router::post('/api/yggdrasil/authserver/authenticate', 'YggdrasilController@authenticate');
Router::post('/api/yggdrasil/authserver/refresh', 'YggdrasilController@refresh');
Router::post('/api/yggdrasil/authserver/validate', 'YggdrasilController@validate');
Router::post('/api/yggdrasil/authserver/signout', 'YggdrasilController@signout');
Router::post('/api/yggdrasil/authserver/invalidate', 'YggdrasilController@invalidate');

// sessionserver
Router::post('/api/yggdrasil/sessionserver/session/minecraft/join', 'YggdrasilController@join');
Router::get('/api/yggdrasil/sessionserver/session/minecraft/hasJoined', 'YggdrasilController@hasJoined');
Router::get('/api/yggdrasil/sessionserver/session/minecraft/profile/:uuid', 'YggdrasilController@profile');

// profiles
Router::post('/api/yggdrasil/api/profiles/minecraft', 'YggdrasilController@profilesByNames');
Router::get('/api/yggdrasil/api/users/profiles/minecraft/:username', 'YggdrasilController@userProfileByName');

// 材质上传/重置
Router::put('/api/yggdrasil/api/user/profile/:uuid/:type', 'YggdrasilController@uploadTexture');
Router::delete('/api/yggdrasil/api/user/profile/:uuid/:type', 'YggdrasilController@resetTexture');