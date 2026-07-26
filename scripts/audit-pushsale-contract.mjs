#!/usr/bin/env node
import fs from 'node:fs';
import path from 'node:path';

const root = process.cwd();
const rel = (file) => path.relative(root, file).replaceAll(path.sep, '/');
const read = (file) => fs.readFileSync(path.join(root, file), 'utf8');
const exists = (file) => fs.existsSync(path.join(root, file));

// `_archive/` giữ file cũ có chủ đích (AGENTS.md §3.5) nên không tính là nợ contract.
const IGNORED_DIRS = new Set(['_archive', 'node_modules', 'vendor']);

function walk(dir, predicate = () => true) {
    const base = path.join(root, dir);
    if (!fs.existsSync(base)) return [];
    const out = [];
    const stack = [base];
    while (stack.length) {
        const current = stack.pop();
        for (const entry of fs.readdirSync(current, { withFileTypes: true })) {
            const full = path.join(current, entry.name);
            if (entry.isDirectory()) {
                if (!IGNORED_DIRS.has(entry.name)) stack.push(full);
            } else if (predicate(full)) {
                out.push(rel(full));
            }
        }
    }
    return out.sort();
}

function lineOf(content, index) {
    return content.slice(0, index).split('\n').length;
}

const checks = [];
const warn = (area, message, file = null) => checks.push({ level: 'warn', area, message, file });
const fail = (area, message, file = null) => checks.push({ level: 'fail', area, message, file });
const pass = (area, message, file = null) => checks.push({ level: 'pass', area, message, file });

// Package manager contract.
if (!exists('package.json')) fail('pnpm', 'Missing package.json');
else {
    const pkg = JSON.parse(read('package.json'));
    if (pkg.packageManager === 'pnpm@9.15.9') pass('pnpm', 'packageManager pinned to pnpm@9.15.9', 'package.json');
    else fail('pnpm', `packageManager must be pnpm@9.15.9, got ${pkg.packageManager}`, 'package.json');
    if (pkg.scripts?.preinstall?.includes('enforce-pnpm')) pass('pnpm', 'preinstall blocks npm/yarn installs', 'package.json');
    else warn('pnpm', 'preinstall does not enforce pnpm', 'package.json');
}
if (exists('pnpm-lock.yaml')) pass('pnpm', 'pnpm-lock.yaml exists', 'pnpm-lock.yaml'); else fail('pnpm', 'Missing pnpm-lock.yaml');
if (exists('package-lock.json')) fail('pnpm', 'package-lock.json still exists; PNPM-only deploy will reject it', 'package-lock.json'); else pass('pnpm', 'package-lock.json absent');
if (exists('pnpm-workspace.yaml') && read('pnpm-workspace.yaml').includes('packages:')) pass('pnpm', 'pnpm-workspace.yaml has packages field', 'pnpm-workspace.yaml');
else fail('pnpm', 'pnpm-workspace.yaml missing packages field', 'pnpm-workspace.yaml');

// Shared frontend components.
[
    'resources/js/components/layout/PushsalePageShell.jsx',
    'resources/js/components/filters/DateRangeFilter.jsx',
    'resources/js/components/filters/ProductSearchSelect.jsx',
    'resources/js/components/pagination/PushsalePagination.jsx',
    'resources/js/lib/pushsaleStyleRegistry.js',
].forEach((file) => exists(file) ? pass('frontend-contract', `Required shared component exists: ${file}`, file) : fail('frontend-contract', `Missing required shared component: ${file}`, file));

// CSS registry coverage.
if (exists('resources/js/lib/pushsaleStyleRegistry.js')) {
    const registry = read('resources/js/lib/pushsaleStyleRegistry.js');
    const registeredCss = [...registry.matchAll(/file:\s*'([^']+\.css)'/g)].map((m) => m[1]);
    const versionCss = walk('resources/css', (file) => /pushsale-v\d+.*\.css$/.test(file));
    for (const file of versionCss) {
        const base = path.basename(file);
        if (!registeredCss.includes(base)) warn('css-registry', `Versioned CSS exists but is not loaded by registry: ${base}`, file);
    }
    if (registeredCss.includes('pushsale-stability-contract.css')) pass('css-registry', 'Final stability contract CSS is registered', 'resources/js/lib/pushsaleStyleRegistry.js');
    else fail('css-registry', 'pushsale-stability-contract.css is not registered', 'resources/js/lib/pushsaleStyleRegistry.js');
}

// CSS risk scan: final version CSS should not introduce global selectors.
const cssFiles = walk('resources/css', (file) => file.endsWith('.css'));
const riskySelectorPatterns = [
    /^\s*button\b/m,
    /^\s*\.btn\b/m,
    /^\s*table\b/m,
    /^\s*select\b/m,
    /^\s*input\b/m,
    /^\s*textarea\b/m,
];
for (const file of cssFiles.filter((name) => /pushsale-v\d+.*\.css$/.test(name))) {
    const content = read(file);
    for (const pattern of riskySelectorPatterns) {
        const match = pattern.exec(content);
        if (match) warn('css-scope', `Potential broad selector '${match[0].trim()}' in versioned CSS`, `${file}:${lineOf(content, match.index)}`);
    }
}

// Naming debt: nothing may be named after a menu number.
const legacyPageFiles = walk('resources/js/pages', (file) => /^Page_\d/.test(path.basename(file)) && file.endsWith('.jsx'));
const legacyControllerFiles = walk('app/Http/Controllers', (file) => /^Page\d/.test(path.basename(file)) && file.endsWith('.php'));
if (legacyPageFiles.length) fail('naming-debt', `${legacyPageFiles.length} React page files are still named by menu number: ${legacyPageFiles.join(', ')}`, 'resources/js/pages');
else pass('naming-debt', 'No React page files named by menu number');
if (legacyControllerFiles.length) fail('naming-debt', `${legacyControllerFiles.length} controllers are still named by menu number: ${legacyControllerFiles.join(', ')}`, 'app/Http/Controllers');
else pass('naming-debt', 'No controllers named by menu number');

// Business services contract.
[
    'app/Services/Leads/LeadIngestionService.php',
    'app/Services/Leads/LandingUpsellService.php',
    'app/Services/Marketing/LandingConnectionManager.php',
    'app/Services/Operations/SaleOperationService.php',
    'app/Services/Operations/WarehouseOperationService.php',
    'app/Services/Operations/AccountingOperationService.php',
    'app/Services/Reporting/DailyReportAggregator.php',
    'app/Services/Reporting/MonthlyArchiveService.php',
    'app/Services/Pushsale/PushsaleLiveDataService.php',
    'app/Services/Pushsale/PushsalePageService.php',
].forEach((file) => exists(file) ? pass('business-service', `Business service exists: ${file}`, file) : fail('business-service', `Missing business service: ${file}`, file));

// Route coverage for critical workflows. Routes live in web.php + routes/admin + routes/roles.
const routeText = walk('routes', (file) => file.endsWith('.php')).map(read).join('\n');
[
    ['sales workspace', 'sales/workspace'],
    ['warehouse operations', 'warehouse/operations'],
    ['accounting operations', "get('accounting'"],
    ['customer profile bulk export', 'customers/export'],
    ['landing connections', 'marketing/landing-connections'],
    ['teams resource', "resource('teams'"],
    ['users quick update', 'quick-update'],
].forEach(([label, needle]) => {
    routeText.includes(needle) ? pass('routes', `Route present: ${label}`) : warn('routes', `Could not find expected route marker: ${label} (${needle})`);
});

// Test coverage landmarks.
[
    'tests/Feature/Leads/LandingConnectionFlowTest.php',
    'tests/Feature/Leads/LandingUpsellCompleteBusinessFlowTest.php',
    'tests/Feature/Shipping/WarehouseShippingFlowV16Test.php',
    'tests/Feature/Reports/HistoricalReportingV18Test.php',
    'tests/Unit/PushsalePageRegistryTest.php',
].forEach((file) => exists(file) ? pass('tests', `Test exists: ${file}`, file) : warn('tests', `Missing expected test: ${file}`, file));

const summary = checks.reduce((acc, check) => {
    acc[check.level] = (acc[check.level] || 0) + 1;
    return acc;
}, {});

const grouped = checks.reduce((acc, check) => {
    acc[check.area] ||= [];
    acc[check.area].push(check);
    return acc;
}, {});

console.log(`# Pushsale contract audit\n`);
console.log(`Summary: ${summary.pass || 0} pass, ${summary.warn || 0} warn, ${summary.fail || 0} fail.\n`);
for (const [area, items] of Object.entries(grouped)) {
    console.log(`## ${area}`);
    for (const item of items) {
        const icon = item.level === 'pass' ? '✅' : item.level === 'warn' ? '⚠️' : '❌';
        console.log(`- ${icon} ${item.message}${item.file ? ` (${item.file})` : ''}`);
    }
    console.log('');
}

process.exit((summary.fail || 0) > 0 ? 1 : 0);
