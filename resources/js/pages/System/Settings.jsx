import { Head, Link, useForm } from '@inertiajs/react';
import { useMemo, useState } from 'react';

import AppLayout from '@/layouts/AppLayout';

const levelLabels = {
    none: 'Không có',
    view: 'Chỉ xem',
    full: 'Toàn quyền',
};

const areaLabels = {
    reports: 'Báo cáo',
    telesale: 'Tác nghiệp sale',
    orders: 'Chốt đơn / đơn hàng',
    marketing: 'Marketing',
    leads: 'Lead / phân bổ data',
    warehouse: 'Kho',
    shipping: 'Giao vận',
    accounting: 'Kế toán',
    customers: 'Hồ sơ khách hàng',
    customer_chat: 'Chat khách hàng',
    products: 'Sản phẩm / combo',
    hr: 'Nhân sự',
    integrations: 'Kết nối',
    pancake: 'Pancake',
    activity: 'Nhật ký',
};

const roleLabels = {
    admin: 'Admin',
    sales: 'Sale',
    marketing: 'Marketing',
    warehouse: 'Kho',
    allocator: 'Chia số',
    accounting: 'Kế toán',
};

function areaName(area) {
    return areaLabels[area.key] || area.label || area.key;
}

function roleName(role) {
    return roleLabels[role.key] || role.label || role.key;
}

function PermissionBadge({ value }) {
    return <span className={`ps-system-permission-badge is-${value || 'none'}`}>{levelLabels[value] || value || 'Không có'}</span>;
}

export default function SystemSettingsPage({ activeMenuCode = '10.1.4', roles = [], areas = [], rolePermissions = {}, users = [] }) {
    const [tab, setTab] = useState('roles');
    const { data, setData, put, processing, errors } = useForm({ role_permissions: rolePermissions });

    const updateRolePermission = (role, area, value) => {
        setData('role_permissions', {
            ...data.role_permissions,
            [role]: {
                ...(data.role_permissions?.[role] || {}),
                [area]: value,
            },
        });
    };

    const submit = (event) => {
        event.preventDefault();
        put('/admin/system/settings', { preserveScroll: true });
    };

    const areaChunks = useMemo(() => {
        const chunkSize = 5;
        const chunks = [];
        for (let index = 0; index < areas.length; index += chunkSize) {
            chunks.push(areas.slice(index, index + chunkSize));
        }
        return chunks;
    }, [areas]);

    return (
        <AppLayout activeMenuCode={activeMenuCode}>
            <Head title="Cấu hình hệ thống" />
            <div className="ps-system-settings-page">
                <div className="ps-system-header">
                    <div>
                        <h1>Cấu hình hệ thống</h1>
                        <p>Thiết lập quyền mặc định theo vai trò cho toàn bộ hệ thống. Đây là cấu hình cấp platform, tách riêng với cấu hình chức năng của từng đơn vị.</p>
                    </div>
                    <div className="ps-system-header-actions">
                        <Link href="/platform/settings" className="btn btn-default">
                            <i className="fa fa-id-card-o" /> Định danh đăng nhập
                        </Link>
                        <form onSubmit={submit}>
                            <button className="btn btn-primary" type="submit" disabled={processing}>
                                <i className="fa fa-save" /> Cập nhật
                            </button>
                        </form>
                    </div>
                </div>

                {errors.role_permissions ? <div className="ps-system-error">{errors.role_permissions}</div> : null}

                <div className="ps-system-tabs">
                    <button type="button" className={tab === 'roles' ? 'active' : ''} onClick={() => setTab('roles')}>Mặc định theo vai trò</button>
                    <button type="button" className={tab === 'users' ? 'active' : ''} onClick={() => setTab('users')}>Ghi đè theo người dùng</button>
                    <button type="button" className={tab === 'rules' ? 'active' : ''} onClick={() => setTab('rules')}>Nguyên tắc quyền</button>
                </div>

                {tab === 'roles' ? (
                    <form className="ps-system-card" onSubmit={submit}>
                        <div className="ps-system-note">
                            <b>Quy tắc áp dụng:</b> mặc định vai trò là nền cho user mới và user chưa có quyền riêng. Quyền riêng ở hồ sơ nhân sự sẽ ghi đè mặc định này.
                        </div>
                        {areaChunks.map((chunk, chunkIndex) => (
                            <div className="ps-system-table-wrap" key={chunkIndex}>
                                <table className="ps-system-table">
                                    <thead>
                                        <tr>
                                            <th className="role-col">Vai trò</th>
                                            {chunk.map((area) => <th key={area.key}>{areaName(area)}</th>)}
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {roles.map((role) => (
                                            <tr key={`${role.key}-${chunkIndex}`}>
                                                <td className="role-name">{roleName(role)}</td>
                                                {chunk.map((area) => (
                                                    <td key={area.key}>
                                                        <select
                                                            className="form-control"
                                                            value={data.role_permissions?.[role.key]?.[area.key] || 'none'}
                                                            onChange={(event) => updateRolePermission(role.key, area.key, event.target.value)}
                                                        >
                                                            {Object.entries(levelLabels).map(([value, label]) => <option key={value} value={value}>{label}</option>)}
                                                        </select>
                                                    </td>
                                                ))}
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        ))}
                        <div className="ps-system-actions">
                            <button className="btn btn-primary" type="submit" disabled={processing}><i className="fa fa-save" /> Lưu quyền mặc định</button>
                        </div>
                    </form>
                ) : null}

                {tab === 'users' ? (
                    <div className="ps-system-card">
                        <div className="ps-system-note">
                            Bảng này giúp rà soát user đang chịu quyền gì. Các quyền riêng nên chỉnh tại trang nhân sự/hồ sơ user để không trộn lẫn cấu hình platform với dữ liệu nhân viên.
                        </div>
                        <div className="ps-system-table-wrap">
                            <table className="ps-system-table is-users">
                                <thead>
                                    <tr>
                                        <th>Tài khoản</th>
                                        <th>Vai trò</th>
                                        <th>Quyền riêng</th>
                                        <th>Quyền hiệu lực</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {users.map((user) => (
                                        <tr key={user.id}>
                                            <td><b>{user.name}</b><small>{user.email}</small></td>
                                            <td>{roleLabels[user.role] || user.role}</td>
                                            <td>{Object.keys(user.custom_permissions || {}).length ? 'Có cấu hình riêng' : 'Theo vai trò'}</td>
                                            <td className="permission-list">
                                                {areas.map((area) => <PermissionBadge key={area.key} value={user.effective_permissions?.[area.key] || 'none'} />)}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </div>
                ) : null}

                {tab === 'rules' ? (
                    <div className="ps-system-card ps-system-rules">
                        <h3>Phân tách cấu hình</h3>
                        <p><b>Cấu hình hệ thống</b> dùng cho superadmin/admin nội bộ: quyền mặc định theo vai trò, quyền truy cập module, kiểm soát dữ liệu nhạy cảm.</p>
                        <p><b>Cấu hình chức năng đơn vị</b> dùng cho từng công ty: prefix mã đơn, cho kho đăng/hủy đơn, quy tắc vận hành nội bộ, cấu hình giao hàng.</p>
                        <p>Không dùng chung hai màn này vì phạm vi ảnh hưởng khác nhau. Cấu hình hệ thống tác động toàn app; cấu hình đơn vị chỉ tác động tenant/đơn vị hiện tại.</p>
                    </div>
                ) : null}
            </div>
        </AppLayout>
    );
}
