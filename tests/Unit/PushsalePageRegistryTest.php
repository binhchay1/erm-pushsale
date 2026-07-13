<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class PushsalePageRegistryTest extends TestCase
{
    public function test_every_registered_page_has_a_unique_semantic_route_component_and_template(): void
    {
        $root = dirname(__DIR__, 2);
        $pages = require $root.'/config/pushsale_pages.php';
        $routes = require $root.'/config/pushsale_routes.php';
        $uris = [];
        $names = [];

        $this->assertSame(array_keys($pages), array_keys($routes));

        foreach ($pages as $code => $page) {
            $route = $routes[$code];
            $uri = (string) ($route['uri'] ?? '');
            $name = (string) ($route['name'] ?? '');

            $this->assertNotSame('', $uri, "Page {$code} is missing semantic URI");
            $this->assertNotSame('', $name, "Page {$code} is missing route name");
            $this->assertStringNotContainsString('pages/', $uri, "Page {$code} still uses generic /pages URL");
            $this->assertDoesNotMatchRegularExpression('#(^|/)'.preg_quote(str_replace('.', '-', $code), '#').'(/|$)#', $uri, "Page {$code} exposes its menu number in the URL");
            $this->assertArrayNotHasKey($uri, $uris, "Duplicate page URI {$uri}");
            $this->assertArrayNotHasKey($name, $names, "Duplicate route name {$name}");
            $uris[$uri] = $code;
            $names[$name] = $code;

            $componentName = $page['component'] ?? 'Page_'.str_replace('.', '_', $code);
            $component = $root.'/resources/js/pages/Pushsale/Pages/'.$componentName.'.jsx';
            $templateCode = $page['template_alias'] ?? $code;
            $template = $root.'/public/pushsale-templates/'.$templateCode.'.html';

            $this->assertFileExists($component, "Missing component for page {$code}");
            $this->assertFileExists($template, "Missing template for page {$code}");
        }
    }

    public function test_navigation_codes_and_urls_match_page_registry(): void
    {
        $root = dirname(__DIR__, 2);
        $pages = require $root.'/config/pushsale_pages.php';
        $routes = require $root.'/config/pushsale_routes.php';
        $navigation = require $root.'/config/pushsale_navigation.php';
        $leaves = [];

        $walk = function (array $items) use (&$walk, &$leaves): void {
            foreach ($items as $item) {
                if (isset($item['code'])) {
                    $leaves[(string) $item['code']] = (string) ($item['url'] ?? '');
                }
                $walk((array) ($item['children'] ?? []));
            }
        };
        $walk($navigation);

        foreach ($pages as $code => $_page) {
            $this->assertArrayHasKey($code, $leaves, "Menu is missing page code {$code}");
            $this->assertSame('/admin/'.$routes[$code]['uri'], $leaves[$code], "Menu URL mismatch for {$code}");
        }

        $this->assertSame('/admin/company/profile', $leaves['1.1.1'] ?? null);
    }

    public function test_dotted_page_codes_are_top_level_array_keys(): void
    {
        $pages = require dirname(__DIR__, 2).'/config/pushsale_pages.php';

        foreach (['1.10', '1.1.2', '2.6.1', '8.5.17'] as $code) {
            $this->assertArrayHasKey($code, $pages);
            $this->assertIsArray($pages[$code]);
        }
    }
    public function test_dotted_resource_keys_are_top_level_array_keys(): void
    {
        $resources = require dirname(__DIR__, 2).'/config/pushsale_resources.php';

        foreach (['1.2.3', '1.3.1:product', '1.9', '5.3.1'] as $key) {
            $this->assertArrayHasKey($key, $resources);
            $this->assertIsArray($resources[$key]);
        }
    }

}
