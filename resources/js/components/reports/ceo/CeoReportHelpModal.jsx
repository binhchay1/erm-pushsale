import { X } from 'lucide-react';

import { useT } from '@/providers/I18nProvider';

export function CeoReportHelpModal({ open, onClose }) {
    const t = useT();

    if (!open) {
        return null;
    }

    const items = t('reports.ceo_report.legend_items', { returnObjects: true });

    return (
        <div className="modal-note-overlay" role="dialog" aria-modal="true" onClick={onClose}>
            <div className="modal-note-panel" onClick={(e) => e.stopPropagation()}>
                <div className="modal-note-header">
                    <h4 className="modal-note-title">{t('reports.ceo_report.legend')}</h4>
                    <button type="button" className="btn-icon" onClick={onClose} aria-label={t('common.close')}>
                        <X className="size-4" />
                    </button>
                </div>
                <div className="modal-note-body">
                    <table className="table table-bordered table-striped">
                        <thead>
                            <tr>
                                <th className="text-center" style={{ width: '30%' }}>
                                    {t('reports.ceo_report.legend_col_name')}
                                </th>
                                <th className="text-center" style={{ width: '70%' }}>
                                    {t('reports.ceo_report.legend_col_desc')}
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {Array.isArray(items) &&
                                items.map((row) => (
                                    <tr key={row.name}>
                                        <td className="text-left font-weight-bold">{row.name}</td>
                                        <td className="text-left">{row.desc}</td>
                                    </tr>
                                ))}
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    );
}
