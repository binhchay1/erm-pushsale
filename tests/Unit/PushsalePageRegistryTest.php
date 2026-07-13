<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class PushsalePageRegistryTest extends TestCase
{
    public function test_every_registered_page_has_a_unique_route_component_and_template(): void
    {
        $pages = require dirname(__DIR__, 2).'/config/pushsale_pages.php';
        $slugs = [];

        foreach ($pages as $code => $page) {
            $this->assertNotEmpty($page['slug'] ?? null, "Page {$code} is missing slug");
            $this->assertNotEmpty($page['component'] ?? null, "Page {$code} is missing component");
            $this->assertArrayNotHasKey($page['slug'], $slugs, "Duplicate page slug {$page['slug']}");
            $slugs[$page['slug']] = $code;

            $component = dirname(__DIR__, 2).'/resources/js/pages/Pushsale/Modules/'.$page['component'].'.jsx';
            $templateCode = $page['template_alias'] ?? $code;
            $template = dirname(__DIR__, 2).'/public/pushsale-templates/'.$templateCode.'.html';

            $this->assertFileExists($component, "Missing component for page {$code}");
            $this->assertFileExists($template, "Missing template for page {$code}");
        }
    }

    public function test_navigation_codes_match_page_registry_once(): void
    {
        $pages = require dirname(__DIR__, 2).'/config/pushsale_pages.php';
        $navigation = require dirname(__DIR__, 2).'/config/pushsale_navigation.php';
        $codes = [];

        $walk = function (array $items) use (&$walk, &$codes): void {
            foreach ($items as $item) {
                if (isset($item['code'])) {
                    $codes[] = (string) $item['code'];
                }
                $walk((array) ($item['children'] ?? []));
            }
        };
        $walk($navigation);

        $expected = array_keys($pages);
        $actual = array_values(array_unique($codes));
        sort($expected, SORT_NATURAL);
        sort($actual, SORT_NATURAL);

        $this->assertSame($expected, $actual);
        $this->assertCount(count($codes), array_unique($codes), 'A page code appears in multiple menu leaves.');
    }
}
