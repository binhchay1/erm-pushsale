#!/usr/bin/env python3
"""Build safe Pushsale page fragments from the user's numbered HTML captures.

Each ``<menu-code>.txt`` is the content of one real screen. Files with suffixes
such as ``-dialog-create`` are modal states for that same screen. The generated
fragment keeps the captured toolbar, filter layout, table headers, inline page
styles and modal form structure. Only executable DNN/ASP.NET plumbing and the
captured sample rows of the primary result table are removed.
"""
from __future__ import annotations

import re
import sys
from pathlib import Path
from typing import Iterable

from bs4 import BeautifulSoup, Tag

ACTION_WORDS = {
    "search": ("tìm kiếm", "search", "lọc"),
    "reload": ("reload", "làm mới", "tải lại"),
    "export": ("xuất excel", "export", "excel"),
    "create": ("thêm mới", "thêm tài khoản", "thêm combo", "thêm số", "khởi tạo", "tạo mới", "kết nối"),
    "save": ("lưu", "cập nhật", "hoàn thành", "đồng ý", "xác nhận"),
    "delete": ("xóa", "xoá", "reset"),
    "download_template": ("tải file mẫu", "tải mẫu", "download mẫu", "file mẫu"),
    "import": ("import dữ liệu", "nhập dữ liệu", "tải file lên", "upload data", "upload"),
    "print": ("in trang", "in báo cáo", "print"),
}
DIALOG_HINTS = ("dialog", "modal", "popup")
FORM_ONLY_CODES = {"1.2.3", "1.10", "2.6.1"}


def text_of(tag: Tag) -> str:
    return " ".join(tag.get_text(" ", strip=True).split()).lower()


def is_dialog_file(path: Path) -> bool:
    return any(hint in path.stem.lower() for hint in DIALOG_HINTS)


def primary_fragment(soup: BeautifulSoup, dialog: bool) -> Tag:
    if dialog:
        return soup.select_one(".modal-content") or soup.select_one(".modal") or soup.body or soup
    return (
        soup.select_one(".content-wrapper")
        or soup.select_one("[id$=_ModuleContent]")
        or soup.select_one(".contentPane")
        or soup.body
        or soup
    )


def nearest_table_is(row: Tag, table: Tag) -> bool:
    return row.find_parent("table") is table


def table_score(table: Tag) -> int:
    if table.find_parent(class_=re.compile(r"modal|datepicker|calendar", re.I)):
        return -10000
    rows = [row for row in table.find_all("tr") if nearest_table_is(row, table)]
    cells = [cell for cell in table.find_all(["th", "td"]) if cell.find_parent("table") is table]
    headers = [cell for cell in table.find_all("th") if cell.find_parent("table") is table]
    classes = " ".join(table.attrs.get("class", []))
    score = len(rows) * 5 + len(cells) + len(headers) * 8
    if re.search(r"mygrid|gridview|tabledata|table-sale|table-multi-select", classes, re.I):
        score += 100
    if table.find(attrs={"id": re.compile(r"gv|grid|report", re.I)}):
        score += 80
    # Small summary cards should not win over the real result grid.
    if len(cells) <= 4:
        score -= 100
    return score


def clean_node_tree(fragment: Tag) -> None:
    for node in list(fragment.select("script, link, meta, noscript, iframe, object, embed, svg script")):
        node.decompose()

    selectors = [
        "[id^=sbz]", "[id*=subiz]", "[class*=subiz]", "[id*=cpsox]",
        ".RadWindow", ".dnnFormPopup", ".dnnModal", ".ui-dialog", ".modal-backdrop",
        "input[type=hidden]", "textarea[style*='display: none']",
    ]
    for selector in selectors:
        for node in list(fragment.select(selector)):
            node.decompose()

    for style in fragment.select("style"):
        css = style.string or style.get_text() or ""
        css = re.sub(r"(^|})\s*(html|body)(\s*[^,{]*)?\s*\{[^}]*\}", r"\1", css, flags=re.I | re.S)
        css = re.sub(r"position\s*:\s*fixed", "position: absolute", css, flags=re.I)
        css = css.replace(".content-wrapper", ".pushsale-template-fragment")
        style.string = css

    for tag in fragment.find_all(True):
        for attr in list(tag.attrs):
            if attr.lower().startswith("on"):
                del tag.attrs[attr]
        href = tag.attrs.get("href")
        if isinstance(href, str) and (href.lower().strip().startswith("javascript:") or "__dopostback" in href.lower()):
            tag.attrs["href"] = "#"
        if tag.attrs.get("action"):
            tag.attrs["action"] = "#"
        tag.attrs.pop("target", None)

    # Select2's generated copy needs its old JS; keep the original <select> instead.
    for container in list(fragment.select(".select2-container")):
        container.decompose()
    for select in fragment.select("select"):
        style = re.sub(r"display\s*:\s*none\s*;?", "", str(select.attrs.get("style", "")), flags=re.I)
        select.attrs["style"] = style
        classes = list(select.attrs.get("class", []))
        if "pushsale-native-select" not in classes:
            classes.append("pushsale-native-select")
        select.attrs["class"] = classes
        select.attrs.pop("tabindex", None)

    for tag in fragment.select("a, button, input[type=button], input[type=submit], .btn"):
        label = text_of(tag) or str(tag.attrs.get("value", "")).strip().lower()
        for action, needles in ACTION_WORDS.items():
            if any(needle in label for needle in needles):
                tag.attrs["data-pushsale-action"] = action
                break
        tag.attrs["role"] = "button"

    for tag in fragment.select("[id*=btnTaiMau], [id*=btnDownloadTemplate]"):
        tag.attrs["data-pushsale-action"] = "download_template"
        tag.attrs["role"] = "button"
    for tag in fragment.select("[id*=btnUpload], [id*=btnImport]"):
        tag.attrs["data-pushsale-action"] = "import"
        tag.attrs["role"] = "button"

    for inp in fragment.select("input[type=text], input[type=number], input[type=date], input[type=datetime-local], input[type=tel], textarea"):
        classes = list(inp.attrs.get("class", []))
        if "pushsale-native-input" not in classes:
            classes.append("pushsale-native-input")
        inp.attrs["class"] = classes

    for node in fragment.select(".content-wrapper"):
        node.attrs["style"] = ""
    for node in fragment.select(".contentPane, [id$=_ContentPane], [id$=_ModuleContent]"):
        classes = list(node.attrs.get("class", []))
        if "pushsale-template-inner" not in classes:
            classes.append("pushsale-template-inner")
        node.attrs["class"] = classes


def prepare_primary_grid(fragment: Tag, soup: BeautifulSoup, dialog: bool, code: str) -> None:
    if dialog or code in FORM_ONLY_CODES:
        return

    top_tables = [
        table for table in fragment.find_all("table")
        if table.find_parent("table") is None and table.find_parent(class_=re.compile(r"modal|datepicker|calendar", re.I)) is None
    ]
    # Trang 1.11 có một bảng danh sách rất ngắn và một bảng form cập nhật dài hơn.
    # Chọn theo tiêu đề nghiệp vụ thay vì điểm số để không biến form thành result grid.
    if code == "1.11":
        facebook_tables = [
            table for table in top_tables
            if "fanpage" in text_of(table) and "fb creator" in text_of(table) and "pushsale user" in text_of(table)
        ]
        if facebook_tables:
            top_tables = facebook_tables
    if not top_tables:
        anchor = soup.new_tag("div")
        anchor.attrs["data-pushsale-grid-fallback"] = "primary"
        fragment.append(anchor)
        return

    primary = max(top_tables, key=table_score)
    rows = [row for row in primary.find_all("tr") if nearest_table_is(row, primary)]
    if not rows:
        return

    header_rows: list[Tag] = []
    for index, row in enumerate(rows):
        direct_headers = row.find_all("th", recursive=False)
        if direct_headers:
            header_rows.append(row)
            continue
        if index == 0:
            header_rows.append(row)
        break

    # Preserve colgroups/caption and the exact captured header rows, remove captured data.
    # Extract headers before decomposing their original tbody/thead, otherwise BeautifulSoup
    # invalidates those Tag objects together with the parent section.
    extracted_headers = [row.extract() for row in header_rows if row.parent is not None]
    for section in list(primary.find_all(["thead", "tbody", "tfoot"], recursive=False)):
        section.decompose()
    for row in rows:
        if row.parent is not None:
            row.decompose()

    # Customer profile keeps the captured page but ERM adds the two requested
    # independent columns (Địa chỉ, Tin nhắn) and a dedicated action cell for
    # operation history/internal chat/Pancake/purchase history/upsale.
    if code == "4.2":
        visible_rows = [
            row for row in extracted_headers
            if "hidden" not in list(row.attrs.get("class", []))
        ]
        if visible_rows:
            visible_header = visible_rows[-1]
            direct_cells = visible_header.find_all(["th", "td"], recursive=False)
            message_header = next(
                (cell for cell in direct_cells if text_of(cell) == "tin nhắn"),
                None,
            )
            if message_header is not None:
                message_header.clear()
                message_header.append("Địa chỉ")
                message_header.attrs["class"] = list(message_header.attrs.get("class", []))
                new_message = soup.new_tag("th")
                new_message.attrs["class"] = ["text-center", "no-wrap"]
                new_message.attrs["style"] = "top: 0.5px; min-width: 130px;"
                new_message.string = "Tin nhắn"
                message_header.insert_after(new_message)

            action_header = soup.new_tag("th")
            action_header.attrs["class"] = ["text-center", "no-wrap", "pushsale-action-header"]
            action_header.attrs["style"] = "top: 0.5px; min-width: 112px;"
            action_header.attrs["title"] = "Thao tác"
            visible_header.append(action_header)

    thead = soup.new_tag("thead")
    for row in extracted_headers:
        classes = list(row.attrs.get("class", []))
        if "pushsale-captured-header-row" not in classes:
            classes.append("pushsale-captured-header-row")
        row.attrs["class"] = classes
        thead.append(row)

    tbody = soup.new_tag("tbody")
    tbody.attrs["data-pushsale-grid-anchor"] = "primary"
    primary.append(thead)
    primary.append(tbody)
    classes = list(primary.attrs.get("class", []))
    for cls in ("pushsale-data-table", "table", "table-bordered"):
        if cls not in classes:
            classes.append(cls)
    primary.attrs["class"] = classes
    primary.attrs["data-pushsale-grid-table"] = "primary"

    pagination = soup.new_tag("div")
    pagination.attrs["data-pushsale-pagination-anchor"] = "primary"
    primary.insert_after(pagination)

    # Page captures often contain hidden modals. Their dedicated suffix files are used instead.
    for modal in list(fragment.select(".modal")):
        modal.decompose()


def sanitize(path: Path) -> str:
    raw = path.read_text(encoding="utf-8", errors="ignore")
    soup = BeautifulSoup(raw, "html.parser")
    dialog = is_dialog_file(path)
    fragment = primary_fragment(soup, dialog)
    clean_node_tree(fragment)
    prepare_primary_grid(fragment, soup, dialog, path.stem.split("-", 1)[0])

    wrapper = soup.new_tag("div")
    wrapper.attrs["class"] = [
        "pushsale-template-fragment",
        "pushsale-dialog-fragment" if dialog else "pushsale-page-fragment",
    ]
    wrapper.attrs["data-template-code"] = path.stem
    wrapper.append(fragment.extract())
    return str(wrapper)


def iter_sources(folder: Path) -> Iterable[Path]:
    return sorted(path for path in folder.glob("*.txt") if path.is_file())


def main() -> int:
    if len(sys.argv) != 3:
        print("Usage: build_pushsale_templates.py <source-folder> <output-folder>", file=sys.stderr)
        return 2
    source = Path(sys.argv[1]).resolve()
    output = Path(sys.argv[2]).resolve()
    output.mkdir(parents=True, exist_ok=True)

    manifest: list[str] = []
    for path in iter_sources(source):
        (output / f"{path.stem}.html").write_text(sanitize(path), encoding="utf-8")
        manifest.append(path.stem)

    (output / "manifest.json").write_text(
        "[\n" + ",\n".join(f'  "{name}"' for name in manifest) + "\n]\n",
        encoding="utf-8",
    )
    print(f"Wrote {len(manifest)} sanitized templates to {output}")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
