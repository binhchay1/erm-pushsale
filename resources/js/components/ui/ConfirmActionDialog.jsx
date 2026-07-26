import { PushsaleDialog } from '@/components/ui/pushsale-dialog';
import { useT } from '@/providers/I18nProvider';

export function ConfirmActionDialog({
    open,
    title,
    description,
    confirmLabel,
    cancelLabel,
    tone = 'danger',
    processing = false,
    onConfirm,
    onCancel,
}) {
    const t = useT();
    const isDanger = tone === 'danger';

    return (
        <PushsaleDialog
            open={Boolean(open)}
            onOpenChange={(nextOpen) => !nextOpen && onCancel?.()}
            title={title || t('confirm_dialog.title')}
            width="520px"
            className="ps-confirm-dialog"
            bodyClassName="ps-confirm-body"
            footer={(
                <>
                    <button type="button" className="btn btn-default btn-sm" disabled={processing} onClick={onCancel}>
                        {cancelLabel || t('confirm_dialog.cancel_label')}
                    </button>
                    <button type="button" className={`btn btn-sm ${isDanger ? 'btn-danger' : 'btn-primary'}`} disabled={processing} onClick={onConfirm}>
                        <i className={`fa ${processing ? 'fa-spinner fa-spin' : isDanger ? 'fa-trash' : 'fa-check'}`} /> {confirmLabel || (isDanger ? t('confirm_dialog.confirm_delete') : t('common.confirm'))}
                    </button>
                </>
            )}
        >
            <div className={`ps-confirm-action ps-confirm-${tone}`}>
                <span className="ps-confirm-icon"><i className={`fa ${isDanger ? 'fa-exclamation-triangle' : 'fa-question-circle'}`} /></span>
                <div className="ps-confirm-copy">{description || t('confirm_dialog.delete_desc')}</div>
            </div>
        </PushsaleDialog>
    );
}
