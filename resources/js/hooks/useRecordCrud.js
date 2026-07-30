import { router, useForm } from '@inertiajs/react';
import { useState } from 'react';

import { useConfirm } from '@/hooks/use-confirm';
import { formErrorText } from '@/pages/Admin/Accounting/expenseShared';

/**
 * Shared record CRUD for simple admin catalogs (DRY #12).
 * Posts `{ payload: data }` to `${routeUrl}/records`.
 */
export function useRecordCrud({
    routeUrl,
    emptyForm = {},
    transformPayload = (data) => data,
    deleteConfirm = (row) => `Xóa "${row?.name ?? row?.id ?? ''}"?`,
}) {
    const { ask } = useConfirm();
    const [open, setOpen] = useState(false);
    const [editingId, setEditingId] = useState(null);
    const form = useForm(emptyForm);
    const errorText = formErrorText(form.errors);

    const openCreate = () => {
        setEditingId(null);
        form.setData(emptyForm);
        form.clearErrors();
        setOpen(true);
    };

    const openEdit = (row, nextData) => {
        setEditingId(row._record_id ?? row.id ?? null);
        form.setData(nextData ?? { ...emptyForm, ...(row._form ?? {}), name: row._form?.name ?? row.name ?? '' });
        form.clearErrors();
        setOpen(true);
    };

    const close = () => setOpen(false);

    const save = (event) => {
        event?.preventDefault?.();
        const options = { preserveScroll: true, onSuccess: () => setOpen(false) };
        const request = form.transform((data) => ({ payload: transformPayload(data) }));
        if (editingId) {
            request.put(`${routeUrl}/records/${editingId}`, options);
            return;
        }
        request.post(`${routeUrl}/records`, options);
    };

    const destroy = async (row) => {
        const id = row?._record_id ?? row?.id;
        if (!id) return;
        const ok = await ask({
            description: typeof deleteConfirm === 'function' ? deleteConfirm(row) : deleteConfirm,
            confirmLabel: 'Xóa',
            variant: 'destructive',
        });
        if (!ok) return;
        router.delete(`${routeUrl}/records/${id}`, { preserveScroll: true });
    };

    return {
        open,
        setOpen,
        close,
        editingId,
        form,
        errorText,
        openCreate,
        openEdit,
        save,
        destroy,
        isEditing: Boolean(editingId),
    };
}
