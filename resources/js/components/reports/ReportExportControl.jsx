import { router } from '@inertiajs/react';
import { Download, FileSpreadsheet } from 'lucide-react';

import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { cleanInertiaFilters } from '@/hooks/useInertiaFilters';
import { useT } from '@/providers/I18nProvider';

/**
 * Build report export URL (DRY #5).
 * @param {string} routeUrl
 * @param {Record<string, unknown>} filters
 * @param {string|number} exportValue `1` | `csv` | `xls` | …
 */
export function buildReportExportUrl(routeUrl, filters = {}, exportValue = '1') {
    const params = new URLSearchParams();
    Object.entries(cleanInertiaFilters(filters ?? {})).forEach(([key, value]) => {
        params.set(key, String(value));
    });
    params.set('export', String(exportValue));
    const separator = String(routeUrl).includes('?') ? '&' : '?';
    return `${routeUrl}${separator}${params.toString()}`;
}

/**
 * Unified report export control.
 *
 * Modes:
 * - `link` — Pushsale primary `<a href="?export=1">` (default)
 * - `visit` — `router.get` with export flag (CEO / system reports)
 * - `dropdown` — shadcn menu for csv + xls
 */
export function ReportExportControl({
    routeUrl,
    filters = {},
    label,
    mode = 'link',
    exportValue = '1',
    formats = ['csv', 'xls'],
    className = '',
    showIcon = true,
}) {
    const t = useT();
    const text = label ?? t('reports.pushsale.export_excel');

    if (mode === 'visit') {
        return (
            <button
                type="button"
                className={`btn btn-primary btn-sm ${className}`.trim()}
                onClick={() => {
                    router.get(
                        routeUrl,
                        { ...cleanInertiaFilters(filters), export: exportValue },
                        { preserveScroll: true },
                    );
                }}
            >
                {showIcon ? <i className="fa fa-file-excel-o" aria-hidden="true" /> : null}
                {showIcon ? ' ' : null}
                {text}
            </button>
        );
    }

    if (mode === 'dropdown') {
        return (
            <DropdownMenu>
                <DropdownMenuTrigger asChild>
                    <Button variant="outline" size="sm" className={className || undefined}>
                        <Download className="size-4" />
                        {label ?? t('common.export')}
                    </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end">
                    {formats.includes('csv') ? (
                        <DropdownMenuItem asChild>
                            <a href={buildReportExportUrl(routeUrl, filters, 'csv')} download className="cursor-pointer">
                                <Download className="size-4" />
                                {t('common.export_csv')}
                            </a>
                        </DropdownMenuItem>
                    ) : null}
                    {formats.includes('xls') || formats.includes('xlsx') || formats.includes('1') ? (
                        <DropdownMenuItem asChild>
                            <a
                                href={buildReportExportUrl(
                                    routeUrl,
                                    filters,
                                    formats.includes('xls') ? 'xls' : formats.includes('xlsx') ? 'xlsx' : '1',
                                )}
                                download
                                className="cursor-pointer"
                            >
                                <FileSpreadsheet className="size-4" />
                                {t('common.export_excel')}
                            </a>
                        </DropdownMenuItem>
                    ) : null}
                </DropdownMenuContent>
            </DropdownMenu>
        );
    }

    // mode === 'link' (Pushsale default)
    return (
        <a className={`ps-btn ps-btn-primary ${className}`.trim()} href={buildReportExportUrl(routeUrl, filters, exportValue)}>
            {showIcon ? <i className="fa fa-file-excel-o" aria-hidden="true" /> : null}
            <span>{text}</span>
        </a>
    );
}
