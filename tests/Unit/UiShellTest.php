<?php

namespace Tests\Unit;

use App\Support\UiShell;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class UiShellTest extends TestCase
{
    #[DataProvider('publicPaths')]
    public function test_public_routes_use_public_shell(string $path): void
    {
        $this->assertTrue((new UiShell)->isPublic(Request::create($path)));
    }

    public function test_internal_routes_use_pushsale_shell(): void
    {
        $shell = new UiShell;

        $this->assertFalse($shell->isPublic(Request::create('/admin/dashboard')));
        $this->assertFalse($shell->isPublic(Request::create('/admin/pages/4-2-ho-so-khach-hang')));
        $this->assertSame('pushsale', $shell->name(Request::create('/sales/workspace')));
    }

    public static function publicPaths(): array
    {
        return [
            ['/'],
            ['/login'],
            ['/forgot-password'],
            ['/reset-password/example-token'],
            ['/features'],
            ['/solutions'],
            ['/about'],
            ['/contact'],
        ];
    }
}
