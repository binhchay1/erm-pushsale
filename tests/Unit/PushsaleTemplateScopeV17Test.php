<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class PushsaleTemplateScopeV17Test extends TestCase
{
    public function test_every_captured_style_is_scoped_and_login_template_has_no_captured_users(): void
    {
        $templates = glob(dirname(__DIR__, 2).'/public/pushsale-templates/*.html') ?: [];
        $this->assertNotEmpty($templates);

        foreach ($templates as $template) {
            $html = file_get_contents($template);
            preg_match_all('/<style\b[^>]*>(.*?)<\/style>/is', $html, $matches);
            foreach ($matches[1] as $css) {
                $this->assertStringStartsWith('@scope', trim($css), basename($template).' contains an unscoped style block.');
            }
            $this->assertDoesNotMatchRegularExpression('/<script\b/i', $html, basename($template).' contains an executable script.');
            $this->assertDoesNotMatchRegularExpression('/<(?:div|span)\b[^>]*class=["\'][^"\']*\b(?:chosen-container|select2-container)\b/i', $html, basename($template).' contains generated select markup.');
            $this->assertDoesNotMatchRegularExpression('/\b(?:Lại Giang|Phạm Lý|Phan Văn Minh|ttgroup2\.marketing\d+|tt\.sale\d+)\b/i', $html, basename($template).' contains captured Pushsale tenant data.');
        }

        $loginTemplate = file_get_contents(dirname(__DIR__, 2).'/public/pushsale-templates/1.7.1.html');
        $this->assertStringContainsString('data-pushsale-login-user-summary="1"', $loginTemplate);
        $this->assertStringNotContainsString('Lại Giang', $loginTemplate);
        $this->assertStringNotContainsString('Phạm Lý', $loginTemplate);
    }
}
