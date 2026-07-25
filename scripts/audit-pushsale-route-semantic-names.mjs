#!/usr/bin/env node
import { execFileSync } from 'node:child_process';

const routes = JSON.parse(execFileSync('php', [
  '-r',
  'echo json_encode(require "config/pushsale_routes.php", JSON_UNESCAPED_UNICODE);',
], { encoding: 'utf8' }));

const bad = [];
for (const [code, route] of Object.entries(routes)) {
  const uri = String(route.uri || '');
  const name = String(route.name || '');
  if (!uri || !name) bad.push(`${code}: missing uri/name`);
  if (/pages\//.test(uri)) bad.push(`${code}: URI still uses generic pages path: ${uri}`);
  if (new RegExp(`(^|/)${code.replaceAll('.', '[-_.]')}($|/)`).test(uri)) bad.push(`${code}: URI exposes menu number: ${uri}`);
  if (/page[_\-.]?\d/i.test(name)) bad.push(`${code}: route name exposes page number: ${name}`);
}

if (bad.length) {
  console.error('Semantic route audit failed:');
  bad.forEach((line) => console.error(`- ${line}`));
  process.exit(1);
}

console.log(`Semantic route audit passed for ${Object.keys(routes).length} routes.`);
