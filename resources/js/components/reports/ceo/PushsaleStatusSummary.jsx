import { useT } from '@/providers/I18nProvider';

const CARDS = [
    { key: 'waitingDelivery', labelKey: 'waiting', headerClass: 'header_chogiao' },
    { key: 'cancelWaybill', labelKey: 'cancel_waybill', headerClass: 'header_chogiao' },
    { key: 'delivering', labelKey: 'delivering', headerClass: 'header_danggiao' },
    { key: 'delivered', labelKey: 'delivered', headerClass: 'header_dagiao' },
    { key: 'paid', labelKey: 'paid', headerClass: 'header_thutien' },
    { key: 'returned', labelKey: 'returned', headerClass: 'header_hoandon', showRate: true },
];

export function PushsaleStatusSummary({ statusSummary = {}, collapsed = false }) {
    const t = useT();
    const returned = Number(statusSummary.returned) || 0;
    const delivered = Number(statusSummary.delivered) || 0;
    const returnRate =
        delivered + returned > 0 ? Math.round((returned / (delivered + returned)) * 1000) / 10 : 0;

    if (collapsed) {
        return (
            <table className="table table-bordered table-multi-select tabledata sr-only-export" id="tableReportGiaoVan">
                <tbody>
                    {CARDS.map(({ key, labelKey, showRate }) => (
                        <tr key={key}>
                            <th className="text-center">{t(`reports.status_summary.${labelKey}`)}</th>
                            <td className="text-center">
                                {statusSummary[key] ?? 0}
                                {showRate ? ` (${returnRate})` : ''}
                            </td>
                        </tr>
                    ))}
                </tbody>
            </table>
        );
    }

    return (
        <>
        <div className="row-cards mt15">
            {CARDS.map(({ key, labelKey, headerClass, showRate }) => (
                <div key={key} className="col-card">
                    <table className="table table-bordered table-multi-select tabledata">
                        <tbody>
                            <tr>
                                <th className={`text-center ${headerClass}`}>
                                    {t(`reports.status_summary.${labelKey}`)}
                                </th>
                            </tr>
                            <tr>
                                <td className="text-center">
                                    {statusSummary[key] ?? 0}
                                    {showRate ? ` (${returnRate})` : ''}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            ))}
        </div>
        <table className="table table-bordered table-multi-select tabledata sr-only-export" id="tableReportGiaoVan">
            <tbody>
                {CARDS.map(({ key, labelKey, showRate }) => (
                    <tr key={`export-${key}`}>
                        <th className="text-center">{t(`reports.status_summary.${labelKey}`)}</th>
                        <td className="text-center">
                            {statusSummary[key] ?? 0}
                            {showRate ? ` (${returnRate})` : ''}
                        </td>
                    </tr>
                ))}
            </tbody>
        </table>
        </>
    );
}
