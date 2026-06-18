import { Download } from 'lucide-react';

import { Button } from '@/components/ui/button';
import { useT } from '@/providers/I18nProvider';

function buildExportUrl(routeUrl, filters) {
    const params = new URLSearchParams();

    Object.entries(filters ?? {}).forEach(([key, value]) => {
        if (value === null || value === undefined || value === '') {
            return;
        }
        params.set(key, String(value));
    });

    params.set('export', 'csv');

    const query = params.toString();

    return query ? `${routeUrl}?${query}` : `${routeUrl}?export=csv`;
}

export function ReportExportButton({ routeUrl, filters, label }) {
    const t = useT();

    return (
        <Button variant="outline" size="sm" asChild>
            <a href={buildExportUrl(routeUrl, filters)} download>
                <Download className="size-4" />
                {label ?? t('common.export_csv')}
            </a>
        </Button>
    );
}
