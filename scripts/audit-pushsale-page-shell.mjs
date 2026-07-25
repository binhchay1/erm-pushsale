#!/usr/bin/env node
import fs from 'node:fs';
import path from 'node:path';
import { execFileSync } from 'node:child_process';

const root = process.cwd();
const pageCodes = JSON.parse(execFileSync('php', [
  '-r',
  'echo json_encode(array_keys(require "config/pushsale_pages.php"), JSON_UNESCAPED_UNICODE);',
], { cwd: root, encoding: 'utf8' }));
const pageConfig = JSON.parse(execFileSync('php', [
  '-r',
  '$p=require "config/pushsale_pages.php"; echo json_encode($p, JSON_UNESCAPED_UNICODE);',
], { cwd: root, encoding: 'utf8' }));

const problems = [];
for (const code of pageCodes) {
  if (pageConfig[code]?.requires_template === false) continue;
  const templateCode = pageConfig[code]?.template_alias || code;
  const templatePath = path.join(root, 'public/pushsale-templates', `${templateCode}.html`);
  if (!fs.existsSync(templatePath)) {
    problems.push(`${code}: missing template ${templateCode}.html`);
    continue;
  }
  const html = fs.readFileSync(templatePath, 'utf8');
  if (!/m-header-wrap|content-header|psm-topbar|psr-topbar|ps-feature-header-wrap/.test(html)) {
    problems.push(`${code}: template has no recognizable Pushsale page header`);
  }
  if (/padding-top\s*:\s*(?:4[5-9]|[5-9]\d|[1-9]\d{2,})px/i.test(html)) {
    problems.push(`${code}: template still contains large inline padding-top spacer; runtime normalizer will hide it`);
  }
}

const numberedControllers = fs.readdirSync(path.join(root, 'app/Http/Controllers/Admin/Pushsale/Pages'))
  .filter((name) => /^Page\d/.test(name));
const numberedComponents = fs.readdirSync(path.join(root, 'resources/js/pages/Pushsale/Pages'))
  .filter((name) => /^Page_\d/.test(name));

console.log(`Pushsale pages: ${pageCodes.length}`);
console.log(`Legacy numbered controllers still present: ${numberedControllers.length}`);
console.log(`Legacy numbered components still present: ${numberedComponents.length}`);

if (problems.length) {
  console.log('\nShell audit notes:');
  problems.forEach((problem) => console.log(`- ${problem}`));
}

// Notes are not fatal because legacy templates are normalized at runtime. The
// route/name audit and PHPUnit coverage tests are the fatal guards.
process.exit(0);
