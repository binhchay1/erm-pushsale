<?php

namespace App\Support;

/**
 * SEO meta cho các trang công khai (marketing / vệ tinh).
 *
 * Controller gọi Seo::page('features') để set meta cho request hiện tại;
 * blade `app.blade.php` đọc lại qua resolved() để render server-side
 * (crawler thấy ngay title/description/OG dù app là SPA Inertia).
 */
class Seo
{
    /** @var array<string, string>|null */
    private ?array $data = null;

    /**
     * Set meta theo key trong lang/{locale}/seo.php và trả về mảng cho Inertia prop.
     *
     * @return array<string, string>
     */
    public function page(string $key): array
    {
        $title = __("seo.pages.$key.title");
        $description = __("seo.pages.$key.description");
        $keywords = __("seo.pages.$key.keywords");

        $brand = (string) config('saleops.brand.name', 'ERM SaleOps');

        $data = [
            'title' => $title.' · '.$brand,
            'heading' => $title,
            'description' => $description,
            'keywords' => $keywords,
            'canonical' => url()->current(),
            'site_name' => $brand,
            'image' => url('/og-image.svg'),
            'locale' => str_replace('_', '-', app()->getLocale()),
        ];

        $this->data = $data;

        return $data;
    }

    /** @return array<string, string> */
    public function resolved(): array
    {
        return $this->data ?? $this->defaults();
    }

    /** @return array<string, string> */
    private function defaults(): array
    {
        $brand = (string) config('saleops.brand.name', 'ERM SaleOps');

        return [
            'title' => $brand,
            'heading' => $brand,
            'description' => (string) __('brand.tagline'),
            'keywords' => '',
            'canonical' => url()->current(),
            'site_name' => $brand,
            'image' => url('/og-image.svg'),
            'locale' => str_replace('_', '-', app()->getLocale()),
        ];
    }
}
