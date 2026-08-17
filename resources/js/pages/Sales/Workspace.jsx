import { Head, usePage } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import { toast } from 'sonner';

import AppLayout from '@/layouts/AppLayout';
import { useLandingUpsellHoldRefresh } from '@/hooks/useLandingUpsellHoldRefresh';
import { useOrderLockPresence } from '@/hooks/useOrderLockPresence';
import { useRealtimeReload } from '@/hooks/useRealtimeReload';
import {
    PushsaleCustomerMessagesDialog,
    PushsaleDataViewHistoryDialog,
} from '@/components/customers/pushsale/PushsaleCustomerDialogs';
import {
    BulkCloseDialog,
    DesiredDeliveryDialog,
    DuplicatePhoneOrdersDialog,
    SaleOperationHistoryDialog,
} from '@/components/operations/pushsale/SaleOperationDialogs';
import { SaleOrderDialog } from '@/components/operations/pushsale/SaleOrderDialog';
import { SaleWorkspaceFilters } from '@/components/operations/pushsale/SaleWorkspaceFilters';
import { SaleWorkspaceTabs } from '@/components/operations/pushsale/SaleWorkspaceTabs';
import { SaleWorkspaceTable } from '@/components/operations/pushsale/SaleWorkspaceTable';
import { useT } from '@/providers/I18nProvider';

export default function Workspace({
    filters = {},
    filterOptions = {},
    report = { rows: { data: [], meta: null }, statusTabs: [] },
    operationStatusOptions = [],
    carrierOptions = [],
    shippingServiceOptions = {},
    warehouseOptions = [],
    productOptions = [],
    sourceOptions = [],
    routeUrl = '/sales/workspace',
    actionBaseUrl = '/sales',
    manualUrl = '/sales/leads/manual',
    workspaceError = null,
}) {
    const t = useT();
    const rows = report?.rows?.data ?? [];
    const meta = report?.rows?.meta ?? null;
    const authUserId = usePage().props?.auth?.user?.id;
    const ordersBase = `${String(actionBaseUrl || '/sales').replace(/\/$/, '')}/orders`;
    const orderIds = useMemo(() => rows.map((row) => row.id), [rows]);
    const locks = useOrderLockPresence({ actionApiBase: ordersBase, orderIds });
    const [orderDialog, setOrderDialog] = useState({ open: false, order: null, closeIntent: false, operationResult: null });
    const [historyState, setHistoryState] = useState({ order: null, context: 'sale' });
    const [dataViewOrder, setDataViewOrder] = useState(null);
    const [messagesOrder, setMessagesOrder] = useState(null);
    const [duplicateOrder, setDuplicateOrder] = useState(null);
    const [desiredOrder, setDesiredOrder] = useState(null);
    const [bulkOrderIds, setBulkOrderIds] = useState([]);

    useRealtimeReload('dashboard.sales', '.workspace.changed', ['report']);
    useLandingUpsellHoldRefresh(rows);

    const assertUnlocked = (order) => {
        if (!order?.id) return true;
        const holder = locks[String(order.id)];
        if (holder && Number(holder.user_id) !== Number(authUserId)) {
            const role = holder.role_label || holder.role || '';
            toast.error(t('operations.ops_table.locked_by', {
                name: `${holder.user_name}${role ? ` (${role})` : ''}`,
            }));
            return false;
        }
        return true;
    };

    const openOrder = (order = null, closeIntent = false, operationResult = null) => {
        if (order && !assertUnlocked(order)) return;
        setOrderDialog({ open: true, order, closeIntent, operationResult });
    };

    const openDuplicateOrders = (order, options = {}) => {
        if (!order) return;
        setDuplicateOrder({ order, closedOnly: Boolean(options.closedOnly) });
    };

    return (
        <AppLayout>
            <Head title={t('operations.sale_workspace.page_title')} />
            <section className="ps-sale-workspace-page">
                {workspaceError && (
                    <div className="ps-alert ps-alert-danger">{workspaceError}</div>
                )}
                <SaleWorkspaceFilters routeUrl={routeUrl} filters={filters} filterOptions={filterOptions}>
                    <SaleWorkspaceTabs tabs={report.statusTabs ?? []} routeUrl={routeUrl} filters={filters} />
                    <SaleWorkspaceTable
                        rows={rows}
                        meta={meta}
                        filters={filters}
                        routeUrl={routeUrl}
                        actionBaseUrl={actionBaseUrl}
                        operationStatusOptions={operationStatusOptions}
                        onEdit={openOrder}
                        onHistory={(order, context = 'sale') => setHistoryState({ order, context })}
                        onDataViewHistory={setDataViewOrder}
                        onMessages={setMessagesOrder}
                        onDuplicateOrders={openDuplicateOrders}
                        onDesiredDate={(order) => assertUnlocked(order) && setDesiredOrder(order)}
                        onResult={(order, result) => openOrder(order, false, result)}
                        interactionLocks={locks}
                        authUserId={authUserId}
                        onBulkClose={setBulkOrderIds}
                    />
                </SaleWorkspaceFilters>

                <button type="button" className="tao-don-fixed ps-create-order-fab" onClick={() => openOrder(null, false)}>
                    <i className="fa fa-edit" /><span className="text">Tạo đơn</span>
                </button>
            </section>

            <SaleOrderDialog
                order={orderDialog.order}
                open={orderDialog.open}
                closeIntent={orderDialog.closeIntent}
                onOpenChange={(open) => setOrderDialog((current) => ({ ...current, open, operationResult: open ? current.operationResult : null }))}
                manualUrl={manualUrl}
                actionBaseUrl={actionBaseUrl}
                sourceOptions={sourceOptions}
                warehouseOptions={warehouseOptions}
                productOptions={productOptions}
                carrierOptions={carrierOptions}
                shippingServiceOptions={shippingServiceOptions}
                operationStatusOptions={operationStatusOptions}
                initialOperationResult={orderDialog.operationResult}
            />
            <SaleOperationHistoryDialog order={historyState.order} context={historyState.context} open={Boolean(historyState.order)} onOpenChange={(open) => !open && setHistoryState({ order: null, context: 'sale' })} />
            <PushsaleDataViewHistoryDialog order={dataViewOrder} open={Boolean(dataViewOrder)} onOpenChange={(open) => !open && setDataViewOrder(null)} />
            <PushsaleCustomerMessagesDialog order={messagesOrder} open={Boolean(messagesOrder)} onOpenChange={(open) => !open && setMessagesOrder(null)} />
            <DuplicatePhoneOrdersDialog
                order={duplicateOrder?.order ?? duplicateOrder}
                initialClosedOnly={Boolean(duplicateOrder?.closedOnly)}
                open={Boolean(duplicateOrder)}
                onOpenChange={(open) => !open && setDuplicateOrder(null)}
            />
            <DesiredDeliveryDialog order={desiredOrder} open={Boolean(desiredOrder)} onOpenChange={(open) => !open && setDesiredOrder(null)} actionBaseUrl={actionBaseUrl} />
            <BulkCloseDialog
                orderIds={bulkOrderIds}
                rows={rows}
                actionBaseUrl={actionBaseUrl}
                open={bulkOrderIds.length > 0}
                onOpenChange={(open) => !open && setBulkOrderIds([])}
            />
        </AppLayout>
    );
}
