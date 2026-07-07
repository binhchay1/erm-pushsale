import { router, usePage } from '@inertiajs/react';
import { Download, FileUp, Loader2, UserPlus } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { toast } from 'sonner';

import { ManualLeadProductItems } from '@/components/leads/ManualLeadProductItems';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { FieldError, RequiredMark } from '@/components/ui/field-error';
import { validate } from '@/lib/validate';
import { useT } from '@/providers/I18nProvider';

const FIELD_LABELS = {
    name: 'field_name',
    phone: 'field_phone',
    address: 'field_address',
    product: 'field_product',
    quantity: 'field_quantity',
    unit_price: 'field_unit_price',
    discount: 'field_discount',
    note: 'field_note',
    utm_source: 'field_source',
    utm_campaign: 'field_campaign',
};

const EMPTY_FORM = {
    name: '',
    phone: '',
    address: '',
    note: '',
    marketing_source_id: '',
    items: [],
};

export function ManualLeadDialogs({
    manualUrl,
    importUrl,
    templateUrl,
    productOptions = [],
    sources = [],
    importFields = [],
    canManageTemplate = false,
    companyTemplate = null,
    templateUploadUrl = '',
    templateRemoveUrl = '',
}) {
    const t = useT();
    const { props } = usePage();
    const importResult = props?.flash?.importResult ?? null;

    const [addOpen, setAddOpen] = useState(false);
    const [importOpen, setImportOpen] = useState(false);
    const [form, setForm] = useState(EMPTY_FORM);
    const [saving, setSaving] = useState(false);
    const [importing, setImporting] = useState(false);
    const [file, setFile] = useState(null);
    const [errors, setErrors] = useState({});
    const [importErrors, setImportErrors] = useState({});
    const [templateFile, setTemplateFile] = useState(null);
    const [uploadingTemplate, setUploadingTemplate] = useState(false);
    const templateFileRef = useRef(null);
    const fileRef = useRef(null);
    const lastShownResult = useRef(null);

    useEffect(() => {
        if (!importResult || importResult === lastShownResult.current) return;
        lastShownResult.current = importResult;
        if (importResult.ok) {
            toast.success(
                t('pages.leads.import_done', {
                    created: importResult.created ?? 0,
                    duplicate: importResult.duplicate ?? 0,
                    failed: importResult.failed ?? 0,
                }),
            );
        } else {
            toast.error(importResult.message ?? t('pages.leads.import_failed'));
        }
    }, [importResult, t]);

    const setField = (key, value) => {
        setForm((prev) => ({ ...prev, [key]: value }));
        setErrors((prev) => (prev[key] ? { ...prev, [key]: undefined } : prev));
    };

    const submitSingle = () => {
        const clientErrors = validate(form, {
            phone: [
                { required: true, label: t('pages.leads.field_phone') },
                { phone: true },
            ],
        });

        const validItems = (form.items ?? []).filter((it) => it.productId);

        if (Object.keys(clientErrors).length > 0) {
            setErrors(clientErrors);
            toast.error(t('common.validation.fix_errors'));
            return;
        }

        setErrors({});
        setSaving(true);
        router.post(
            manualUrl,
            {
                name: form.name,
                phone: form.phone,
                address: form.address,
                note: form.note,
                marketing_source_id: form.marketing_source_id || null,
                items: validItems.map((it) => ({
                    product_id: Number(it.productId),
                    item_type: it.itemType,
                    quantity: Number(it.quantity) || 1,
                    unit_price: Number(it.unitPrice) || 0,
                })),
                allocation_mode: 'default',
                sale_user_ids: [],
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setAddOpen(false);
                    setForm(EMPTY_FORM);
                    setErrors({});
                },
                onError: (serverErrors) => {
                    setErrors(serverErrors);
                    toast.error(serverErrors.phone ?? serverErrors.items ?? t('pages.leads.manual_failed'));
                },
                onFinish: () => setSaving(false),
            },
        );
    };

    const submitImport = () => {
        const clientErrors = {};
        if (!file) {
            clientErrors.file = t('pages.leads.import_pick_file');
        }

        if (Object.keys(clientErrors).length > 0) {
            setImportErrors(clientErrors);
            toast.error(t('common.validation.fix_errors'));
            return;
        }

        setImportErrors({});
        setImporting(true);
        router.post(
            importUrl,
            { file, allocation_mode: 'default', sale_user_ids: [] },
            {
                forceFormData: true,
                preserveScroll: true,
                onSuccess: () => {
                    setImportOpen(false);
                    setFile(null);
                    if (fileRef.current) fileRef.current.value = '';
                    setImportErrors({});
                },
                onError: (serverErrors) => {
                    setImportErrors(serverErrors);
                    toast.error(serverErrors.file ?? t('pages.leads.import_failed'));
                },
                onFinish: () => setImporting(false),
            },
        );
    };

    const uploadTemplate = () => {
        if (!templateFile) {
            toast.error(t('pages.leads.import_pick_file'));
            return;
        }
        setUploadingTemplate(true);
        router.post(
            templateUploadUrl,
            { lead_import_template: templateFile },
            {
                forceFormData: true,
                preserveScroll: true,
                onSuccess: () => {
                    setTemplateFile(null);
                    if (templateFileRef.current) templateFileRef.current.value = '';
                    toast.success(t('pages.leads.template_uploaded'));
                },
                onError: () => toast.error(t('pages.leads.template_upload_failed')),
                onFinish: () => setUploadingTemplate(false),
            },
        );
    };

    const removeTemplate = () => {
        setUploadingTemplate(true);
        router.delete(templateRemoveUrl, {
            preserveScroll: true,
            onSuccess: () => toast.success(t('pages.leads.template_removed')),
            onError: () => toast.error(t('pages.leads.template_upload_failed')),
            onFinish: () => setUploadingTemplate(false),
        });
    };

    return (
        <>
            <div className="flex flex-wrap gap-2">
                <Button size="sm" variant="outline" onClick={() => { setErrors({}); setAddOpen(true); }}>
                    <UserPlus className="size-4" />
                    {t('pages.leads.manual_add')}
                </Button>
                <Button size="sm" variant="outline" onClick={() => { setImportErrors({}); setImportOpen(true); }}>
                    <FileUp className="size-4" />
                    {t('pages.leads.import_csv')}
                </Button>
            </div>

            {importResult && importResult.ok && (
                <div className="mt-3 space-y-2 rounded-xl border bg-card p-4 text-xs">
                    <p className="text-sm font-semibold">{t('pages.leads.import_result_title')}</p>
                    <div className="flex flex-wrap gap-4">
                        <span className="text-emerald-600 dark:text-emerald-400">
                            {t('pages.leads.import_created')}: <b>{importResult.created ?? 0}</b>
                        </span>
                        <span className="text-amber-600 dark:text-amber-400">
                            {t('pages.leads.import_duplicate')}: <b>{importResult.duplicate ?? 0}</b>
                        </span>
                        <span className="text-rose-600 dark:text-rose-400">
                            {t('pages.leads.import_failed_count')}: <b>{importResult.failed ?? 0}</b>
                        </span>
                        <span className="text-muted-foreground">
                            {t('pages.leads.import_skipped')}: <b>{importResult.skipped ?? 0}</b>
                        </span>
                    </div>
                    {importResult.mapping?.length > 0 && (
                        <div className="flex flex-wrap gap-1.5 pt-1">
                            {importResult.mapping.map((m) => (
                                <span key={m.header} className="rounded bg-muted px-2 py-0.5">
                                    {m.header} → <b>{t(`pages.leads.${FIELD_LABELS[m.field] ?? 'col_note'}`)}</b>
                                </span>
                            ))}
                        </div>
                    )}
                    {importResult.unmatched?.length > 0 && (
                        <p className="text-muted-foreground">
                            {t('pages.leads.import_unmatched')}: {importResult.unmatched.join(', ')}
                        </p>
                    )}
                    {importResult.errors?.length > 0 && (
                        <ul className="list-inside list-disc text-rose-600 dark:text-rose-400">
                            {importResult.errors.slice(0, 8).map((e) => (
                                <li key={e.row}>
                                    {t('pages.leads.import_row')} {e.row}: {e.message}
                                </li>
                            ))}
                        </ul>
                    )}
                </div>
            )}

            <Dialog open={addOpen} onOpenChange={setAddOpen}>
                <DialogContent className="max-w-2xl max-h-[90vh] overflow-y-auto">
                    <DialogHeader>
                        <DialogTitle>{t('pages.leads.manual_add_title')}</DialogTitle>
                        <DialogDescription>{t('pages.leads.manual_add_desc')}</DialogDescription>
                    </DialogHeader>

                    <div className="grid gap-3 sm:grid-cols-2">
                        <label className="space-y-1 text-xs">
                            <span className="font-medium text-muted-foreground">{t('pages.leads.field_name')}</span>
                            <input className="input-soft h-9 w-full px-2" value={form.name} onChange={(e) => setField('name', e.target.value)} />
                        </label>
                        <label className="space-y-1 text-xs">
                            <span className="font-medium text-muted-foreground">
                                {t('pages.leads.field_phone')}
                                <RequiredMark />
                            </span>
                            <input
                                className="input-soft h-9 w-full px-2"
                                value={form.phone}
                                aria-invalid={!!errors.phone}
                                onChange={(e) => setField('phone', e.target.value)}
                            />
                            <FieldError message={errors.phone} />
                        </label>
                        <label className="space-y-1 text-xs sm:col-span-2">
                            <span className="font-medium text-muted-foreground">{t('pages.leads.field_address')}</span>
                            <input className="input-soft h-9 w-full px-2" value={form.address} onChange={(e) => setField('address', e.target.value)} />
                        </label>
                        <label className="space-y-1 text-xs sm:col-span-2">
                            <span className="font-medium text-muted-foreground">{t('pages.leads.field_source')}</span>
                            <select
                                className="input-soft h-9 w-full px-2"
                                value={form.marketing_source_id}
                                onChange={(e) => setField('marketing_source_id', e.target.value)}
                            >
                                <option value="">{t('pages.leads.select_source_placeholder')}</option>
                                {sources.map((s) => (
                                    <option key={s.id} value={s.id}>
                                        {s.name}
                                    </option>
                                ))}
                            </select>
                        </label>
                        <div className="sm:col-span-2">
                            <ManualLeadProductItems
                                productOptions={productOptions}
                                items={form.items}
                                onChange={(items) => setField('items', items)}
                                error={errors.items}
                            />
                        </div>
                        <label className="space-y-1 text-xs sm:col-span-2">
                            <span className="font-medium text-muted-foreground">{t('pages.leads.field_note')}</span>
                            <textarea className="input-soft w-full px-2 py-1.5" rows={2} value={form.note} onChange={(e) => setField('note', e.target.value)} />
                        </label>
                    </div>

                    <DialogFooter>
                        <Button variant="outline" onClick={() => setAddOpen(false)}>
                            {t('common.cancel')}
                        </Button>
                        <Button onClick={submitSingle} disabled={saving}>
                            {saving && <Loader2 className="size-4 animate-spin" />}
                            {t('common.save')}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <Dialog open={importOpen} onOpenChange={setImportOpen}>
                <DialogContent className="max-w-lg">
                    <DialogHeader>
                        <DialogTitle>{t('pages.leads.import_csv_title')}</DialogTitle>
                        <DialogDescription>{t('pages.leads.import_csv_desc')}</DialogDescription>
                    </DialogHeader>

                    <div className="space-y-3">
                        <div className="flex flex-wrap items-center gap-2">
                            <a
                                href={templateUrl}
                                className="inline-flex items-center gap-1.5 rounded-md border bg-background px-3 py-1.5 text-sm font-medium hover:bg-muted"
                            >
                                <Download className="size-4" />
                                {t('pages.leads.template_download')}
                            </a>
                            <span className="text-xs text-muted-foreground">{t('pages.leads.template_download_hint')}</span>
                        </div>

                        <div>
                            <input
                                ref={fileRef}
                                type="file"
                                accept=".csv,.txt,.xls,.xlsx,text/csv,application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"
                                className="input-soft w-full px-2 py-1.5 text-sm"
                                aria-invalid={!!importErrors.file}
                                onChange={(e) => {
                                    setFile(e.target.files?.[0] ?? null);
                                    setImportErrors((prev) => (prev.file ? { ...prev, file: undefined } : prev));
                                }}
                            />
                            <FieldError message={importErrors.file} className="mt-1" />
                        </div>

                        <div className="rounded-lg border bg-muted/30 p-3 text-xs text-muted-foreground">
                            <p className="mb-1 font-medium text-foreground">{t('pages.leads.import_columns_hint')}</p>
                            <p>{t('pages.leads.import_columns_examples')}</p>
                        </div>

                        {canManageTemplate && (
                            <div className="space-y-2 rounded-lg border border-dashed bg-muted/10 p-3 text-xs">
                                <p className="font-medium text-foreground">{t('pages.leads.template_manage_title')}</p>
                                <p className="text-muted-foreground">{t('pages.leads.template_manage_desc')}</p>
                                {companyTemplate?.has && companyTemplate?.name ? (
                                    <p>
                                        {t('pages.platform.template_current')}:{' '}
                                        <span className="font-medium">{companyTemplate.name}</span>
                                    </p>
                                ) : (
                                    <p className="text-muted-foreground">{t('pages.platform.template_none')}</p>
                                )}
                                <input
                                    ref={templateFileRef}
                                    type="file"
                                    accept=".csv,.txt,.xls,.xlsx"
                                    className="input-soft w-full px-2 py-1.5 text-sm"
                                    onChange={(e) => setTemplateFile(e.target.files?.[0] ?? null)}
                                />
                                <div className="flex flex-wrap gap-2">
                                    <Button type="button" size="sm" variant="secondary" disabled={uploadingTemplate} onClick={uploadTemplate}>
                                        {uploadingTemplate && <Loader2 className="size-3.5 animate-spin" />}
                                        {t('pages.leads.template_upload_btn')}
                                    </Button>
                                    {companyTemplate?.has ? (
                                        <Button type="button" size="sm" variant="outline" disabled={uploadingTemplate} onClick={removeTemplate}>
                                            {t('pages.platform.template_remove')}
                                        </Button>
                                    ) : null}
                                </div>
                            </div>
                        )}
                    </div>

                    <DialogFooter>
                        <Button variant="outline" onClick={() => setImportOpen(false)}>
                            {t('common.cancel')}
                        </Button>
                        <Button onClick={submitImport} disabled={importing}>
                            {importing && <Loader2 className="size-4 animate-spin" />}
                            {t('pages.leads.import_submit')}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}
