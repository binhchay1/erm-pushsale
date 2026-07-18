import { Head, Link, useForm } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import { toast } from 'sonner';

import AppLayout from '@/layouts/AppLayout';

const normalize = (value) => String(value ?? '').toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '');

function rowMatches(row, keyword) {
    if (!keyword) return true;
    const haystack = [
        row.label,
        row.help,
        row.note,
        ...(row.controls ?? []).flatMap((control) => [
            control.label,
            control.placeholder,
            control.help,
            ...(control.options ?? []).flatMap((option) => [option.label, option.value]),
        ]),
    ].join(' ');

    return normalize(haystack).includes(keyword);
}

function FieldControl({ control, value, onChange }) {
    const id = `feature-setting-${control.key}`;

    if (control.type === 'boolean') {
        return (
            <label className="ps-feature-check" htmlFor={id}>
                <input id={id} type="checkbox" checked={Boolean(value)} onChange={(event) => onChange(event.target.checked)} />
                <span>{control.label || control.placeholder || control.key}</span>
            </label>
        );
    }

    if (control.type === 'select') {
        return (
            <select id={id} className="form-control txt-dotted" value={value ?? control.default ?? ''} onChange={(event) => onChange(event.target.value)}>
                {(control.options ?? []).map((option) => (
                    <option key={`${control.key}-${option.value}`} value={option.value}>{option.label}</option>
                ))}
            </select>
        );
    }

    if (control.type === 'long_text') {
        return (
            <textarea
                id={id}
                className="form-control txt-dotted"
                rows={2}
                maxLength={control.max_length || undefined}
                placeholder={control.placeholder || ''}
                title={control.help || ''}
                value={value ?? ''}
                onChange={(event) => onChange(event.target.value)}
            />
        );
    }

    if (control.type === 'excel_columns') {
        return <ExcelColumnPicker control={control} value={Array.isArray(value) ? value : []} onChange={onChange} />;
    }

    return (
        <input
            id={id}
            type="text"
            className="form-control txt-dotted"
            maxLength={control.max_length || undefined}
            placeholder={control.placeholder || ''}
            title={control.help || ''}
            value={value ?? ''}
            onChange={(event) => onChange(event.target.value)}
        />
    );
}

function ExcelColumnPicker({ control, value, onChange }) {
    const [selected, setSelected] = useState(control.options?.[0]?.value ?? '');
    const labels = useMemo(() => new Map((control.options ?? []).map((option) => [String(option.value), option.label])), [control.options]);
    const unusedOptions = (control.options ?? []).filter((option) => !value.includes(option.value));

    const addColumn = () => {
        if (!selected || value.includes(selected)) return;
        onChange([...value, selected]);
        const next = unusedOptions.find((option) => option.value !== selected)?.value ?? '';
        setSelected(next);
    };

    const removeColumn = (key) => onChange(value.filter((item) => item !== key));
    const moveColumn = (index, delta) => {
        const nextIndex = index + delta;
        if (nextIndex < 0 || nextIndex >= value.length) return;
        const next = [...value];
        [next[index], next[nextIndex]] = [next[nextIndex], next[index]];
        onChange(next);
    };

    return (
        <div className="ps-feature-excel-control">
            <div className="ps-feature-excel-add">
                <select className="form-control" value={selected} onChange={(event) => setSelected(event.target.value)}>
                    {unusedOptions.map((option) => (
                        <option key={`${control.key}-option-${option.value}`} value={option.value}>{option.label}</option>
                    ))}
                </select>
                <button type="button" className="btn btn-sm btn-primary" onClick={addColumn} disabled={!selected || unusedOptions.length === 0}>
                    <i className="fa fa-plus" /> Thêm cột
                </button>
            </div>
            <div className="ps-feature-excel-list">
                {value.map((item, index) => (
                    <span className="item1" data-colname={item} key={`${control.key}-${item}`}>
                        {labels.get(String(item)) ?? item}
                        <button type="button" className="btn-xoa-col aoh" title="Đưa lên" onClick={() => moveColumn(index, -1)} disabled={index === 0}><i className="fa fa-arrow-up" /></button>
                        <button type="button" className="btn-xoa-col aoh" title="Đưa xuống" onClick={() => moveColumn(index, 1)} disabled={index === value.length - 1}><i className="fa fa-arrow-down" /></button>
                        <button type="button" className="btn-xoa-col aoh" title="Xóa cột" onClick={() => removeColumn(item)}><i className="fa fa-trash" /></button>
                    </span>
                ))}
            </div>
        </div>
    );
}

function SettingsRow({ row, values, onChange }) {
    return (
        <tr className="ps-feature-row">
            <td className="col1">{row.label}</td>
            <td className="col2">
                {(row.controls ?? []).length > 0 ? (
                    <div className="ps-feature-controls">
                        {row.controls.map((control) => (
                            <FieldControl
                                key={control.key}
                                control={control}
                                value={values[control.key] ?? control.default}
                                onChange={(next) => onChange(control.key, next)}
                            />
                        ))}
                    </div>
                ) : (
                    <span className="ps-feature-muted">—</span>
                )}
            </td>
            <td className="col3">
                {(row.help || row.controls?.some((control) => control.help)) ? (
                    <span className="ps-feature-help" title={row.help || row.controls?.find((control) => control.help)?.help}>
                        <i className="fa fa-question-circle" />
                    </span>
                ) : null}
            </td>
            <td className="col4">{row.note}</td>
        </tr>
    );
}

function SettingsTabTable({ tab, values, keyword, onChange }) {
    const rows = (tab.rows ?? []).filter((row) => rowMatches(row, keyword));

    if (rows.length === 0) return null;

    return (
        <div className="tb-search active" data-tab={tab.id}>
            <table className="table table-bordered ps-feature-table">
                <tbody>
                    <tr className="trh"><th colSpan={4} className="text-lead">{tab.title}</th></tr>
                    {rows.map((row) => <SettingsRow key={row.key} row={row} values={values} onChange={onChange} />)}
                </tbody>
            </table>
        </div>
    );
}

export default function SettingsIndex({ definition = [], values = {}, activityUrl = '/admin/activity-logs' }) {
    const [activeTab, setActiveTab] = useState('tab_1');
    const [keyword, setKeyword] = useState('');
    const normalizedKeyword = normalize(keyword.trim());
    const { data, setData, put, processing, recentlySuccessful } = useForm({ settings: values });

    const updateSetting = (key, value) => {
        setData('settings', {
            ...data.settings,
            [key]: value,
        });
    };

    const visibleTabs = activeTab === 'all'
        ? definition
        : definition.filter((tab) => tab.id === activeTab);

    const submit = (event) => {
        event.preventDefault();
        put('/settings', {
            preserveScroll: true,
            onSuccess: () => toast.success('Đã cập nhật cấu hình chức năng'),
            onError: (errors) => toast.error(Object.values(errors)[0] ?? 'Không thể lưu cấu hình'),
        });
    };

    return (
        <AppLayout>
            <Head title="Cấu hình chức năng" />
            <form className="ps-feature-settings-page" onSubmit={submit}>
                <div className="m-header-wrap ps-feature-header-wrap">
                    <div className="m-header ps-feature-header">
                        <div className="ps-feature-title"><span className="text">Cấu hình chức năng</span></div>
                        <div className="ps-feature-actions">
                            <Link className="mr15 ps-feature-history" href={activityUrl}>
                                <i className="fa fa-history" /> Lịch sử hoạt động
                            </Link>
                            <button type="submit" className="btn btn-sm btn-primary" disabled={processing}>
                                <i className="fa fa-save" /> {processing ? 'Đang cập nhật' : 'Cập nhật'}
                            </button>
                        </div>
                    </div>
                </div>

                <div className="ps-feature-body">
                    <aside className="ps-feature-tabs" aria-label="Nhóm cấu hình">
                        <div className="ps-feature-all-label">Tất cả</div>
                        <button type="button" className={`btn-xem-kn tab-0 ${activeTab === 'all' ? 'active' : ''}`} onClick={() => setActiveTab('all')}>Tất cả</button>
                        {definition.map((tab) => (
                            <button
                                type="button"
                                key={tab.id}
                                className={`btn-xem-kn tab-${tab.index} ${activeTab === tab.id ? 'active' : ''}`}
                                onClick={() => setActiveTab(tab.id)}
                            >
                                {tab.title}
                            </button>
                        ))}
                    </aside>

                    <main className="ps-feature-content">
                        <div className="box-body ps-feature-search-row">
                            <input
                                type="text"
                                className="form-control txt-kw"
                                placeholder="Nhập từ khóa tìm kiếm"
                                value={keyword}
                                onChange={(event) => setKeyword(event.target.value)}
                            />
                            {recentlySuccessful ? <span className="ps-feature-saved">Đã lưu</span> : null}
                        </div>
                        <div className="st-wrapper">
                            {visibleTabs.map((tab) => (
                                <SettingsTabTable
                                    key={tab.id}
                                    tab={tab}
                                    values={data.settings}
                                    keyword={normalizedKeyword}
                                    onChange={updateSetting}
                                />
                            ))}
                            {visibleTabs.every((tab) => (tab.rows ?? []).every((row) => !rowMatches(row, normalizedKeyword))) ? (
                                <div className="ps-feature-empty">Không tìm thấy cấu hình phù hợp.</div>
                            ) : null}
                        </div>
                    </main>
                </div>
            </form>
        </AppLayout>
    );
}
