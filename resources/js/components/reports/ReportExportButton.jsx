import { Download, FileSpreadsheet } from 'lucide-react';

import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useT } from '@/providers/I18nProvider';

function buildExportUrl(routeUrl, filters, format) {
    const params = new URLSearchParams();

    Object.entries(filters ?? {}).forEach(([key, value]) => {
        if (value === null || value === undefined || value === '') {
            return;
        }
        params.set(key, String(value));
    });

    params.set('export', format);

    const query = params.toString();

    return query ? `${routeUrl}?${query}` : `${routeUrl}?export=${format}`;
}

export function ReportExportButton({ routeUrl, filters, label }) {
    const t = useT();

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button variant="outline" size="sm">
                    <Download className="size-4" />
                    {label ?? t('common.export')}
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end">
                <DropdownMenuItem asChild>
                    <a href={buildExportUrl(routeUrl, filters, 'csv')} download className="cursor-pointer">
                        <Download className="size-4" />
                        {t('common.export_csv')}
                    </a>
                </DropdownMenuItem>
                <DropdownMenuItem asChild>
                    <a href={buildExportUrl(routeUrl, filters, 'xls')} download className="cursor-pointer">
                        <FileSpreadsheet className="size-4" />
                        {t('common.export_excel')}
                    </a>
                </DropdownMenuItem>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
