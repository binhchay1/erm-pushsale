import { Plus, Trash2 } from 'lucide-react';
import { useMemo } from 'react';

import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useT } from '@/providers/I18nProvider';

const EMPTY_ROW = { productId: null, productName: '', itemType: 'product', quantity: 1, unitPrice: 0 };

/**
 * Chọn nhiều sản phẩm / gói combo từ danh mục (giống tab cập nhật đơn).
 */
export function ManualLeadProductItems({ productOptions = [], items = [], onChange, error }) {
    const t = useT();

    const catalog = useMemo(() => {
        const products = [];
        const combos = [];
        (productOptions ?? []).forEach((p) => {
            (p.type === 'combo' ? combos : products).push(p);
        });
        return { products, combos };
    }, [productOptions]);

    const optionsForType = (itemType) => (itemType === 'combo' ? catalog.combos : catalog.products);

    const updateItem = (index, patch) => {
        onChange(items.map((it, i) => (i === index ? { ...it, ...patch } : it)));
    };

    const addItem = (itemType) => {
        onChange([...items, { ...EMPTY_ROW, itemType }]);
    };

    const removeItem = (index) => {
        onChange(items.filter((_, i) => i !== index));
    };

    const selectCatalogItem = (index, id) => {
        const current = items[index];
        const found = optionsForType(current?.itemType).find((p) => String(p.id) === String(id));
        if (!found) {
            updateItem(index, { productId: null, productName: '', unitPrice: 0 });
            return;
        }
        updateItem(index, {
            productId: found.id,
            productName: found.sku ? `${found.name} (${found.sku})` : found.name,
            unitPrice: Number(found.unit_price) || 0,
        });
    };

    return (
        <div className="space-y-2">
            <div className="flex items-center justify-between gap-2">
                <span className="text-xs font-medium text-muted-foreground">{t('pages.leads.field_products')}</span>
                <div className="flex gap-1.5">
                    <Button type="button" size="sm" variant="outline" className="h-7 gap-1 text-xs" onClick={() => addItem('product')}>
                        <Plus className="size-3" />
                        {t('operations.order_edit.add_item')}
                    </Button>
                    <Button type="button" size="sm" variant="outline" className="h-7 gap-1 text-xs" onClick={() => addItem('combo')}>
                        <Plus className="size-3" />
                        {t('operations.order_edit.add_combo')}
                    </Button>
                </div>
            </div>

            <div className="overflow-x-auto rounded-lg border border-border/70">
                <table className="w-full text-sm">
                    <thead className="bg-muted/60 text-xs text-muted-foreground">
                        <tr>
                            <th className="px-2 py-2 text-left font-medium">{t('operations.order_edit.col_product')}</th>
                            <th className="px-2 py-2 text-right font-medium">{t('operations.order_edit.col_unit_price')}</th>
                            <th className="px-2 py-2 text-center font-medium">{t('operations.order_edit.col_qty')}</th>
                            <th className="w-8 px-1 py-2" />
                        </tr>
                    </thead>
                    <tbody>
                        {items.length === 0 ? (
                            <tr>
                                <td colSpan={4} className="px-2 py-3 text-center text-xs text-muted-foreground">
                                    {t('operations.order_edit.empty_items')}
                                </td>
                            </tr>
                        ) : (
                            items.map((it, index) => (
                                <tr key={index} className="border-t border-border/60 align-top">
                                    <td className="px-2 py-1.5">
                                        <select
                                            className="input-soft h-8 w-full px-2 text-sm"
                                            value={it.productId ?? ''}
                                            onChange={(e) => selectCatalogItem(index, e.target.value)}
                                        >
                                            <option value="">
                                                {it.itemType === 'combo'
                                                    ? t('operations.order_edit.pick_combo_placeholder')
                                                    : t('operations.order_edit.pick_item_placeholder')}
                                            </option>
                                            {optionsForType(it.itemType).map((p) => (
                                                <option key={p.id} value={p.id}>
                                                    {p.name}
                                                    {p.sku ? ` (${p.sku})` : ''}
                                                </option>
                                            ))}
                                        </select>
                                        <span
                                            className={`mt-1 inline-flex rounded px-1.5 py-0.5 text-[10px] font-semibold ${
                                                it.itemType === 'combo'
                                                    ? 'bg-violet-100 text-violet-700 dark:bg-violet-950/50 dark:text-violet-300'
                                                    : 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300'
                                            }`}
                                        >
                                            {t(`operations.order_edit.type_${it.itemType}`)}
                                        </span>
                                    </td>
                                    <td className="px-2 py-1.5">
                                        <Input
                                            className="h-8 text-right"
                                            type="number"
                                            min={0}
                                            value={it.unitPrice}
                                            onChange={(e) => updateItem(index, { unitPrice: e.target.value })}
                                        />
                                    </td>
                                    <td className="px-2 py-1.5">
                                        <Input
                                            className="h-8 text-center"
                                            type="number"
                                            min={1}
                                            value={it.quantity}
                                            onChange={(e) => updateItem(index, { quantity: e.target.value })}
                                        />
                                    </td>
                                    <td className="px-1 py-1.5">
                                        <Button type="button" size="icon" variant="ghost" className="size-7" onClick={() => removeItem(index)}>
                                            <Trash2 className="size-3.5 text-destructive" />
                                        </Button>
                                    </td>
                                </tr>
                            ))
                        )}
                    </tbody>
                </table>
            </div>
            {error ? <p className="text-xs text-destructive">{error}</p> : null}
        </div>
    );
}
