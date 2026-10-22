import { Head, Link } from '@inertiajs/react';
import { useState, useMemo } from 'react';
import { ArrowRight, Search, SlidersHorizontal, CheckCircle2, Clock, Globe2 } from 'lucide-react';

import { PlatformCard } from '@/components/integrations/PlatformCard';
import { PageHeader } from '@/components/layout/PageHeader';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout';
import { tOr } from '@/lib/i18n-fallback';
import { cn } from '@/lib/utils';
import { useT } from '@/providers/I18nProvider';

export default function IntegrationsIndex({ hub, categories, platforms, stats }) {
    const t = useT();
    const [selectedCategory, setSelectedCategory] = useState('all');
    const [searchTerm, setSearchTerm] = useState('');

    const categoryList = useMemo(() => {
        return [
            { key: 'all', label: 'Tất cả nền tảng', count: platforms.length },
            ...Object.keys(categories).map((key) => ({
                key,
                label: tOr(t, `integrations.categories.${key}`, categories[key]),
                count: platforms.filter((p) => p.category === key).length,
            })),
        ];
    }, [categories, platforms, t]);

    const filteredPlatforms = useMemo(() => {
        return platforms.filter((p) => {
            const matchesCategory = selectedCategory === 'all' || p.category === selectedCategory;
            const matchesSearch = !searchTerm.trim() || 
                p.label.toLowerCase().includes(searchTerm.toLowerCase()) ||
                p.platform.toLowerCase().includes(searchTerm.toLowerCase()) ||
                (p.description && p.description.toLowerCase().includes(searchTerm.toLowerCase()));
            return matchesCategory && matchesSearch;
        });
    }, [platforms, selectedCategory, searchTerm]);

    const groupedFiltered = useMemo(() => {
        if (selectedCategory !== 'all') {
            return [{
                key: selectedCategory,
                label: tOr(t, `integrations.categories.${selectedCategory}`, categories[selectedCategory] || 'Nền tảng'),
                items: filteredPlatforms,
            }];
        }

        return Object.keys(categories).map((key) => ({
            key,
            label: tOr(t, `integrations.categories.${key}`, categories[key]),
            items: filteredPlatforms.filter((p) => p.category === key),
        })).filter(g => g.items.length > 0);
    }, [categories, filteredPlatforms, selectedCategory, t]);

    return (
        <AppLayout>
            <Head title={t('pages.integrations_page.title')} />

            <div className="ps-feature-page ps-integrations-page space-y-6">
                <PageHeader
                    title={tOr(t, 'integrations.hub.title', hub.title)}
                    description={tOr(t, 'integrations.hub.summary', hub.summary)}
                    actions={
                        <Button variant="outline" size="sm" className="h-8.5 text-xs gap-1.5" asChild>
                            <Link href="/admin/leads">
                                {t('integrations.leads_log')}
                                <ArrowRight className="size-3.5" />
                            </Link>
                        </Button>
                    }
                />

                {/* METRICS ROW (QUIET, ACCURATE, NO GAUCHE SLOP) */}
                <div className="grid gap-4 sm:grid-cols-3">
                    <div className="rounded-xl border border-slate-200/90 bg-white p-5 shadow-2xs hover:border-slate-300 transition-all flex flex-col justify-between">
                        <div className="flex items-center justify-between">
                            <span className="text-xs font-semibold uppercase tracking-wider text-slate-500">
                                {t('integrations.leads_today')}
                            </span>
                            <div className="flex size-8 items-center justify-center rounded-lg bg-slate-50 border border-slate-200/70 text-slate-600">
                                <Globe2 className="size-4" />
                            </div>
                        </div>
                        <div className="mt-3 flex items-baseline gap-2">
                            <span className="font-mono text-3xl font-extrabold tracking-tight text-slate-900">
                                {stats.leads_today}
                            </span>
                            <span className="text-xs text-slate-500 font-medium">lead/đơn hôm nay</span>
                        </div>
                    </div>

                    <div className="rounded-xl border border-slate-200/90 bg-white p-5 shadow-2xs hover:border-slate-300 transition-all flex flex-col justify-between">
                        <div className="flex items-center justify-between">
                            <span className="text-xs font-semibold uppercase tracking-wider text-slate-500">
                                {t('integrations.leads_pending')}
                            </span>
                            <div className="flex size-8 items-center justify-center rounded-lg bg-amber-50 border border-amber-200/70 text-amber-600">
                                <Clock className="size-4" />
                            </div>
                        </div>
                        <div className="mt-3 flex items-baseline gap-2">
                            <span className="font-mono text-3xl font-extrabold tracking-tight text-slate-900">
                                {stats.leads_pending}
                            </span>
                            <span className="text-xs text-slate-500 font-medium">đang chờ phân bổ</span>
                        </div>
                    </div>

                    <div className="rounded-xl border border-slate-200/90 bg-white p-5 shadow-2xs hover:border-slate-300 transition-all flex flex-col justify-between">
                        <div className="flex items-center justify-between">
                            <span className="text-xs font-semibold uppercase tracking-wider text-slate-500">
                                {t('integrations.platforms_enabled')}
                            </span>
                            <div className="flex size-8 items-center justify-center rounded-lg bg-emerald-50 border border-emerald-200/70 text-emerald-600">
                                <CheckCircle2 className="size-4" />
                            </div>
                        </div>
                        <div className="mt-3 flex items-baseline gap-2">
                            <span className="font-mono text-3xl font-extrabold tracking-tight text-emerald-700">
                                {stats.platforms_enabled}
                            </span>
                            <span className="text-xs text-slate-500 font-medium">/ {platforms.length} kênh kích hoạt</span>
                        </div>
                    </div>
                </div>

                {/* FILTER & SEARCH BAR */}
                <div className="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between border-b border-slate-200 pb-4 pt-1">
                    {/* Category segmented pill track */}
                    <div className="inline-flex flex-wrap items-center gap-1.5 rounded-lg bg-slate-100/90 p-1.5 border border-slate-200/80">
                        {categoryList.map((cat) => {
                            const isSelected = selectedCategory === cat.key;
                            return (
                                <button
                                    key={cat.key}
                                    type="button"
                                    onClick={() => setSelectedCategory(cat.key)}
                                    className={cn(
                                        'inline-flex items-center gap-1.5 rounded-md px-3 py-1.5 text-xs transition-all cursor-pointer select-none',
                                        isSelected
                                            ? 'bg-white text-slate-900 font-semibold shadow-xs border border-slate-200/70'
                                            : 'bg-transparent text-slate-600 hover:text-slate-900 hover:bg-slate-200/60 font-medium border border-transparent'
                                    )}
                                >
                                    <span className={isSelected ? 'text-slate-900 font-semibold' : 'text-slate-600'}>
                                        {cat.label}
                                    </span>
                                    <span className={cn(
                                        'rounded-full px-1.5 py-0.2 text-[10px] font-mono',
                                        isSelected
                                            ? 'bg-blue-50 text-blue-700 border border-blue-200/60 font-semibold'
                                            : 'bg-slate-200/80 text-slate-600'
                                    )}>
                                        {cat.count}
                                    </span>
                                </button>
                            );
                        })}
                    </div>

                    {/* Search box */}
                    <div className="relative w-full sm:w-72 shrink-0">
                        <Search className="absolute left-3 top-1/2 -translate-y-1/2 size-4 text-slate-400 pointer-events-none" />
                        <Input
                            value={searchTerm}
                            onChange={(e) => setSearchTerm(e.target.value)}
                            placeholder="Tìm kiếm kênh kết nối..."
                            className="h-9 pl-9 text-xs font-normal bg-white border-slate-300 text-slate-800 placeholder:text-slate-400 shadow-2xs rounded-lg"
                        />
                    </div>
                </div>

                {/* PLATFORMS GRID */}
                {groupedFiltered.length === 0 ? (
                    <div className="rounded-lg border border-dashed border-border p-8 text-center space-y-2">
                        <p className="text-sm font-medium text-foreground">Không tìm thấy nền tảng kết nối phù hợp</p>
                        <p className="text-xs text-muted-foreground">Thử đổi từ khóa tìm kiếm hoặc chọn danh mục "Tất cả nền tảng".</p>
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={() => { setSelectedCategory('all'); setSearchTerm(''); }}
                            className="h-8 text-xs mt-2"
                        >
                            Đặt lại bộ lọc
                        </Button>
                    </div>
                ) : (
                    groupedFiltered.map((group) => (
                        <section key={group.key} className="space-y-4 pt-2">
                            <div className="flex items-center justify-between pb-1 border-b border-slate-200">
                                <h2 className="text-sm font-bold tracking-tight text-slate-900 flex items-center gap-2">
                                    <span>{group.label}</span>
                                    <span className="text-xs text-slate-500 font-normal font-mono">({group.items.length})</span>
                                </h2>
                            </div>
                            <div className="grid gap-5 xl:grid-cols-2">
                                {group.items.map((platform) => (
                                    <PlatformCard key={platform.platform} platform={platform} />
                                ))}
                            </div>
                        </section>
                    ))
                )}
            </div>
        </AppLayout>
    );
}
