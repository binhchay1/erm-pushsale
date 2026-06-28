<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;

class SitemapController extends Controller
{
    /** Các đường dẫn công khai + độ ưu tiên cho crawler. */
    private const PAGES = [
        ['path' => '/', 'priority' => '1.0', 'freq' => 'weekly'],
        ['path' => '/features', 'priority' => '0.9', 'freq' => 'monthly'],
        ['path' => '/solutions', 'priority' => '0.8', 'freq' => 'monthly'],
        ['path' => '/about', 'priority' => '0.6', 'freq' => 'yearly'],
        ['path' => '/contact', 'priority' => '0.7', 'freq' => 'yearly'],
        ['path' => '/login', 'priority' => '0.5', 'freq' => 'yearly'],
    ];

    public function __invoke(): Response
    {
        $today = now()->toDateString();

        $urls = '';
        foreach (self::PAGES as $page) {
            $loc = htmlspecialchars(url($page['path']), ENT_XML1);
            $urls .= "    <url>\n";
            $urls .= "        <loc>{$loc}</loc>\n";
            $urls .= "        <lastmod>{$today}</lastmod>\n";
            $urls .= "        <changefreq>{$page['freq']}</changefreq>\n";
            $urls .= "        <priority>{$page['priority']}</priority>\n";
            $urls .= "    </url>\n";
        }

        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n"
            .'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n"
            .$urls
            .'</urlset>'."\n";

        return response($xml, 200, ['Content-Type' => 'application/xml']);
    }
}
