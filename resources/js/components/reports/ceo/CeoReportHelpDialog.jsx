import { PushsaleDialog } from '@/components/ui/pushsale-dialog';
import { useT } from '@/providers/I18nProvider';

export function CeoReportHelpDialog({ open, onClose }) {
    const t = useT();
    const items = t('reports.ceo_report.legend_items', { returnObjects: true });

    return (
        <PushsaleDialog
            open={Boolean(open)}
            onOpenChange={(nextOpen) => !nextOpen && onClose?.()}
            title={t('reports.ceo_report.legend')}
            width="820px"
            className="ceo-report-help-dialog"
            bodyClassName="ceo-report-help-dialog-body"
        >
            <table className="table table-bordered table-striped">
                <thead>
                    <tr>
                        <th className="text-center" style={{ width: '30%' }}>{t('reports.ceo_report.legend_col_name')}</th>
                        <th className="text-center" style={{ width: '70%' }}>{t('reports.ceo_report.legend_col_desc')}</th>
                    </tr>
                </thead>
                <tbody>
                    {Array.isArray(items) && items.map((row) => (
                        <tr key={row.name}>
                            <td className="text-left font-weight-bold">{row.name}</td>
                            <td className="text-left">{row.desc}</td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </PushsaleDialog>
    );
}
