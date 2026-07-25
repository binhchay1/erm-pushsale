#!/usr/bin/env node
import fs from 'node:fs';
import path from 'node:path';

const root = process.cwd();
const nav = fs.readFileSync(path.join(root, 'config/pushsale_navigation.php'), 'utf8');
const failures = [];

const requiredPairs = [
  ['8.1.1 hourly legacy', "'url' => '/ld/thong-ke'"],
  ['8.1.3 upsale legacy', "'url' => '/ld/thong-ke/bao-cao-up-sale?menu=8.1.3'"],
  ['8.3.2 pending export', "'url' => '/admin/warehouse/reports/pending-export'"],
  ['8.3.3 warehouse movement summary', "'url' => '/admin/warehouse/reports/movement-summary'"],
  ['8.5.9 power dashboard', "'url' => '/admin/reports/power-dashboard'"],
  ['8.7.1 customer multidimensional', "'url' => '/admin/customers/reports/multidimensional'"],
];

for (const [label, needle] of requiredPairs) {
  if (!nav.includes(needle)) failures.push(`${label}: missing ${needle}`);
}

const wrongHourlyTitles = [
  'Báo cáo giá vốn sản phẩm',
  'Thống kê khách hàng đa chiều',
];
for (const title of wrongHourlyTitles) {
  const start = nav.indexOf(`'title' => '${title}'`);
  if (start >= 0) {
    const chunk = nav.slice(start, start + 220);
    if (chunk.includes("'url' => '/admin/reports/hourly'")) {
      failures.push(`${title}: still points to /admin/reports/hourly`);
    }
  }
}

if (failures.length) {
  console.error('Pushsale menu 8 report audit failed:');
  for (const failure of failures) console.error(`- ${failure}`);
  process.exit(1);
}
console.log('Pushsale menu 8 report audit passed.');
