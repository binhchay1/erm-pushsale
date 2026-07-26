import { Head } from '@inertiajs/react';
import { useState } from 'react';

import AppLayout from '@/layouts/AppLayout';
import { useLandingUpsellHoldRefresh } from '@/hooks/useLandingUpsellHoldRefresh';
import { useRealtimeReload } from '@/hooks/useRealtimeReload';
import {
    PushsaleCustomerMessagesDialog,
    PushsaleDataViewHistoryDialog,
    PushsalePurchaseHistoryDialog,
} from '@/components/customers/pushsale/PushsaleCustomerDialogs';
import {
    BulkCloseDialog,
    DesiredDeliveryDialog,
    DuplicatePhoneOrdersDialog,
    OperationResultDialog,
    SaleOperationHistoryDialog,
} from '@/components/operations/pushsale/SaleOperationDialogs';
import { SaleOrderDialog } from '@/components/operations/pushsale/SaleOrderDialog';
import { SaleWorkspaceFilters } from '@/components/operations/pushsale/SaleWorkspaceFilters';
import { SaleWorkspaceTabs } from '@/components/operations/pushsale/SaleWorkspaceTabs';
import { SaleWorkspaceTable } from '@/components/operations/pushsale/SaleWorkspaceTable';

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
    const rows = report?.rows?.data ?? [];
    const meta = report?.rows?.meta ?? null;
    const [orderDialog, setOrderDialog] = useState({ open: false, order: null, closeIntent: false });
    const [historyState, setHistoryState] = useState({ order: null, context: 'sale' });
    const [dataViewOrder, setDataViewOrder] = useState(null);
    const [messagesOrder, setMessagesOrder] = useState(null);
    const [purchaseOrder, setPurchaseOrder] = useState(null);
    const [duplicateOrder, setDuplicateOrder] = useState(null);
    const [desiredOrder, setDesiredOrder] = useState(null);
    const [resultState, setResultState] = useState({ open: false, order: null, result: null });
    const [bulkOrderIds, setBulkOrderIds] = useState([]);

    useRealtimeReload('dashboard.sales', '.workspace.changed', ['report']);
    useLandingUpsellHoldRefresh(rows);

    const openOrder = (order = null, closeIntent = false) => setOrderDialog({ open: true, order, closeIntent });

    return (
        <AppLayout>
            <Head title="Sale tác nghiệp" />
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
                        onPurchaseHistory={setPurchaseOrder}
                        onDuplicateOrders={setDuplicateOrder}
                        onDesiredDate={setDesiredOrder}
                        onResult={(order, result) => setResultState({ open: true, order, result })}
                        onBulkClose={setBulkOrderIds}
                    />
                </SaleWorkspaceFilters>

                <button type="button" className="tao-don-fixed ps-create-order-fab" onClick={() => openOrder(null, false)}>
                    <i className="fa fa-pencil-square-o" /><span className="text">Tạo đơn</span>
                </button>
            </section>

            <SaleOrderDialog
                order={orderDialog.order}
                open={orderDialog.open}
                closeIntent={orderDialog.closeIntent}
                onOpenChange={(open) => setOrderDialog((current) => ({ ...current, open }))}
                manualUrl={manualUrl}
                actionBaseUrl={actionBaseUrl}
                sourceOptions={sourceOptions}
                warehouseOptions={warehouseOptions}
                productOptions={productOptions}
                carrierOptions={carrierOptions}
                shippingServiceOptions={shippingServiceOptions}
            />
            <SaleOperationHistoryDialog order={historyState.order} context={historyState.context} open={Boolean(historyState.order)} onOpenChange={(open) => !open && setHistoryState({ order: null, context: 'sale' })} />
            <PushsaleDataViewHistoryDialog order={dataViewOrder} open={Boolean(dataViewOrder)} onOpenChange={(open) => !open && setDataViewOrder(null)} />
            <PushsaleCustomerMessagesDialog order={messagesOrder} open={Boolean(messagesOrder)} onOpenChange={(open) => !open && setMessagesOrder(null)} />
            <PushsalePurchaseHistoryDialog order={purchaseOrder} open={Boolean(purchaseOrder)} onOpenChange={(open) => !open && setPurchaseOrder(null)} />
            <DuplicatePhoneOrdersDialog order={duplicateOrder} open={Boolean(duplicateOrder)} onOpenChange={(open) => !open && setDuplicateOrder(null)} />
            <DesiredDeliveryDialog order={desiredOrder} open={Boolean(desiredOrder)} onOpenChange={(open) => !open && setDesiredOrder(null)} actionBaseUrl={actionBaseUrl} />
            <OperationResultDialog
                order={resultState.order}
                result={resultState.result}
                open={resultState.open}
                onOpenChange={(open) => setResultState((current) => ({ ...current, open }))}
                actionBaseUrl={actionBaseUrl}
                onCloseOrder={(order) => openOrder(order, true)}
            />
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
