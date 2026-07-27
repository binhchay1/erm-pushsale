import { Head, router } from '@inertiajs/react';
import { useRef, useState } from 'react';
import { toast } from 'sonner';

import { PushsaleSearchButton } from '@/components/actions/PushsaleSearchButton';
import { PageHeader } from '@/components/layout/PageHeader';
import AppLayout from '@/layouts/AppLayout';

const STATUS_OPTIONS = [
    { value: '', label: '--Chọn trạng thái--' },
    { value: 'HOP_LE', label: 'Hợp lệ' },
    { value: 'KO_HOP_LE', label: 'Không hợp lệ' },
    { value: 'SODATONTAI_', label: 'Đã tồn tại trên hệ thống' },
    { value: 'TRUNGSO_', label: 'Trùng số hệ thống' },
    { value: 'TRUNGSOEXCEL_', label: 'Trùng số Excel' },
    { value: 'KHACHCU_', label: 'Khách cũ' },
];

function pageUrls(schema = {}) {
    const path = typeof window !== 'undefined' ? window.location.pathname : '/admin/leads/import';
    const importUrl = schema.import_url
        || (path.startsWith('/marketing/') ? '/marketing/leads/import' : '/admin/leads/import');
    const templateUrl = schema.template_url
        || (path.startsWith('/marketing/') ? '/marketing/leads/import-template' : '/admin/leads/import-template');
    const listUrl = schema.routeUrl || path;

    return { importUrl, templateUrl, listUrl };
}

function ImportGuideDialog({ open, onClose, templateUrl }) {
    if (!open) return null;

    return (
        <div className="ps-import-guide-modal" role="dialog" aria-modal="true" aria-label="Hướng dẫn import contact" onClick={onClose}>
            <div className="ps-import-guide-dialog" onClick={(event) => event.stopPropagation()}>
                <div className="ps-import-guide-header">
                    <h3>Hướng dẫn import contact</h3>
                    <button type="button" onClick={onClose} aria-label="Đóng"><i className="fa fa-times" /></button>
                </div>
                <div className="ps-import-guide-body">
                    <div className="ps-import-guide-note">
                        <b>Quy trình chuẩn</b>
                        <ol>
                            <li>Tải file mẫu Excel từ nút <b>B1. Tải mẫu Excel</b>.</li>
                            <li>Nhập dữ liệu theo đúng các cột trong file mẫu, không đổi tên cột bắt buộc.</li>
                            <li>Chọn file ở bước <b>B2</b>, sau đó bấm <b>Upload</b> ở bước <b>B3</b>.</li>
                            <li>Bật <b>Kiểm trùng hệ thống</b> khi cần so số điện thoại với dữ liệu đang có trong hệ thống trước khi chia sale.</li>
                            <li>Sau khi import, vào <b>Lịch sử import</b> để kiểm tra số dòng hợp lệ, số trùng, số lỗi và trạng thái xử lý.</li>
                        </ol>
                    </div>
                    <div className="ps-import-guide-grid">
                        <section>
                            <h4>Quy tắc dữ liệu</h4>
                            <ul>
                                <li>File nên dưới 5.000 dòng mỗi lần import để queue xử lý ổn định.</li>
                                <li>Số điện thoại là khóa chống trùng chính; hệ thống sẽ chuẩn hóa số trước khi ghi nhận.</li>
                                <li>Các cột sản phẩm, nguồn dữ liệu, ghi chú, địa chỉ sẽ được map vào lead/customer/order draft theo cấu hình hiện tại.</li>
                                <li>Dòng lỗi sẽ được lưu vào lịch sử import để rà soát, không ghi đè dữ liệu hợp lệ.</li>
                            </ul>
                        </section>
                        <section>
                            <h4>Liên kết nghiệp vụ</h4>
                            <ul>
                                <li>Lead hợp lệ đi vào hàng chờ phân bổ data.</li>
                                <li>Nếu có sản phẩm/nguồn dữ liệu, luật phân quyền Marketing/Sale theo sản phẩm vẫn được áp dụng.</li>
                                <li>Lead trùng hoặc khách cũ được đánh dấu để review, tránh chia trùng cho nhiều sale.</li>
                                <li>Import không tự chốt đơn; sale vẫn phải tác nghiệp và chốt theo luồng hiện tại.</li>
                            </ul>
                        </section>
                    </div>
                    {templateUrl ? (
                        <div className="ps-import-guide-actions">
                            <a className="btn btn-sm btn-primary" href={templateUrl}><i className="fa fa-cloud-download" /> Tải file mẫu</a>
                        </div>
                    ) : null}
                </div>
            </div>
        </div>
    );
}

function postImport(url, formData) {
    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
    return fetch(url, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'X-CSRF-TOKEN': csrf,
            'X-Requested-With': 'XMLHttpRequest',
        },
        body: formData,
    }).then(async (response) => {
        if (!response.ok) {
            const body = await response.json().catch(() => ({}));
            throw new Error(body.message || Object.values(body.errors ?? {}).flat().join(' ') || 'Không thể nhập dữ liệu.');
        }
        return response.json().catch(() => ({}));
    });
}

export default function LeadImportPage({ schema = {}, routeUrl }) {
    const pageCode = String(schema.code ?? '1.10');
    const { importUrl, templateUrl, listUrl } = pageUrls({ ...schema, routeUrl });
    const fileRef = useRef(null);

    const params = typeof window !== 'undefined'
        ? Object.fromEntries(new URLSearchParams(window.location.search).entries())
        : {};

    const [status, setStatus] = useState(params.status ?? '');
    const [keyword, setKeyword] = useState(params.q ?? params.search ?? '');
    const [fileName, setFileName] = useState('');
    const [checkDuplicates, setCheckDuplicates] = useState(false);
    const [uploading, setUploading] = useState(false);
    const [guideOpen, setGuideOpen] = useState(false);

    const applyFilters = () => {
        const payload = {};
        if (status) payload.status = status;
        if (keyword.trim()) payload.q = keyword.trim();
        router.get(listUrl, payload, { preserveState: true, preserveScroll: true, replace: true });
    };

    const onFileChange = (event) => {
        const file = event.target.files?.[0];
        setFileName(file?.name || '');
    };

    const pickFile = () => fileRef.current?.click();

    const upload = async () => {
        const file = fileRef.current?.files?.[0];
        if (!file) {
            toast.error('Vui lòng chọn file Excel cần import.');
            return;
        }

        const formData = new FormData();
        formData.append('file', file);
        if (checkDuplicates) formData.append('check_duplicates', '1');

        setUploading(true);
        toast.loading('Đang import contact…', { id: 'lead-import', duration: 12000 });
        try {
            await postImport(importUrl, formData);
            toast.success('Đã gửi file import.', { id: 'lead-import', duration: 5000 });
            setFileName('');
            if (fileRef.current) fileRef.current.value = '';
            router.reload({ preserveScroll: true });
        } catch (error) {
            toast.error(error.message || 'Không thể import.', { id: 'lead-import', duration: 8000 });
        } finally {
            setUploading(false);
        }
    };

    return (
        <AppLayout activeMenuCode={pageCode}>
            <Head title={schema.title || 'Import contact'} />
            <div className="ps-lead-import-page" data-page-code={pageCode}>
                <PageHeader
                    title={(
                        <>
                            <span>{schema.title || 'Import contact'}</span>
                            <button
                                type="button"
                                className="ps-lead-import-guide-link"
                                onClick={() => setGuideOpen(true)}
                            >
                                (Xem hướng dẫn)
                            </button>
                        </>
                    )}
                    pageCode={pageCode}
                    className="ps-lead-import-header"
                    actions={(
                        <div className="ps-lead-import-search ps-lead-import-search--with-filters">
                            <select
                                className="ps-lead-import-status"
                                value={status}
                                onChange={(event) => setStatus(event.target.value)}
                            >
                                {STATUS_OPTIONS.map((option) => (
                                    <option key={option.value || 'all'} value={option.value}>{option.label}</option>
                                ))}
                            </select>
                            <input
                                type="text"
                                className="form-control"
                                value={keyword}
                                placeholder="Từ khóa"
                                onChange={(event) => setKeyword(event.target.value)}
                                onKeyDown={(event) => event.key === 'Enter' && applyFilters()}
                            />
                            <PushsaleSearchButton onClick={applyFilters} label="Tìm kiếm" />
                        </div>
                    )}
                />

                <div className="ps-lead-import-body">
                    <section className="ps-lead-import-upload">
                        <div className="ps-lead-import-upload-title">Upload data</div>
                        <table className="ps-lead-import-table">
                            <tbody>
                                <tr>
                                    <td className="ps-lead-import-step">B1: Tải mẫu</td>
                                    <td>
                                        <a className="ps-lead-import-link" href={templateUrl}>
                                            <i className="fa fa-cloud-download" /> B1. Tải mẫu Excel
                                        </a>
                                    </td>
                                </tr>
                                <tr>
                                    <td className="ps-lead-import-step">B2: Chọn file</td>
                                    <td>
                                        <div className="ps-lead-import-file">
                                            <input
                                                ref={fileRef}
                                                type="file"
                                                accept=".xls,.xlsx,.csv,.txt"
                                                className="hidden"
                                                onChange={onFileChange}
                                            />
                                            <button type="button" className="ps-lead-import-file-name" onClick={pickFile}>
                                                {fileName || 'Chọn file...'}
                                            </button>
                                            <button
                                                type="button"
                                                className="ps-lead-import-file-btn"
                                                onClick={pickFile}
                                                title="Chọn file"
                                            >
                                                <i className="fa fa-cloud-upload" />
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <tr>
                                    <td className="ps-lead-import-step">B3: Upload data</td>
                                    <td className="ps-lead-import-upload-row">
                                        <button
                                            type="button"
                                            className="ps-lead-import-link"
                                            onClick={upload}
                                            disabled={uploading}
                                        >
                                            <i className="fa fa-cloud-upload" /> Upload
                                        </button>
                                        <label className="ps-lead-import-check">
                                            <input
                                                type="checkbox"
                                                checked={checkDuplicates}
                                                onChange={(event) => setCheckDuplicates(event.target.checked)}
                                            />
                                            <span>Kiểm trùng hệ thống</span>
                                        </label>
                                    </td>
                                </tr>
                                <tr>
                                    <td />
                                    <td className="ps-lead-import-history">
                                        <span className="ps-lead-import-history-link">
                                            <i className="fa fa-history" /> Lịch sử import
                                        </span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </section>
                </div>
            </div>

            <ImportGuideDialog open={guideOpen} onClose={() => setGuideOpen(false)} templateUrl={templateUrl} />
        </AppLayout>
    );
}
