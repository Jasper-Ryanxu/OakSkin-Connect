<?php
/**
 * 站点设置控制器（公开接口）
 */
declare(strict_types=1);

namespace App\Controllers;

use App\Config;
use App\Response;

class SettingsController
{
    /** GET /api/settings/public 公开站点设置 */
    public function publicSettings(): never
    {
        Response::data([
            'code' => 0,
            'data' => [
                'max_upload_size' => (int) Config::get('upload.max_size', 51200), // KB
                'announcement' => (string) Config::get('announcement.content', ''),
                'site_url' => (string) Config::siteUrl(),
                'site_name' => (string) Config::get('site.name', 'OakSkin Connect'),
                'seo_keywords' => (string) Config::get('seo.keywords', ''),
                'seo_description' => (string) Config::get('seo.description', ''),
                'friend_links' => (array) Config::get('friend_links', []),
            ],
        ]);
    }
}