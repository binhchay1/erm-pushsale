import { Head, router, useForm, usePage } from '@inertiajs/react';
import { useRef, useState } from 'react';

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

    const upload = (event) => {
        event.preventDefault();
        if (!form.data.file) {
            inputRef.current?.click();
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
            <section className="ps-adminlte-page ps-product-import-page" data-page-code="1.3.1-import">
                <div className="m-header-wrap">
                    <div className="m-header">
                        <div className="col-sm-6 form-group">
                            <span className="text ps-title">Import sản phẩm</span>
                        </div>
                        <div className="col-sm-3 form-group" />
                    </div>
                </div>

                <div className="box-body ps-product-import-body">
                    <div className="row">
                        <div className="col-xs-12 huong-dan form-group">
                            <div className="notice ps-import-notice">
                                <b>Lưu ý:</b><br />
                                - <span>Chỉ import file excel dưới 3000 dòng</span><br />
                                - <span>Nếu mã sản phẩm import trùng tên sản phẩm gốc, trùng thuộc tính đã có trên hệ thống sẽ thực hiện cập nhật</span><br />
                                - <span>Xem video hướng dẫn tại đây</span>
                            </div>
                        </div>
                    </div>

                    {(flash.success || flash.error || Object.keys(form.errors).length > 0) && (
                        <div className={`alert ${flash.error || Object.keys(form.errors).length ? 'alert-danger' : 'alert-success'} ps-import-alert`}>
                            {flash.success || flash.error || Object.values(form.errors).join(' · ')}
                        </div>
                    )}

                    <div className="row ps-import-workspace">
                        <div className="col-xs-3 ps-import-left">
                            <form onSubmit={upload}>
                                <table className="table table-bordered tb-sp ps-import-table">
                                    <tbody>
                                        <tr>
                                            <td className="no-wrap ps-import-label">Tải mẫu</td>
                                            <td>
                                                <a href="/admin/products/import/sample" className="ps-import-link">
                                                    <i className="fa fa-cloud-download" />Tải mẫu Excel
                                                </a>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td className="no-wrap ps-import-label">Chọn file</td>
                                            <td>
                                                <div className="app-file-upload ps-file-upload">
                                                    <div className="ps-file-name-wrap">
                                                        <input ref={inputRef} type="file" className="app-file-upload-chooser form-control hidden" accept=".xls,.xlsx,.csv,.txt" onChange={chooseFile} />
                                                        <button type="button" className="app-file-upload-chooser form-control ps-file-chooser" onClick={() => inputRef.current?.click()}>{fileName || 'Chọn file...'}</button>
                                                    </div>
                                                    <div className="ps-file-upload-icon-wrap">
                                                        <button type="button" className="btn btn-default btn-square btn-icon app-btn-upload-file" onClick={() => inputRef.current?.click()} title="Upload File">
                                                            <i className="fa fa-cloud-upload" />
                                                        </button>
                                                    </div>
                                                    <div className="clearfix" />
                                                </div>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td />
                                            <td>
                                                <button type="submit" className="ps-import-link ps-upload-submit" disabled={form.processing}>
                                                    <i className="fa fa-cloud-upload" /> Upload
                                                </button>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </form>
                            <div className="progress progress-sm active hidden ps-import-progress">
                                <div className="progress-bar progress-bar-success progress-bar-striped" role="progressbar" style={{ width: form.processing ? '80%' : '0%' }} />
                            </div>
                        </div>
                        <div className="col-xs-9 ps-import-preview" />
                    </div>
                </div>
            </section>
        </AppLayout>
    );
}
