#!/usr/bin/env python3
"""Static UI contract checks for captured Pushsale templates and CSS entries."""
from __future__ import annotations

import re
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
TEMPLATES = ROOT / "public" / "pushsale-templates"
errors: list[str] = []

for path in sorted(TEMPLATES.glob("*.html")):
    text = path.read_text(encoding="utf-8", errors="ignore")
    code_match = re.search(r'data-template-code=["\']([^"\']+)', text)
    code = code_match.group(1) if code_match else path.stem
    for index, match in enumerate(re.finditer(r"<style\b[^>]*>(.*?)</style>", text, re.I | re.S), start=1):
        css = match.group(1).strip()
        if css and not css.startswith("@scope"):
            errors.append(f"{path.name}: style #{index} is not scoped to data-template-code={code}")
    if re.search(r"<script\b", text, re.I):
        errors.append(f"{path.name}: executable script tag remains")
    if re.search(r'<(?:div|span)\b[^>]*class=["\'][^"\']*\b(?:chosen-container|select2-container)\b', text, re.I):
        errors.append(f"{path.name}: generated Select2/Chosen widget remains")
    if re.search(r'<option\b[^>]*>[^<]*(?:ttgroup\d*\.|tt\.sale\d+|@saleops\.local)', text, re.I):
        errors.append(f"{path.name}: captured employee option remains")
    if re.search(r'\b(?:Lại Giang|Phạm Lý|Phan Văn Minh|ttgroup2\.marketing\d+|tt\.sale\d+)\b', text, re.I):
        errors.append(f"{path.name}: captured Pushsale tenant data remains")

login_template = (TEMPLATES / "1.7.1.html").read_text(encoding="utf-8", errors="ignore")
if 'data-pushsale-login-user-summary="1"' not in login_template:
    errors.append("1.7.1.html does not expose the dynamic login-user anchor")

app_css = (ROOT / "resources/css/app.css").read_text(encoding="utf-8")
for forbidden in ("pushsale-layout.css", "public-shell.css", "pushsale-v12-fixes.css", "pushsale-v13-fixes.css"):
    if forbidden in app_css:
        errors.append(f"app.css still imports shell-specific stylesheet: {forbidden}")

pushsale_css = (ROOT / "resources/css/pushsale.css").read_text(encoding="utf-8")
if "pushsale-system-v17.css" not in pushsale_css:
    errors.append("pushsale.css does not load the V17 final contract")

v17_css = (ROOT / "resources/css/pushsale-system-v17.css").read_text(encoding="utf-8")
for contract in (".pushsale-filter-row", ".pushsale-row-actions", ".ps-modal-surface", ".pushsale-login-user-summary"):
    if contract not in v17_css:
        errors.append(f"V17 CSS contract missing: {contract}")

if errors:
    print("UI CONTRACT AUDIT: FAIL")
    print("\n".join(f"- {error}" for error in errors))
    sys.exit(1)

print(f"UI CONTRACT AUDIT: PASS ({len(list(TEMPLATES.glob('*.html')))} templates)")
