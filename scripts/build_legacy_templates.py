#!/usr/bin/env python3
"""Sanitize Pushsale HTML captures into safe, reusable UI fragments.

Usage:
  python scripts/build_legacy_templates.py /path/to/template-folder public/legacy-templates

The source captures are user-provided browser HTML. This script removes executable
content, DNN postback handlers, sample result grids and third-party widgets while
preserving the exact toolbar/filter/form DOM and inline page CSS.
"""
from __future__ import annotations

import re
import sys
from pathlib import Path
from typing import Iterable

from bs4 import BeautifulSoup, Tag

ACTION_WORDS = {
    "search": ("tìm kiếm", "search"),
    "reload": ("reload", "làm mới", "tải lại"),
    "export": ("xuất excel", "export"),
    "create": ("thêm mới", "thêm tài khoản", "thêm combo", "thêm số", "khởi tạo", "kết nối"),
    "save": ("lưu", "cập nhật", "hoàn thành", "đồng ý"),
    "delete": ("xóa", "reset"),
    "print": ("in",),
}

DIALOG_HINTS = ("dialog", "modal", "popup")


def text_of(tag: Tag) -> str:
    return " ".join(tag.get_text(" ", strip=True).split()).lower()


def is_dialog_file(path: Path) -> bool:
    lower = path.stem.lower()
    return any(hint in lower for hint in DIALOG_HINTS)


def primary_fragment(soup: BeautifulSoup) -> Tag:
    if is_dialog_file(Path(getattr(soup, "_source_path", "dialog"))):
        return soup.select_one(".modal-content") or soup.select_one(".modal") or soup.body or soup

    return (
        soup.select_one(".content-wrapper")
        or soup.select_one("[id$=_ModuleContent]")
        or soup.select_one(".contentPane")
        or soup.body
        or soup
    )


def clean_node_tree(fragment: Tag) -> None:
    for node in list(fragment.select("script, link, meta, noscript, iframe, object, embed, svg script")):
        node.decompose()

    # Remove chat/support/analytics overlays and hidden DNN plumbing.
    selectors = [
        "[id^=sbz]", "[id*=subiz]", "[class*=subiz]", "[id*=cpsox]",
        ".RadWindow", ".dnnFormPopup", ".dnnModal", ".ui-dialog", ".modal-backdrop",
        "input[type=hidden]", "textarea[style*='display: none']",
    ]
    for selector in selectors:
        for node in list(fragment.select(selector)):
            node.decompose()

    # Keep page-specific style blocks, but neutralize global/body rules that can leak.
    for style in fragment.select("style"):
        css = style.string or style.get_text() or ""
        css = re.sub(r"(^|})\s*(html|body)(\s*[^,{]*)?\s*\{[^}]*\}", r"\1", css, flags=re.I | re.S)
        css = css.replace("position: fixed", "position: static")
        style.string = css

    # DNN/ASP.NET event handlers must never execute in the new application.
    for tag in fragment.find_all(True):
        for attr in list(tag.attrs):
            if attr.lower().startswith("on"):
                del tag.attrs[attr]
        href = tag.attrs.get("href")
        if isinstance(href, str) and href.lower().strip().startswith("javascript:"):
            tag.attrs["href"] = "#"
        action = tag.attrs.get("action")
        if action:
            tag.attrs["action"] = "#"
        tag.attrs.pop("target", None)

    # Select2's generated presentation needs its old JS. Replace it with the original
    # select element, which is then styled by the new theme and remains fully usable.
    for container in list(fragment.select(".select2-container")):
        container.decompose()
    for select in fragment.select("select"):
        style = str(select.attrs.get("style", ""))
        style = re.sub(r"display\s*:\s*none\s*;?", "", style, flags=re.I)
        select.attrs["style"] = style
        classes = list(select.attrs.get("class", []))
        if "legacy-select" not in classes:
            classes.append("legacy-select")
        select.attrs["class"] = classes
        select.attrs.pop("tabindex", None)

    # Normalize controls so the React event bridge can understand the original buttons.
    for tag in fragment.select("a.btn, button, input[type=button], input[type=submit]"):
        label = text_of(tag) or str(tag.attrs.get("value", "")).strip().lower()
        action = None
        for key, needles in ACTION_WORDS.items():
            if any(needle in label for needle in needles):
                action = key
                break
        if action:
            tag.attrs["data-legacy-action"] = action
        tag.attrs["role"] = "button"

    for inp in fragment.select("input[type=text], input[type=number], input[type=date], textarea"):
        classes = list(inp.attrs.get("class", []))
        if "legacy-input" not in classes:
            classes.append("legacy-input")
        inp.attrs["class"] = classes

    # Remove nested shell geometry. The application's own header/sidebar owns layout.
    for node in fragment.select(".content-wrapper"):
        node.attrs["style"] = ""
    for node in fragment.select(".contentPane, [id$=_ContentPane], [id$=_ModuleContent]"):
        classes = list(node.attrs.get("class", []))
        if "legacy-template-inner" not in classes:
            classes.append("legacy-template-inner")
        node.attrs["class"] = classes


def table_score(table: Tag) -> int:
    if table.find_parent(class_=re.compile(r"modal|datepicker|calendar", re.I)):
        return -1000
    rows = table.find_all("tr")
    cells = table.find_all(["th", "td"])
    header_cells = table.find_all("th")
    classes = " ".join(table.attrs.get("class", []))
    score = len(rows) * 2 + len(cells) + len(header_cells) * 4
    if re.search(r"mygrid|gridview|table", classes, re.I):
        score += 30
    if table.find(attrs={"id": re.compile(r"gv|grid|report", re.I)}):
        score += 25
    return score


def replace_primary_grid(fragment: Tag, soup: BeautifulSoup, dialog: bool) -> None:
    if dialog:
        return
    tables = fragment.find_all("table")
    if not tables:
        anchor = soup.new_tag("div")
        anchor.attrs["data-legacy-grid-anchor"] = "1"
        fragment.append(anchor)
        return

    primary = max(tables, key=table_score)
    anchor = soup.new_tag("div")
    anchor.attrs["data-legacy-grid-anchor"] = "1"
    primary.replace_with(anchor)

    # Remove hidden modal markup from page captures; dedicated dialog captures are loaded
    # separately and displayed in the React modal.
    for modal in list(fragment.select(".modal")):
        modal.decompose()


def sanitize(path: Path) -> str:
    raw = path.read_text(encoding="utf-8", errors="ignore")
    soup = BeautifulSoup(raw, "html.parser")
    soup._source_path = str(path)
    fragment = primary_fragment(soup)
    dialog = is_dialog_file(path)
    clean_node_tree(fragment)
    replace_primary_grid(fragment, soup, dialog)

    wrapper = soup.new_tag("div")
    wrapper.attrs["class"] = ["legacy-template-fragment", "legacy-dialog-fragment" if dialog else "legacy-page-fragment"]
    wrapper.attrs["data-template-code"] = path.stem
    # Move, rather than clone, to retain BeautifulSoup's normalized tree.
    wrapper.append(fragment.extract())
    return str(wrapper)


def iter_sources(folder: Path) -> Iterable[Path]:
    return sorted(p for p in folder.glob("*.txt") if p.is_file())


def main() -> int:
    if len(sys.argv) != 3:
        print("Usage: build_legacy_templates.py <source-folder> <output-folder>", file=sys.stderr)
        return 2
    source = Path(sys.argv[1]).resolve()
    output = Path(sys.argv[2]).resolve()
    output.mkdir(parents=True, exist_ok=True)

    manifest: list[str] = []
    for path in iter_sources(source):
        html = sanitize(path)
        target = output / f"{path.stem}.html"
        target.write_text(html, encoding="utf-8")
        manifest.append(path.stem)

    (output / "manifest.json").write_text(
        "[\n" + ",\n".join(f'  "{name}"' for name in manifest) + "\n]\n",
        encoding="utf-8",
    )
    print(f"Wrote {len(manifest)} sanitized templates to {output}")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
