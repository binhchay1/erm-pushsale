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
                heading: 'Top metric cards',
                items: [
                    'New leads: leads received from landing pages / ads in the filtered period.',
                    'Closed orders: orders successfully closed by sales, before cancellations / returns.',
                    'Recorded revenue: total value of closed orders after discounts in the period.',
                    'Close rate: closed orders / leads received — gauges lead quality and the sales team.',
                ],
            },
            {
                heading: 'Charts & activity',
                items: [
                    'Daily revenue trend chart: spot unusually high or low days.',
                    'Lead source breakdown: which source brings in the most leads.',
                    'Recent activity feed: order closing, stock in / out, landing approvals… in near real time.',
                ],
            },
            {
                heading: 'How to use it',
                items: [
                    'Start your day here for the big picture, then drill into individual reports under Reports.',
                    'Switch This week / This month / This quarter to compare periods.',
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
                heading: 'Operations funnel (stages)',
                items: [
                    'Awaiting allocation → Calling → Closed → Awaiting shipment → In transit → Completed.',
                    'Each stage shows how many orders are stuck there — a bulging stage is a bottleneck.',
                    'Conversion rate between adjacent stages: the one losing the most needs attention first.',
                ],
            },
            {
                heading: 'Reading conversion metrics',
                items: [
                    'Low lead → close rate: poor lead quality or slow sales follow-up.',
                    'Low close → delivered rate: many cancellations / failed deliveries.',
                    'Percentage cells are color-coded: green = good, yellow = watch, red = act now.',
                ],
            },
            {
                heading: 'Notes',
                items: [
                    'Orders stuck in "Awaiting shipment" usually mean the warehouse has not created a label — follow up.',
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
                heading: 'Revenue by status',
                items: [
                    'Closed: just closed by sales, not yet shipped.',
                    'In transit: handed to the carrier, on the way.',
                    'Delivered: carrier reports success, COD pending / collected.',
                    'Paid: COD received and reconciled — actual recognized revenue.',
                ],
            },
            {
                heading: 'Comparison & breakdown',
                items: [
                    'Compare performance across sales and marketing teams in the same period.',
                    'Revenue share by product: flagship vs underperforming products.',
                    'Revenue share by warehouse: balance stock and delivery capacity by region.',
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
                heading: 'Telesales group',
                items: [
                    'Operational workload: which call step each contact is on and how many are pending.',
                    'Closing summary: orders, revenue, close rate per employee.',
                    'Detailed revenue: each order with product, value, and status.',
                    'Sales KPI & callback schedule: expected vs actual, customers to call within 7 days.',
                ],
            },
            {
                heading: 'Marketing & Warehouse groups',
                items: [
                    'Marketing: revenue by marketer, order close rate by product.',
                    'Warehouse / system: revenue by warehouse, system sales (new vs returning customers).',
                ],
            },
            {
                heading: 'Access rules & colors',
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
                    'Choose "Before/After discount" to change how the leaderboard sums revenue.',
                    'Quick period filter: This week / This month / This quarter or a custom range.',
                ],
            },
            {
                heading: 'Filtering & scope',
                items: [
                    'Filter by team or team lead to view internal team rankings.',
                    'Ranks reflect the active filter — changing it re-ranks the list.',
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
                    'Cost per closed order (CPO) — which campaigns are expensive.',
                ],
            },
            {
                heading: 'How to use it',
                items: [
                    'Shift budget toward campaigns with low CPO and high close rate.',
                    'A sudden drop in leads usually means a broken landing page or exhausted ad budget.',
                ],
            },
        ],
    },
    {
        path: '/admin/landing-approvals',
        title: 'Landing page approvals',
        intro: 'Approve marketing landing / campaign connections before they receive leads.',
        sections: [
            {
                heading: 'Approval workflow',
                items: [
                    'Marketing creates a campaign + landing → status "Pending approval".',
                    'Click a row (or the Details button) to open the full campaign detail popup.',
                    'Click Approve after review — only approved campaigns get leads routed to sales.',
                ],
            },
            {
                heading: 'Info in the detail popup',
                items: [
                    'Campaign: creator, marketer, ad channel, lead intake status, budget.',
                    'Product & price: product name, SKU, current selling price.',
                    'Tracking & webhook: utm_campaign, utm_source, lead intake URL (paste into Ladipage).',
                    'Ladipage → system field mapping table to configure the form correctly.',
                ],
            },
            {
                heading: 'Notes',
                items: [
                    'Before approval, test leads go to Admin only, not to sales.',
                    'Opening from a notification auto-scrolls to and opens the campaign popup.',
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
                    'Revenue generated vs budget spent.',
                    'Cost per closed order — expensive campaigns stand out immediately.',
                ],
            },
            {
                heading: 'Notes',
                items: [
                    'Cost per order above average order value means the campaign is losing money.',
                    'Cross-check valid lead rate to know whether a source has many junk leads.',
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
                    'Calls made and contacts handled per employee.',
                    'Outcome per call: closed, callback, no answer, declined, wrong number…',
                    'Close rate = closed orders / total assigned contacts.',
                ],
            },
            {
                heading: 'How to use it',
                items: [
                    'High call volume but low close rate → coach on the script.',
                    'Many untouched contacts → forgotten leads, prompt action.',
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
                heading: 'Main columns',
                items: [
                    'Time: when the lead arrived in the system.',
                    'Source: the platform / campaign that generated the lead.',
                    'Phone / Name: customer info; duplicates are flagged.',
                    'Status: order created, duplicate, or data error.',
                    'Sales: the rep the lead was assigned to (if any).',
                ],
            },
            {
                heading: 'Business flow',
                items: [
                    'Leads from landing pages / ads are recorded here.',
                    'Valid leads are auto-assigned or manually allocated via "Manual allocation".',
                    'Faulty leads (bad phone, missing info) need source fixes or to be skipped.',
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
                heading: 'How to connect',
                items: [
                    'Each platform has its own webhook URL — paste it into that platform\'s settings.',
                    'Enter credentials (verify token / webhook secret / API key) and Save.',
                    'Turn on the "Receive webhook" switch so the system accepts incoming leads.',
                ],
            },
            {
                heading: 'Testing & monitoring',
                items: [
                    'The "Send test" button creates a sample lead to verify the connection.',
                    'Check "Last received" to know whether a platform is still sending leads.',
                    'Stat cards: leads today, pending processing, platforms enabled.',
                ],
            },
        ],
    },
    {
        path: '/admin/shipping-partners',
        title: 'Shipping partners',
        intro: 'Set up carrier API accounts (GHN, GHTK, VTP, J&T, SPX…) for automatic label creation.',
        sections: [
            {
                heading: 'How to configure',
                items: [
                    'Enter the token / shop ID / secret provided by the carrier and Save.',
                    'Turn on "Activate partner" so the system may call the API to create labels.',
                    'Set a webhook secret to receive delivery status callbacks from the carrier.',
                ],
            },
            {
                heading: 'Test before going live',
                items: [
                    'Use the test buttons (verify token, pickup list, sample fee…) to confirm the connection.',
                    'Only fully configured and enabled carriers appear when creating a label in the warehouse screen.',
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
                heading: 'Order detail (open each order)',
                items: [
                    'Choose a carrier and preview the cost via "Calculate fee" before creating a label.',
                    'The delivery timeline (tracking) updates by milestone from the carrier.',
                    'Customer, COD, and order total are shown for label printing / reconciliation.',
                ],
            },
            {
                heading: 'Notes',
                items: [
                    'Delivery status syncs from the carrier via webhook or when you click "Sync".',
                    'Returned goods must be confirmed as received to restore inventory.',
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
                    'COD mismatch: carrier amount differs from system — review the order value.',
                    'Order not found: carrier tracking number not in the system.',
                    'Matched: reconciliation complete, funds received — can be marked as paid.',
                ],
            },
            {
                heading: 'How to read the table',
                items: [
                    'Each row is a carrier callback with tracking number, partner COD, and system COD.',
                    'Prioritize rows with COD mismatch and callbacks that could not be matched to an order.',
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
                    'Warehouse, accounting, and allocation have their own role-based workspaces.',
                ],
            },
            {
                heading: 'Level & management',
                items: [
                    'Level (department head / supervisor / staff) widens the data scope.',
                    'Assign a team and direct manager so the org chart and access are accurate.',
                    'You cannot delete your own account or the last admin.',
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
                heading: 'Structure',
                items: [
                    'Each team has a type (sales / marketing / warehouse / allocation / accounting) and one lead.',
                    'Teams can be nested (parent department → child teams) to mirror the org.',
                ],
            },
            {
                heading: 'Notes',
                items: [
                    'Team leads can view data for all members in their team.',
                    'The org chart is built automatically from the structure defined here.',
                    'A team with members or child teams cannot be deleted.',
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
                heading: 'Catalog structure',
                items: [
                    'A base product groups its child variants (size, color, combo…).',
                    'Each variant has its own SKU and selling price.',
                ],
            },
            {
                heading: 'Notes',
                items: [
                    'Every order links to a product — revenue-by-product reports use this data.',
                    'Stock is tracked per SKU under Product inventory.',
                    'A product with child variants must have its variants deleted first.',
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
                    'Each person card may show close rate / revenue metrics if you have access.',
                ],
            },
            {
                heading: 'Visibility scope',
                items: [
                    'Admins see the whole company; department heads see their entire division.',
                    'Team leads and staff only see their own team.',
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
                heading: 'Cash flow',
                items: [
                    'Successful delivery → await carrier COD transfer.',
                    'Funds received and reconciled → mark "paid" (actual recognized revenue).',
                    'Returns / errors need a reason note for accurate revenue adjustment.',
                ],
            },
            {
                heading: 'Notes',
                items: [
                    'Track uncollected COD against delivered orders to follow up with partners in time.',
                    'Cross-check with Shipping reconciliation to handle COD-mismatch orders.',
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
                    'Assign warehouse staff / leads to approve stock vouchers.',
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
                    'Manual stock out for transfers / write-offs.',
                    'Sales stock out auto-deducts when the order gets a shipping label.',
                    'Returns add stock back when warehouse confirms receipt.',
                ],
            },
            {
                heading: 'Notes',
                items: [
                    'The on-hand column shows real stock; SKUs below threshold trigger a low-stock alert.',
                    'All changes are logged under Stock movement history.',
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
                    'Filter by warehouse / product / date range to trace a specific SKU.',
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
                    'Fix source data and re-sync.',
                    'Delete the row if it is junk / duplicate not worth keeping.',
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
                heading: 'What does this page show?',
                items: [
                    'Contacts assigned today and how many you have handled.',
                    'Your closed orders and revenue against your KPI.',
                    'Callbacks due now — prioritize them first.',
                ],
            },
            {
                heading: 'How to use it',
                items: [
                    'Track personal KPI progress and today\'s to-do list.',
                    'Click a due callback to jump straight into the call & close workspace.',
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
            {
                heading: 'Operating tips',
                items: [
                    'Read call history / notes before calling to grasp the customer\'s context.',
                    'For duplicate-phone (returning) customers, confirm the old order for better care.',
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
                heading: 'Key metrics',
                items: [
                    'Calls made and contacts handled in the period.',
                    'Close rate = closed orders / total assigned contacts.',
                    'Revenue is based on orders closed in the selected date range.',
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
                    'Open a profile to see all orders and each order\'s delivery status.',
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
                    'Track cost per closed order to drop underperforming campaigns.',
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
                    'Compare which source yields the most closed orders to optimize budget.',
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
                    'Create a campaign, attach product and budget → the system issues a lead intake URL.',
                    'Paste the lead intake URL into the API settings of Ladipage / your landing.',
                    'Submit for admin approval — after approval, the landing starts receiving leads.',
                ],
            },
            {
                heading: 'Notes',
                items: [
                    'The campaign name generates utm_campaign — Ladipage must send this exact field.',
                    'The field mapping table helps align form data with the system.',
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
                    'High leads but low close rate → check source quality / landing content.',
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
                    'Revenue by delivery status shows how much is actually collected vs at risk of return.',
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
                    'Receive returns to add stock back and close the orders.',
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
                    'Closed sales orders arrive here as "Awaiting waybill".',
                    'Open order details → choose carrier → "Calculate fee" for a quote → "Create label".',
                    'Successful label creation auto-deducts stock and moves the order to "Picking / In transit".',
                    'Returns: click "Receive return" to add stock back.',
                ],
            },
            {
                heading: 'Status tabs',
                items: [
                    'Awaiting waybill, Pickup, Delivering, Delivered, Paid, Returns, Cancelled.',
                    'Click a tab to quickly filter the group of orders to handle.',
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
                    'SKUs below the stock threshold trigger a replenishment alert.',
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
                    'Know the return rate to forecast cash flow accurately.',
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
                    'Track the unassigned lead backlog to balance load across sales reps.',
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
                    'Balance the number of leads per rep so no one is overloaded or idle.',
                ],
            },
        ],
    },
];
