<?php
/**
 * sitemap.xml 生成器
 * 站点地图包含：静态页面 + 全部公开材质详情页
 */
declare(strict_types=1);

namespace App;

class Sitemap
{
    /** 输出文件（web 根目录） */
    private static function output(): string
    {
        return BS_PUBLIC . '/sitemap.xml';
    }

    /** 重新生成 sitemap.xml（成功返回 true，失败 false） */
    public static function generate(): bool
    {
        $siteUrl = Config::siteUrl();
        if ($siteUrl === '') {
            // 未配置站点 URL 时回退到请求域名
            $siteUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http')
                . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
        }
        $siteUrl = rtrim($siteUrl, '/');

        $pages = [
            '/' => ['changefreq' => 'daily', 'priority' => '1.0'],
            '/skinlib' => ['changefreq' => 'daily', 'priority' => '0.9'],
            '/auth/login' => ['changefreq' => 'monthly', 'priority' => '0.5'],
            '/auth/register' => ['changefreq' => 'monthly', 'priority' => '0.5'],
            '/user/player' => ['changefreq' => 'weekly', 'priority' => '0.4'],
            '/user/closet' => ['changefreq' => 'weekly', 'priority' => '0.4'],
        ];

        $textures = Database::all(
            'SELECT tid, name, upload_at FROM textures WHERE public = 1 ORDER BY tid DESC'
        );

        $urls = '';
        $today = date('Y-m-d');
        foreach ($pages as $path => $meta) {
            $urls .= "  <url>\n"
                . "    <loc>{$siteUrl}{$path}</loc>\n"
                . "    <lastmod>{$today}</lastmod>\n"
                . "    <changefreq>{$meta['changefreq']}</changefreq>\n"
                . "    <priority>{$meta['priority']}</priority>\n"
                . "  </url>\n";
        }
        foreach ($textures as $t) {
            $lastmod = $t['upload_at'] ? date('Y-m-d', strtotime($t['upload_at'])) : $today;
            $urls .= "  <url>\n"
                . "    <loc>{$siteUrl}/skinlib/{$t['tid']}</loc>\n"
                . "    <lastmod>{$lastmod}</lastmod>\n"
                . "    <changefreq>weekly</changefreq>\n"
                . "    <priority>0.6</priority>\n"
                . "  </url>\n";
        }

        $xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n"
            . "<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n"
            . $urls
            . "</urlset>\n";

        return @file_put_contents(self::output(), $xml) !== false;
    }
}
