/**
 * Business explanation content for each screen (English).
 * Matched by longest pathname prefix — add a new entry when adding a page.
 */
export default [
    // ─── Admin: Operations ────────────────────────────────────────────────
    {
        path: '/admin/dashboard',
        title: 'Executive overview',
        intro: 'A quick company-wide snapshot for the selected day or period, for leadership.',
        sections: [
            {
                heading: 'What does this page show?',
                items: [
                    'New leads received, closed orders, and recorded revenue in near real time.',
                    'Revenue trend charts help spot unusually high or low days.',
                    'Recent activity across departments (order closing, stock movements, landing approvals…).',
                ],
            },
            {
                heading: 'How to use it',
                items: [
                    'Start your day here for the big picture, then drill into individual reports under Reports.',
                    'Figures filter by date range — change filters to compare periods.',
                ],
            },
        ],
    },
    {
        path: '/admin/reports/business',
        title: 'Operations overview',
        intro: 'Track orders from lead intake through successful delivery and spot bottlenecks.',
        sections: [
            {
                heading: 'What does this page show?',
                items: [
                    'Orders at each stage: awaiting allocation → calling → closed → awaiting shipment → in transit → completed.',
                    'Conversion rates between stages — low rates indicate where to act.',
                ],
            },
            {
                heading: 'Notes',
                items: [
                    'Orders stuck in "awaiting shipment" are often because the warehouse has not created a shipping label — follow up with warehouse.',
                    'Unusually high return rates should be cross-checked against detailed sales revenue reports.',
                ],
            },
        ],
    },
    {
        path: '/admin/reports/ceo',
        title: 'Executive report',
        intro: 'High-level summary: revenue, marketing spend, and performance by department.',
        sections: [
            {
                heading: 'What does this page show?',
                items: [
                    'Revenue by status: closed, in transit, delivered, collected.',
                    'Performance comparison across sales and marketing teams.',
                    'Revenue share by product and by warehouse.',
                ],
            },
        ],
    },
    {
        path: '/admin/reports/extra',
        title: 'Business report suite',
        intro: 'Detailed reports by department. Use the tabs at the top to switch between reports.',
        sections: [
            {
                heading: 'Report groups',
                items: [
                    'Telesales: operational workload, closing summary, detailed revenue, KPI, callback schedule.',
                    'Marketing: revenue by marketer, order close rate by product.',
                    'Warehouse / system: revenue by warehouse, system sales (new vs returning customers).',
                ],
            },
            {
                heading: 'Access rules',
                items: [
                    'Admins see everything; department heads / team leads see their team\'s data.',
                    'Staff only see their own figures on permitted reports.',
                    'Percentage cells are color-coded: green = good, yellow = watch, red = action needed.',
                ],
            },
        ],
    },
    {
        path: '/admin/rankings',
        title: 'Revenue rankings',
        intro: 'Revenue leaderboard for sales and marketing staff in the selected period.',
        sections: [
            {
                heading: 'How it is calculated',
                items: [
                    'Revenue is based on orders closed in the selected date range, after discounts.',
                    'Filter by team or team lead to view internal team rankings.',
                ],
            },
        ],
    },

    // ─── Admin: Marketing ────────────────────────────────────────────────
    {
        path: '/admin/marketing/dashboard',
        title: 'Marketing overview',
        intro: 'Ad source performance: leads received, orders closed, and spend.',
        sections: [
            {
                heading: 'What does this page show?',
                items: [
                    'Leads by source / campaign and conversion to orders.',
                    'Budget spent vs revenue generated per campaign.',
                ],
            },
        ],
    },
    {
        path: '/admin/landing-approvals',
        title: 'Landing page approvals',
        intro: 'Approve marketing landing pages before ads go live.',
        sections: [
            {
                heading: 'Workflow',
                items: [
                    'Marketing creates campaign + landing → status "Pending approval".',
                    'Admin reviews content and linked products, then clicks Approve.',
                    'Only approved campaigns start receiving leads into the system.',
                ],
            },
        ],
    },
    {
        path: '/admin/marketing/campaign-report',
        title: 'Campaign report',
        intro: 'Compare marketing campaigns: leads, orders, revenue, and cost.',
        sections: [
            {
                heading: 'Key metrics',
                items: [
                    'Leads received and close rate per campaign.',
                    'Cost per closed order — expensive campaigns stand out immediately.',
                ],
            },
        ],
    },

    // ─── Admin: Telesales / Leads ──────────────────────────────────────────
    {
        path: '/admin/sales/performance',
        title: 'Telesales performance',
        intro: 'Track call volume and operational quality for the sales team.',
        sections: [
            {
                heading: 'Key metrics',
                items: [
                    'Calls made, contacts handled, and outcome per call (closed, callback, declined…).',
                    'Close rate over total assigned contacts.',
                ],
            },
        ],
    },
    {
        path: '/admin/leads',
        title: 'Lead intake log',
        intro: 'All leads from platforms and their allocation status to sales.',
        sections: [
            {
                heading: 'Business flow',
                items: [
                    'Leads from landing pages / ad platforms are recorded here.',
                    'Valid leads are auto-assigned or manually allocated ("Manual allocation").',
                    'Status shows whether the lead became an order, is a duplicate, or has data errors.',
                ],
            },
        ],
    },

    // ─── Admin: Integrations & Reconciliation ───────────────────────────────────────
    {
        path: '/admin/integrations',
        title: 'Platform connections',
        intro: 'Configure webhooks to receive leads from external platforms (landing pages, ad forms…).',
        sections: [
            {
                heading: 'How to use',
                items: [
                    'Each platform has its own webhook URL — paste it into that platform\'s settings.',
                    'The "Send test" button creates a sample lead to verify the connection.',
                ],
            },
        ],
    },
    {
        path: '/admin/shipping-partners',
        title: 'Shipping partners',
        intro: 'Set up carrier API accounts (GHN, GHTK, VTP…) for automatic shipping label creation.',
        sections: [
            {
                heading: 'How to use',
                items: [
                    'Enter the token / shop ID provided by the carrier and save.',
                    'Use test buttons to verify the connection before going live.',
                ],
            },
        ],
    },
    {
        path: '/admin/shipping/orders',
        title: 'Shipping orders',
        intro: 'Manage shipping labels for closed orders: create labels, track status, print.',
        sections: [
            {
                heading: 'Actions shown by status',
                items: [
                    'No label yet → "Create label" and "Calculate fee".',
                    'Active label → "Sync status", "Print label", "Cancel label".',
                    'Delivered / cancelled → view only, no further actions.',
                ],
            },
            {
                heading: 'Notes',
                items: [
                    'Delivery status syncs from the carrier via webhook or when you click "Sync".',
                    'Returned goods must be confirmed with "Receive return" to restore inventory.',
                ],
            },
        ],
    },
    {
        path: '/admin/shipping/reconciliation',
        title: 'Shipping reconciliation',
        intro: 'Match carrier-reported COD amounts with amounts recorded in the system.',
        sections: [
            {
                heading: 'Issue types',
                items: [
                    'COD mismatch: carrier amount differs from system — review the order.',
                    'Order not found: carrier tracking number not in the system.',
                    'Matched: reconciliation complete, funds received in full.',
                ],
            },
        ],
    },

    // ─── Admin: HR & Catalog ───────────────────────────────────────
    {
        path: '/admin/users',
        title: 'Staff',
        intro: 'Manage employee accounts: role, level, team, and direct manager.',
        sections: [
            {
                heading: 'Roles define access',
                items: [
                    'Sales see only their customers / orders; team leads see the whole team.',
                    'Marketing see campaigns and revenue they own.',
                    'Warehouse, accounting, and allocation have their own workspaces.',
                ],
            },
        ],
    },
    {
        path: '/admin/teams',
        title: 'Departments & teams',
        intro: 'Define departments, teams, and team leads — directly affects data access.',
        sections: [
            {
                heading: 'Notes',
                items: [
                    'Team leads can view data for all members in their team.',
                    'The org chart is built automatically from the structure defined here.',
                ],
            },
        ],
    },
    {
        path: '/admin/products',
        title: 'Products',
        intro: 'Product catalog: base products and variants, selling price, SKU.',
        sections: [
            {
                heading: 'Notes',
                items: [
                    'Every order links to a product — revenue-by-product reports use this data.',
                    'Stock is tracked per SKU under Product inventory.',
                ],
            },
        ],
    },
    {
        path: '/org-chart',
        title: 'Org chart',
        intro: 'Company organization by department, department head, team lead, and staff.',
        sections: [
            {
                heading: 'How to read it',
                items: [
                    'Each block is a department; leaders appear above their teams.',
                    'The chart reflects data access: superiors see subordinates\' data.',
                ],
            },
        ],
    },

    // ─── Admin: Warehouse & Finance ──────────────────────────────────────────
    {
        path: '/admin/accounting',
        title: 'Accounting',
        intro: 'Track order cash flow: delivered, COD collected, pending reconciliation, returns.',
        sections: [
            {
                heading: 'Business flow',
                items: [
                    'Successful delivery → await carrier COD transfer → reconcile → mark paid.',
                    'Returns / errors need a reason note for accurate revenue adjustment.',
                ],
            },
        ],
    },
    {
        path: '/admin/warehouses',
        title: 'Warehouse list',
        intro: 'Define physical warehouses and assigned warehouse managers.',
        sections: [
            {
                heading: 'Notes',
                items: [
                    'Each order ships from one warehouse — revenue-by-warehouse uses this.',
                    'A warehouse can only be deleted when it has no stock or related orders.',
                ],
            },
        ],
    },
    {
        path: '/admin/warehouse/inventory',
        title: 'Product inventory',
        intro: 'On-hand quantity per product per warehouse, with stock in / out actions.',
        sections: [
            {
                heading: 'Stock in / out workflow',
                items: [
                    'Stock in: select warehouse, product, quantity — voucher needs warehouse lead approval to take effect.',
                    'Manual stock out for transfers / write-offs; sales stock out auto-deducts when the order is handed to shipping.',
                    'Returns add stock back when warehouse confirms receipt.',
                ],
            },
        ],
    },
    {
        path: '/admin/warehouse/movements',
        title: 'Stock movement history',
        intro: 'All inventory changes: who acted, who approved, when, and quantity.',
        sections: [
            {
                heading: 'How to read it',
                items: [
                    'Each row is a movement: stock in, sales out, other out, return.',
                    'The "Approver" column confirms the voucher was signed off by the warehouse lead.',
                ],
            },
        ],
    },
    {
        path: '/admin/orders/failed',
        title: 'Failed orders',
        intro: 'Orders / partner data that failed on sync — requires manual handling.',
        sections: [
            {
                heading: 'How to resolve',
                items: [
                    'See the error reason per row (bad address, missing phone, product mismatch…).',
                    'Fix source data and re-sync, or delete if junk.',
                ],
            },
        ],
    },

    // ─── Sales ───────────────────────────────────────────────────────────
    {
        path: '/sales/dashboard',
        title: 'My overview',
        intro: 'Your personal figures for the day: assigned contacts, closed orders, revenue.',
        sections: [
            {
                heading: 'How to use it',
                items: [
                    'Track personal KPI progress and today\'s to-do list.',
                    'Callbacks due now appear here — prioritize them first.',
                ],
            },
        ],
    },
    {
        path: '/sales/workspace',
        title: 'Call & close orders',
        intro: 'Main sales workspace: call customers, log outcomes, close orders.',
        sections: [
            {
                heading: 'Workflow',
                items: [
                    'Newly assigned customers appear at the top — click to view details and call.',
                    'After each call, log the outcome: closed, callback, no answer, declined…',
                    'Callback customers auto-advance (Call 2, Call 3…) until closed or skipped.',
                    'Close order: choose product, quantity, delivery address — order goes to warehouse.',
                ],
            },
        ],
    },
    {
        path: '/sales/performance',
        title: 'Performance report',
        intro: 'Your results (team leads see the whole team): calls, close rate, revenue.',
        sections: [
            {
                heading: 'Notes',
                items: [
                    'Revenue is based on orders closed in the selected date range.',
                    'Close rate = closed orders / total assigned contacts.',
                ],
            },
        ],
    },
    {
        path: '/sales/reports',
        title: 'Sales business reports',
        intro: 'Daily sales reports. Staff see only their data; team leads see the whole team.',
        sections: [
            {
                heading: 'Reports',
                items: [
                    'Sales workload: which call step each contact is on and how many are pending.',
                    'Closing summary & detailed revenue: your / team sales results.',
                    'Sales KPI: new vs returning customers, expected vs actual revenue.',
                    'Telesales callback schedule: customers to call back in the next 7 days.',
                ],
            },
        ],
    },
    {
        path: '/sales/customers',
        title: 'Customer profiles',
        intro: 'Customers you own, with purchase history and care notes.',
        sections: [
            {
                heading: 'Notes',
                items: [
                    'Customers with multiple orders are marked "returning" — prioritize follow-up.',
                    'Call history and notes help handoffs get context quickly.',
                ],
            },
        ],
    },

    // ─── Marketing ───────────────────────────────────────────────────────
    {
        path: '/marketing/dashboard',
        title: 'Marketing overview',
        intro: 'Performance of campaigns you own: leads, closed orders, budget.',
        sections: [
            {
                heading: 'How to use it',
                items: [
                    'Compare leads and revenue across campaigns to reallocate budget.',
                    'A sudden drop in leads often means a broken landing page or exhausted ad budget.',
                ],
            },
        ],
    },
    {
        path: '/marketing/workspace',
        title: 'Ad source report',
        intro: 'Lead details by source / ad platform.',
        sections: [
            {
                heading: 'Notes',
                items: [
                    'Watch valid lead rate — many duplicates / bad phones signal a poor source.',
                ],
            },
        ],
    },
    {
        path: '/marketing/campaigns',
        title: 'Landing pages',
        intro: 'Create and manage campaigns + landing pages that receive leads.',
        sections: [
            {
                heading: 'Workflow',
                items: [
                    'Create campaign, attach product and budget → submit for admin approval.',
                    'After approval, the landing starts receiving leads into the system.',
                ],
            },
        ],
    },
    {
        path: '/marketing/campaign-report',
        title: 'Campaign report',
        intro: 'Performance of your campaigns: leads, closed orders, cost per order.',
        sections: [
            {
                heading: 'Notes',
                items: [
                    'Cost per order above average order value means the campaign is losing money.',
                ],
            },
        ],
    },
    {
        path: '/marketing/revenue',
        title: 'Revenue report',
        intro: 'Revenue from leads of campaigns you own.',
        sections: [
            {
                heading: 'Notes',
                items: [
                    'Revenue is counted when sales closes an order from the campaign\'s lead.',
                ],
            },
        ],
    },
    {
        path: '/marketing/reports',
        title: 'Marketing business reports',
        intro: 'Detailed report suite. Staff see their data; team leads see the team and product reports.',
        sections: [
            {
                heading: 'Reports',
                items: [
                    'Marketing revenue: orders and revenue by delivery status.',
                    'Product close rate (team lead): which products close easily and average value.',
                ],
            },
        ],
    },

    // ─── Warehouse ───────────────────────────────────────────────────────
    {
        path: '/warehouse/dashboard',
        title: 'Warehouse overview',
        intro: 'Today\'s warehouse tasks: orders awaiting labels, returns to receive, low stock.',
        sections: [
            {
                heading: 'How to use it',
                items: [
                    'Prioritize orders awaiting shipping labels so goods ship early.',
                    'Low stock alerts help plan replenishment.',
                ],
            },
        ],
    },
    {
        path: '/warehouse/workspace',
        title: 'Fulfillment & shipping',
        intro: 'Warehouse workspace: create shipping labels for closed orders and track delivery.',
        sections: [
            {
                heading: 'Workflow',
                items: [
                    'Closed sales orders arrive here as "Awaiting label".',
                    'Open order details → choose carrier → "Calculate fee" for a quote → "Create label".',
                    'Successful label creation auto-deducts stock and moves the order to "Picking / In transit".',
                    'Returns: click "Receive return" to add stock back.',
                ],
            },
            {
                heading: 'Actions shown by status',
                items: [
                    'No label yet → Create label, Calculate fee.',
                    'Active label → Sync status, Print label, Cancel label.',
                    'Delivered / paid / cancelled → view only.',
                ],
            },
        ],
    },
    {
        path: '/warehouse/shipping/orders',
        title: 'Shipping orders',
        intro: 'Warehouse shipping label list and delivery status per order.',
        sections: [
            {
                heading: 'Notes',
                items: [
                    'Status syncs from the carrier — click "Sync" if data looks stale.',
                    'Repeated failed deliveries: notify sales to call the customer before reshipping.',
                ],
            },
        ],
    },
    {
        path: '/warehouse/inventory',
        title: 'Product inventory',
        intro: 'On-hand stock at your warehouse, with approved stock in / out.',
        sections: [
            {
                heading: 'Workflow',
                items: [
                    'Stock in / out vouchers need warehouse lead approval before affecting balance.',
                    'Sales stock out is automatic when a label is created — no manual step.',
                ],
            },
        ],
    },
    {
        path: '/warehouse/reports',
        title: 'Warehouse reports',
        intro: 'Revenue and goods flow by warehouse — for warehouse leads.',
        sections: [
            {
                heading: 'Notes',
                items: [
                    'Confirmed revenue = delivered + paid orders.',
                    'Unusually high returns: check packaging quality and addresses.',
                ],
            },
        ],
    },

    // ─── Accounting ──────────────────────────────────────────────────────
    {
        path: '/accounting/dashboard',
        title: 'Accounting overview',
        intro: 'Cash flow in the period: COD collected, pending reconciliation, returns.',
        sections: [
            {
                heading: 'How to use it',
                items: [
                    'Track uncollected COD against delivered orders to follow up with partners in time.',
                ],
            },
        ],
    },
    {
        path: '/accounting/workspace',
        title: 'Orders & cash flow',
        intro: 'Orders by payment status: delivered awaiting collection, paid, return / error.',
        sections: [
            {
                heading: 'Business flow',
                items: [
                    'Successful delivery moves to awaiting COD collection.',
                    'When the carrier transfers funds and reconciliation matches → mark paid.',
                    'Returns need a reason note to deduct revenue in the correct period.',
                ],
            },
        ],
    },
    {
        path: '/accounting/reports',
        title: 'Business reports',
        intro: 'System-wide revenue reports for accounting: by warehouse, sales, marketing.',
        sections: [
            {
                heading: 'Reports',
                items: [
                    'Revenue by warehouse & system sales: revenue, new vs returning customers.',
                    'Closing summary / detailed revenue: reconcile with sales and marketing.',
                ],
            },
        ],
    },

    // ─── Allocator ───────────────────────────────────────────────────────
    {
        path: '/allocator/dashboard',
        title: 'Allocation overview',
        intro: 'Leads waiting to be assigned and today\'s processing speed.',
        sections: [
            {
                heading: 'Notes',
                items: [
                    'Leads waiting too long hurt close rate — assign as soon as leads arrive.',
                ],
            },
        ],
    },
    {
        path: '/allocator/workspace',
        title: 'Assign leads to sales',
        intro: 'Distribute new leads to sales based on capacity and current workload.',
        sections: [
            {
                heading: 'How to use',
                items: [
                    'Select unassigned leads → choose receiving sales rep → confirm.',
                    'Duplicate phone numbers should go back to the sales rep who handled the customer before.',
                ],
            },
        ],
    },
];
