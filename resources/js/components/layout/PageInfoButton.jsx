import { usePage } from '@inertiajs/react';
import { useMemo, useState } from 'react';

import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import { findPageGuide } from '@/lib/page-guides';
import { useI18n } from '@/providers/I18nProvider';

function fallbackTitle(pathname, locale) {
    const last = pathname.split('/').filter(Boolean).pop() ?? 'dashboard';
    const label = last
        .replace(/[-_]+/g, ' ')
        .replace(/\b\w/g, (char) => char.toUpperCase());

    return locale === 'en' ? `Page guide: ${label}` : `Hướng dẫn trang: ${label}`;
}

function fallbackGuide(pathname, locale, t) {
    return {
        title: fallbackTitle(pathname, locale),
        intro: locale === 'en'
            ? 'This page uses the shared Pushsale layout. Use the filters, table and action buttons according to your permission scope.'
            : 'Trang này dùng cùng bộ khung Pushsale. Sử dụng bộ lọc, bảng dữ liệu và các nút thao tác theo quyền được cấp.',
        sections: [
            {
                heading: locale === 'en' ? 'How to use' : 'Cách sử dụng',
                items: [
                    locale === 'en'
                        ? 'Filter first, then press Search to reload data in the current permission scope.'
                        : 'Chọn bộ lọc trước, sau đó bấm Tìm kiếm để tải dữ liệu trong phạm vi quyền hiện tại.',
                    locale === 'en'
                        ? 'Rows can be selected when a bulk action bar is available at the bottom of the table.'
                        : 'Các dòng có thể tích chọn khi trang có thanh thao tác hàng loạt ở cuối bảng.',
                    locale === 'en'
                        ? 'Export buttons keep the same active filters unless the page explicitly says otherwise.'
                        : 'Nút xuất file giữ nguyên bộ lọc đang dùng, trừ khi trang có ghi chú riêng.',
                ],
            },
        ],
    };
}

export function PageInfoButton({ className = '' }) {
    const { url } = usePage();
    const { locale, t } = useI18n();
    const [open, setOpen] = useState(false);

    const pathname = url.split('?')[0];
    const guide = useMemo(
        () => findPageGuide(pathname, locale) ?? fallbackGuide(pathname, locale, t),
        [pathname, locale, t],
    );

    return (
        <>
            <Tooltip>
                <TooltipTrigger asChild>
                    <button
                        type="button"
                        className={`pushsale-header-info-trigger ${className}`.trim()}
                        aria-label={t('page_info.tooltip')}
                        title={t('page_info.tooltip')}
                        onClick={() => setOpen(true)}
                    >
                        <i className="fa fa-info-circle" aria-hidden="true" />
                    </button>
                </TooltipTrigger>
                <TooltipContent>{t('page_info.what_is_this')}</TooltipContent>
            </Tooltip>

            <Dialog open={open} onOpenChange={setOpen}>
                <DialogContent className="max-h-[85vh] max-w-xl gap-0 overflow-hidden p-0">
                    <DialogHeader className="border-b border-border/60 px-6 py-4">
                        <div className="flex items-center gap-2">
                            <span className="flex size-7 items-center justify-center rounded-full bg-primary/10 text-primary">
                                <i className="fa fa-info-circle" aria-hidden="true" />
                            </span>
                            <DialogTitle>{guide.title}</DialogTitle>
                        </div>
                        <DialogDescription className="pt-1">{guide.intro}</DialogDescription>
                    </DialogHeader>

                    <div className="max-h-[60vh] space-y-5 overflow-y-auto px-6 py-5">
                        {guide.sections.map((section) => (
                            <div key={section.heading}>
                                <h3 className="mb-2 text-sm font-semibold text-foreground">
                                    {section.heading}
                                </h3>
                                <ul className="space-y-1.5">
                                    {section.items.map((item, index) => (
                                        <li
                                            key={index}
                                            className="flex gap-2 text-sm leading-relaxed text-muted-foreground"
                                        >
                                            <span className="mt-[7px] size-1.5 shrink-0 rounded-full bg-primary/60" />
                                            <span>{item}</span>
                                        </li>
                                    ))}
                                </ul>
                            </div>
                        ))}
                    </div>
                </DialogContent>
            </Dialog>
        </>
    );
}
