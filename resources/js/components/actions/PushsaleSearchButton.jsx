import { translateReportText } from '@/lib/reportI18n';
import { useT } from '@/providers/I18nProvider';

/**
 * Nút "Tìm kiếm" dùng chung trên header / toolbar Pushsale.
 * Style: `.ps-btn.ps-btn-primary` (page-header-contract + pushsale.css).
 */
export function PushsaleSearchButton({ onClick, label, type = 'button', form, className = '', disabled = false }) {
    const t = useT();
    const text = label ? translateReportText(t, label, label) : t('reports.pushsale.search');

    return (
        <button
            type={type}
            form={form}
            className={`ps-btn ps-btn-primary ${className}`.trim()}
            onClick={onClick}
            disabled={disabled}
        >
            <i className="fa fa-search" aria-hidden="true" />
            <span>{text}</span>
        </button>
    );
}

export default PushsaleSearchButton;
