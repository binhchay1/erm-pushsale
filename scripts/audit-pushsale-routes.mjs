#!/usr/bin/env node
import fs from 'node:fs';
import path from 'node:path';

const root = process.cwd();
const checks = [
  'config/pushsale_navigation.php',
  'config/staging_test.php',
  'app/Console/Commands/BulkUiFlowCommand.php',
  'resources/js/lib/page-guides.js',
];

const bannedInMenu = [
  /\/admin\/reports\/extra\/sale-\d+\b/g,
  /\/admin\/reports\/extra\/kho-\d+\b/g,
  /\/admin\/reports\/extra\/marketing-\d+\b/g,
  /\/admin\/reports\/extra\/sale-(?:work|kpi|closing-summary|revenue|revenue-detail|revenue-v2|appointments)\b/g,
];

const required = [
  '/admin/sales/reports/sale-kpi',
  '/admin/sales/reports/closing-summary',
  '/admin/sales/reports/work',
  '/admin/sales/reports/revenue-detail',
  '/admin/sales/reports/revenue',
  '/admin/sales/reports/revenue-v2',
  '/admin/sales/reports/appointments',
  '/admin/reports/system-business',
  '/admin/marketing/reports/revenue-detail',
  '/admin/marketing/reports/revenue',
  '/admin/marketing/reports/revenue-v2',
  '/admin/warehouse/reports/revenue',
  '/admin/warehouse/reports/revenue-v2',
];

const failures = [];

for (const rel of checks) {
  const file = path.join(root, rel);
  if (!fs.existsSync(file)) continue;
  const body = fs.readFileSync(file, 'utf8');
  for (const rx of bannedInMenu) {
    const matches = [...body.matchAll(rx)];
    for (const match of matches) {
      failures.push(`${rel}: banned route '${match[0]}'`);
    }
  }
}

const navPath = path.join(root, 'config/pushsale_navigation.php');
const navBody = fs.readFileSync(navPath, 'utf8');
for (const url of required) {
  if (!navBody.includes(url)) {
    failures.push(`config/pushsale_navigation.php: missing canonical route '${url}'`);
  }
}

const routeConfig = fs.readFileSync(path.join(root, 'config/pushsale_report_routes.php'), 'utf8');
for (const url of required) {
  if (!routeConfig.includes(url)) {
    failures.push(`config/pushsale_report_routes.php: missing canonical route '${url}'`);
  }
}

if (failures.length > 0) {
  console.error('Pushsale route audit failed:');
  for (const failure of failures) console.error(`- ${failure}`);
  process.exit(1);
}

console.log('Pushsale route audit passed.');
