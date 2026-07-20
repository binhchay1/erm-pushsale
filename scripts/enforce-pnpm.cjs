#!/usr/bin/env node
'use strict';

const userAgent = process.env.npm_config_user_agent || '';
const execPath = process.env.npm_execpath || '';
const isPnpm = /\bpnpm\b/i.test(userAgent) || /[\\/]pnpm(?:\.cjs)?$/i.test(execPath);

if (!isPnpm) {
    console.error('\nERM Pushsale frontend is pinned to pnpm only.');
    console.error('Use: corepack enable && corepack prepare pnpm@9.15.9 --activate');
    console.error('Then run: pnpm install --frozen-lockfile && pnpm run build\n');
    process.exit(1);
}
