import { router, usePage } from '@inertiajs/react';
import { FileUp, Loader2, UserPlus } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { toast } from 'sonner';

import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
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

const EMPTY_FORM = { name: '', phone: '', address: '', product_id: '', quantity: 1, note: '', utm_source: '' };

function AllocationPicker({ mode, setMode, salesUsers, selected, toggle }) {
    const t = useT();

    return (
        <div className="space-y-2 rounded-lg border bg-muted/20 p-3 text-xs">
            <p className="font-medium text-foreground">{t('pages.leads.alloc_title')}</p>
            <label className="flex items-center gap-2">
                <input type="radio" checked={mode === 'default'} onChange={() => setMode('default')} />
                <span>{t('pages.leads.alloc_default')}</span>
            </label>
            <label className="flex items-center gap-2">
                <input type="radio" checked={mode === 'manual'} onChange={() => setMode('manual')} />
                <span>{t('pages.leads.alloc_manual')}</span>
            </label>
            {mode === 'manual' && (
                <div className="mt-1 space-y-1 rounded-md border bg-background p-2">
                    <p className="text-[11px] text-muted-foreground">{t('pages.leads.alloc_pick_sales')}</p>
                    <div className="max-h-40 space-y-1 overflow-auto">
                        {salesUsers.length ? (
                            salesUsers.map((u) => (
                                <label key={u.id} className="flex items-center gap-2">
                                    <input
                                        type="checkbox"
                                        checked={selected.includes(String(u.id))}
                                        onChange={() => toggle(String(u.id))}
                                    />
                                    <span>{u.name}</span>
                                </label>
                            ))
                        ) : (
                            <p className="text-muted-foreground">{t('pages.leads.alloc_no_sales')}</p>
                        )}
                    </div>
                </div>
            )}
        </div>
    );
}

export function ManualLeadDialogs({ manualUrl, importUrl, products = [], importFields = [], salesUsers = [] }) {
    const t = useT();
    const { props } = usePage();
    const importResult = props?.flash?.importResult ?? null;

    const [addOpen, setAddOpen] = useState(false);
    const [importOpen, setImportOpen] = useState(false);
    const [form, setForm] = useState(EMPTY_FORM);
    const [saving, setSaving] = useState(false);
    const [importing, setImporting] = useState(false);
    const [file, setFile] = useState(null);
    const fileRef = useRef(null);
    const lastShownResult = useRef(null);

    const [addMode, setAddMode] = useState('default');
    const [addSales, setAddSales] = useState([]);
    const [importMode, setImportMode] = useState('default');
    const [importSales, setImportSales] = useState([]);

    const toggleIn = (setter) => (id) =>
        setter((prev) => (prev.includes(id) ? prev.filter((x) => x !== id) : [...prev, id]));

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

    const setField = (key, value) => setForm((prev) => ({ ...prev, [key]: value }));

    const submitSingle = () => {
        if (!form.phone.trim()) {
            toast.error(t('pages.leads.phone_required'));
            return;
        }
        if (addMode === 'manual' && addSales.length === 0) {
            toast.error(t('pages.leads.alloc_need_sale'));
            return;
        }
        setSaving(true);
        router.post(
            manualUrl,
            { ...form, allocation_mode: addMode, sale_user_ids: addMode === 'manual' ? addSales : [] },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setAddOpen(false);
                    setForm(EMPTY_FORM);
                    setAddMode('default');
                    setAddSales([]);
                },
                onError: (errors) => toast.error(errors.phone ?? errors.product_id ?? errors.sale_user_ids ?? t('pages.leads.manual_failed')),
                onFinish: () => setSaving(false),
            },
        );
    };

    const submitImport = () => {
        if (!file) {
            toast.error(t('pages.leads.import_pick_file'));
            return;
        }
        if (importMode === 'manual' && importSales.length === 0) {
            toast.error(t('pages.leads.alloc_need_sale'));
            return;
        }
        setImporting(true);
        router.post(
            importUrl,
            { file, allocation_mode: importMode, sale_user_ids: importMode === 'manual' ? importSales : [] },
            {
                forceFormData: true,
                preserveScroll: true,
                onSuccess: () => {
                    setImportOpen(false);
                    setFile(null);
                    if (fileRef.current) fileRef.current.value = '';
                },
                onError: (errors) => toast.error(errors.file ?? errors.sale_user_ids ?? t('pages.leads.import_failed')),
                onFinish: () => setImporting(false),
            },
        );
    };

    return (
        <>
            <div className="flex flex-wrap gap-2">
                <Button size="sm" variant="outline" onClick={() => setAddOpen(true)}>
                    <UserPlus className="size-4" />
                    {t('pages.leads.manual_add')}
                </Button>
                <Button size="sm" variant="outline" onClick={() => setImportOpen(true)}>
                    <FileUp className="size-4" />
                    {t('pages.leads.import_csv')}
                </Button>
            </div>

            {/* Kết quả import gần nhất */}
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

            {/* Dialog: thêm 1 lead lẻ */}
            <Dialog open={addOpen} onOpenChange={setAddOpen}>
                <DialogContent className="max-w-lg">
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
                                {t('pages.leads.field_phone')} <span className="text-rose-500">*</span>
                            </span>
                            <input className="input-soft h-9 w-full px-2" value={form.phone} onChange={(e) => setField('phone', e.target.value)} />
                        </label>
                        <label className="space-y-1 text-xs sm:col-span-2">
                            <span className="font-medium text-muted-foreground">{t('pages.leads.field_address')}</span>
                            <input className="input-soft h-9 w-full px-2" value={form.address} onChange={(e) => setField('address', e.target.value)} />
                        </label>
                        <label className="space-y-1 text-xs">
                            <span className="font-medium text-muted-foreground">{t('pages.leads.field_product')}</span>
                            <select className="input-soft h-9 w-full px-2" value={form.product_id} onChange={(e) => setField('product_id', e.target.value)}>
                                <option value="">{t('pages.leads.select_product_placeholder')}</option>
                                {products.map((p) => (
                                    <option key={p.id} value={p.id}>
                                        {p.name}
                                    </option>
                                ))}
                            </select>
                        </label>
                        <label className="space-y-1 text-xs">
                            <span className="font-medium text-muted-foreground">{t('pages.leads.field_quantity')}</span>
                            <input type="number" min={1} className="input-soft h-9 w-full px-2" value={form.quantity} onChange={(e) => setField('quantity', e.target.value)} />
                        </label>
                        <label className="space-y-1 text-xs sm:col-span-2">
                            <span className="font-medium text-muted-foreground">{t('pages.leads.field_source')}</span>
                            <input className="input-soft h-9 w-full px-2" value={form.utm_source} onChange={(e) => setField('utm_source', e.target.value)} />
                        </label>
                        <label className="space-y-1 text-xs sm:col-span-2">
                            <span className="font-medium text-muted-foreground">{t('pages.leads.field_note')}</span>
                            <textarea className="input-soft w-full px-2 py-1.5" rows={2} value={form.note} onChange={(e) => setField('note', e.target.value)} />
                        </label>
                        <div className="sm:col-span-2">
                            <AllocationPicker
                                mode={addMode}
                                setMode={setAddMode}
                                salesUsers={salesUsers}
                                selected={addSales}
                                toggle={toggleIn(setAddSales)}
                            />
                        </div>
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

            {/* Dialog: import CSV */}
            <Dialog open={importOpen} onOpenChange={setImportOpen}>
                <DialogContent className="max-w-lg">
                    <DialogHeader>
                        <DialogTitle>{t('pages.leads.import_csv_title')}</DialogTitle>
                        <DialogDescription>{t('pages.leads.import_csv_desc')}</DialogDescription>
                    </DialogHeader>

                    <div className="space-y-3">
                        <input
                            ref={fileRef}
                            type="file"
                            accept=".csv,.txt,.xls,.xlsx,text/csv,application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"
                            className="input-soft w-full px-2 py-1.5 text-sm"
                            onChange={(e) => setFile(e.target.files?.[0] ?? null)}
                        />
                        <div className="rounded-lg border bg-muted/30 p-3 text-xs text-muted-foreground">
                            <p className="mb-1 font-medium text-foreground">{t('pages.leads.import_columns_hint')}</p>
                            <p>{t('pages.leads.import_columns_examples')}</p>
                        </div>
                        <AllocationPicker
                            mode={importMode}
                            setMode={setImportMode}
                            salesUsers={salesUsers}
                            selected={importSales}
                            toggle={toggleIn(setImportSales)}
                        />
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
