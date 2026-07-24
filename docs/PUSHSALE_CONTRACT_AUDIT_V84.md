# Pushsale contract audit

Summary: 33 pass, 12 warn, 0 fail.

## pnpm
- ✅ packageManager pinned to pnpm@9.15.9 (package.json)
- ✅ preinstall blocks npm/yarn installs (package.json)
- ✅ pnpm-lock.yaml exists (pnpm-lock.yaml)
- ✅ package-lock.json absent
- ✅ pnpm-workspace.yaml has packages field (pnpm-workspace.yaml)

## frontend-contract
- ✅ Required shared component exists: resources/js/components/layout/PushsalePageShell.jsx (resources/js/components/layout/PushsalePageShell.jsx)
- ✅ Required shared component exists: resources/js/components/filters/DateRangeFilter.jsx (resources/js/components/filters/DateRangeFilter.jsx)
- ✅ Required shared component exists: resources/js/components/filters/ProductSearchSelect.jsx (resources/js/components/filters/ProductSearchSelect.jsx)
- ✅ Required shared component exists: resources/js/components/pagination/PushsalePagination.jsx (resources/js/components/pagination/PushsalePagination.jsx)
- ✅ Required shared component exists: resources/js/lib/pushsaleStyleRegistry.js (resources/js/lib/pushsaleStyleRegistry.js)

## css-registry
- ⚠️ Versioned CSS exists but is not loaded by registry: pushsale-legacy-fixes.css (resources/css/pushsale-legacy-fixes.css)
- ⚠️ Versioned CSS exists but is not loaded by registry: pushsale-legacy-adminlte-fixes.css (resources/css/pushsale-legacy-adminlte-fixes.css)
- ✅ Final stability contract CSS is registered (resources/js/lib/pushsaleStyleRegistry.js)

## css-scope
- ⚠️ Potential broad selector 'table' in versioned CSS (resources/css/pushsale-legacy-fixes.css:189)
- ⚠️ Potential broad selector 'table' in versioned CSS (resources/css/pushsale-page-polish.css:175)
- ⚠️ Potential broad selector 'table' in versioned CSS (resources/css/pushsale-combo-page.css:106)
- ⚠️ Potential broad selector 'table' in versioned CSS (resources/css/pushsale-login-history.css:78)
- ⚠️ Potential broad selector 'table' in versioned CSS (resources/css/pushsale-operation-categories.css:166)
- ⚠️ Potential broad selector 'table' in versioned CSS (resources/css/pushsale-users-frame-toast.css:189)
- ⚠️ Potential broad selector 'table' in versioned CSS (resources/css/pushsale-teams-page.css:32)
- ⚠️ Potential broad selector 'table' in versioned CSS (resources/css/pushsale-accounting-operations.css:181)

## naming-debt
- ⚠️ 64 legacy React page files still use Page_* names. Migrate by business cluster, not all at once. (resources/js/pages/Pushsale/Pages)
- ⚠️ 64 legacy controller files still use Page* names. Keep redirects while migrating. (app/Http/Controllers/Admin/Pushsale/Pages)

## business-service
- ✅ Business service exists: app/Services/Leads/LeadIngestionService.php (app/Services/Leads/LeadIngestionService.php)
- ✅ Business service exists: app/Services/Leads/LandingUpsellService.php (app/Services/Leads/LandingUpsellService.php)
- ✅ Business service exists: app/Services/Marketing/LandingConnectionManager.php (app/Services/Marketing/LandingConnectionManager.php)
- ✅ Business service exists: app/Services/Operations/SaleOperationService.php (app/Services/Operations/SaleOperationService.php)
- ✅ Business service exists: app/Services/Operations/WarehouseOperationService.php (app/Services/Operations/WarehouseOperationService.php)
- ✅ Business service exists: app/Services/Operations/AccountingOperationService.php (app/Services/Operations/AccountingOperationService.php)
- ✅ Business service exists: app/Services/Reporting/DailyReportAggregator.php (app/Services/Reporting/DailyReportAggregator.php)
- ✅ Business service exists: app/Services/Reporting/MonthlyArchiveService.php (app/Services/Reporting/MonthlyArchiveService.php)
- ✅ Business service exists: app/Services/Pushsale/PushsaleLiveDataService.php (app/Services/Pushsale/PushsaleLiveDataService.php)
- ✅ Business service exists: app/Services/Pushsale/PushsalePageService.php (app/Services/Pushsale/PushsalePageService.php)

## routes
- ✅ Route present: sales workspace
- ✅ Route present: warehouse operations
- ✅ Route present: accounting operations
- ✅ Route present: customer profile bulk export
- ✅ Route present: landing connections
- ✅ Route present: teams resource
- ✅ Route present: users quick update

## tests
- ✅ Test exists: tests/Feature/Leads/LandingConnectionFlowTest.php (tests/Feature/Leads/LandingConnectionFlowTest.php)
- ✅ Test exists: tests/Feature/Leads/LandingUpsellCompleteBusinessFlowTest.php (tests/Feature/Leads/LandingUpsellCompleteBusinessFlowTest.php)
- ✅ Test exists: tests/Feature/Shipping/WarehouseShippingFlowV16Test.php (tests/Feature/Shipping/WarehouseShippingFlowV16Test.php)
- ✅ Test exists: tests/Feature/Reports/HistoricalReportingV18Test.php (tests/Feature/Reports/HistoricalReportingV18Test.php)
- ✅ Test exists: tests/Unit/PushsalePageRegistryTest.php (tests/Unit/PushsalePageRegistryTest.php)
