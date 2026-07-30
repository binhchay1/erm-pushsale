import { Head, useForm, usePage } from '@inertiajs/react';
import { useRef, useState } from 'react';

import { PageHeader } from '@/components/layout/PageHeader';
import AppLayout from '@/layouts/AppLayout';

export default function ProductImport() {
    const { props } = usePage();
    const flash = props.flash ?? {};
    const inputRef = useRef(null);
    const [fileName, setFileName] = useState('');
    const form = useForm({ file: null });

    const chooseFile = (event) => {
        const file = event.target.files?.[0] ?? null;
        form.setData('file', file);
        setFileName(file?.name ?? '');
    };

    const pickFile = () => inputRef.current?.click();

    const upload = (event) => {
        event.preventDefault();
        if (!form.data.file) {
            pickFile();
            return;
        }
        form.post('/admin/products/import', {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                form.reset('file');
                setFileName('');
                if (inputRef.current) inputRef.current.value = '';
            },
        });
    };

    return (
        <AppLayout>
            <Head title="Import sản phẩm" />
            <div className="ps-product-import-page" data-page-code="1.3.1-import">
                <PageHeader title="Import sản phẩm" pageCode="1.3.1-import" />
                <div className="ps-product-import-body">
                    <div className="notice ps-import-notice">
                        <b>Lưu ý:</b>
                        <br />
                        - <span>Chỉ import file excel dưới 3000 dòng</span>
                        <br />
                        - <span>Nếu mã sản phẩm import trùng tên sản phẩm gốc, trùng thuộc tính đã có trên hệ thống sẽ thực hiện cập nhật</span>
                        <br />
                        - <span>Xem video hướng dẫn tại đây</span>
                    </div>

                    {(flash.success || flash.error || Object.keys(form.errors).length > 0) && (
                        <div className={`alert ${flash.error || Object.keys(form.errors).length ? 'alert-danger' : 'alert-success'} ps-import-alert`}>
                            {flash.success || flash.error || Object.values(form.errors).join(' · ')}
                        </div>
                    )}

                    <div className="ps-import-workspace">
                        <form className="ps-import-left" onSubmit={upload}>
                            <table className="table table-bordered tb-sp ps-import-table">
                                <tbody>
                                    <tr>
                                        <td className="no-wrap ps-import-label">Tải mẫu</td>
                                        <td>
                                            <a href="/admin/products/import/sample" className="ps-import-link">
                                                <i className="fa fa-cloud-download" />
                                                {' '}
                                                Tải mẫu Excel
                                            </a>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td className="no-wrap ps-import-label">Chọn file</td>
                                        <td>
                                            <div className="ps-product-import-file">
                                                <input
                                                    ref={inputRef}
                                                    type="file"
                                                    className="hidden"
                                                    accept=".xls,.xlsx,.csv,.txt"
                                                    onChange={chooseFile}
                                                />
                                                <button type="button" className="ps-product-import-file-name" onClick={pickFile}>
                                                    {fileName || 'Chọn file...'}
                                                </button>
                                                <button
                                                    type="button"
                                                    className="ps-product-import-file-btn"
                                                    onClick={pickFile}
                                                    title="Chọn file"
                                                >
                                                    <i className="fa fa-cloud-upload" />
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td />
                                        <td>
                                            <button type="submit" className="ps-import-link ps-upload-submit" disabled={form.processing}>
                                                <i className="fa fa-cloud-upload" />
                                                {' '}
                                                Upload
                                            </button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </form>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
