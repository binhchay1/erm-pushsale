import { router, useForm } from '@inertiajs/react';
import { toast } from 'sonner';

import { CurrencyInput } from '@/components/ui/currency-input';
import { PushsaleDialog } from '@/components/ui/pushsale-dialog';
import { normalizeVietnamesePhone, vietnamesePhoneError } from '@/lib/vietnamesePhone';

export function userToForm(user = null) {
    return {
        role: user?.role ?? '',
        email_local: user?.email_local ?? user?.email?.split('@')?.[0] ?? '',
        password: '',
        password_confirmation: '',
        phone: user?.phone ?? '',
        name: user?.name ?? '',
        employee_code: user?.employee_code ?? '',
        base_salary: user?.base_salary ?? '',
        team_id: user?.team_id ?? '',
        manager_user_id: user?.manager_user_id ?? '',
        work_shift_id: user?.work_shift_id ?? '',
        is_team_leader: Boolean(user?.is_team_leader ?? false),
        receive_data: Boolean(user?.receive_data ?? true),
        is_locked: Boolean(user?.is_locked ?? false),
    };
}

function EmployeeFormRow({ label, required = false, hint = null, children }) {
    return (
        <div className="ps-employee-form-row">
            <label>
                {label}
                {required ? <> <span className="required">(*)</span></> : null}
                {hint ? <><br /><span className="small-tip">{hint}</span></> : null}
            </label>
            <div>{children}</div>
        </div>
    );
}

export function PasswordDialog({ user, onClose }) {
    const form = useForm({ password: '', password_confirmation: '' });
    if (!user) return null;

    const submit = (event) => {
        event.preventDefault();
        form.patch(`/admin/users/${user.id}/password`, {
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                onClose();
            },
        });
    };

    return (
        <PushsaleDialog
            open={Boolean(user)}
            onOpenChange={(nextOpen) => !nextOpen && onClose()}
            title="THAY ĐỔI MẬT KHẨU"
            width="600px"
            bodyClassName="ps-employee-dialog-body"
        >
            <form className="ps-employee-form" onSubmit={submit}>
                <div className="ps-employee-form-row">
                    <label>Tài khoản:</label>
                    <div className="ps-employee-readonly">{user.name} ({user.email})</div>
                </div>
                <EmployeeFormRow label="Mật khẩu mới" required>
                    <input
                        className="form-control"
                        type="password"
                        value={form.data.password}
                        onChange={(event) => form.setData('password', event.target.value)}
                        required
                    />
                </EmployeeFormRow>
                <EmployeeFormRow label="Nhập lại mật khẩu" required>
                    <input
                        className="form-control"
                        type="password"
                        value={form.data.password_confirmation}
                        onChange={(event) => form.setData('password_confirmation', event.target.value)}
                        required
                    />
                </EmployeeFormRow>
                {Object.keys(form.errors).length > 0 && (
                    <div className="ps-employee-error">{Object.values(form.errors).join(' · ')}</div>
                )}
                <div className="ps-employee-action-row">
                    <button type="button" className="btn btn-default btn-sm" onClick={onClose}>Đóng</button>
                    <button className="btn btn-primary btn-sm" disabled={form.processing}>
                        <i className={`fa ${form.processing ? 'fa-spinner fa-spin' : 'fa-save'}`} /> Lưu
                    </button>
                </div>
            </form>
        </PushsaleDialog>
    );
}

export function AccountDialog({
    mode = 'create',
    user = null,
    onClose,
    roles = [],
    workShifts = [],
    teams = [],
    managers = [],
    emailIdentity = {},
}) {
    const form = useForm(userToForm(mode === 'update' ? user : null));
    if (mode === 'update' && !user) return null;
    if (!['create', 'update'].includes(mode)) return null;

    const isUpdate = mode === 'update';
    const title = isUpdate ? 'CẬP NHẬT TÀI KHOẢN' : 'THÊM TÀI KHOẢN';
    const actionText = isUpdate ? 'Cập nhật' : 'Thêm mới';
    const suffix = emailIdentity?.suffix ?? '@saleops.local';

    const submit = (event) => {
        event.preventDefault();
        const phoneError = vietnamesePhoneError(form.data.phone);
        if (phoneError) {
            form.setError('phone', phoneError);
            toast.error(phoneError);
            return;
        }
        const normalizedPhone = normalizeVietnamesePhone(form.data.phone);
        if (String(form.data.phone ?? '').trim() && normalizedPhone) {
            form.setData('phone', normalizedPhone);
        }
        const options = {
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                onClose();
                toast.success(isUpdate ? 'Đã cập nhật tài khoản.' : 'Đã thêm tài khoản.');
            },
            onError: (errors) => {
                const message = errors.phone || Object.values(errors)[0] || 'Không thể lưu tài khoản.';
                toast.error(String(message));
            },
        };
        if (isUpdate) form.patch(`/admin/users/${user.id}/quick-update`, options);
        else form.post('/admin/users', options);
    };

    return (
        <PushsaleDialog
            open
            onOpenChange={(nextOpen) => !nextOpen && onClose()}
            title={title}
            width="600px"
            bodyClassName="ps-employee-dialog-body ps-employee-account-dialog"
        >
            <form className="ps-employee-form" onSubmit={submit}>
                {Object.keys(form.errors).length > 0 && (
                    <div className="ps-employee-error">{Object.values(form.errors).join(' · ')}</div>
                )}

                <EmployeeFormRow label="Chức vụ" required>
                    <select
                        className="form-control"
                        value={form.data.role}
                        onChange={(event) => form.setData('role', event.target.value)}
                        required
                    >
                        <option value="">--Chức vụ--</option>
                        {roles.map((role) => (
                            <option key={role.value} value={role.value}>{role.label}</option>
                        ))}
                    </select>
                </EmployeeFormRow>

                <EmployeeFormRow label="Tài khoản" required>
                    <div className="ps-employee-email-input">
                        <input
                            className="form-control"
                            value={form.data.email_local}
                            onChange={(event) => form.setData('email_local', event.target.value)}
                            required
                        />
                        <span>{suffix}</span>
                    </div>
                </EmployeeFormRow>

                <EmployeeFormRow label="Mật khẩu" required={!isUpdate}>
                    <input
                        className="form-control"
                        type="password"
                        value={form.data.password}
                        onChange={(event) => {
                            const password = event.target.value;
                            form.setData({ ...form.data, password, password_confirmation: password });
                        }}
                        required={!isUpdate}
                        placeholder={isUpdate ? 'Để trống nếu không đổi' : ''}
                    />
                </EmployeeFormRow>

                <EmployeeFormRow label="Họ và tên" required>
                    <input
                        className="form-control"
                        value={form.data.name}
                        onChange={(event) => form.setData('name', event.target.value)}
                        required
                    />
                </EmployeeFormRow>

                <EmployeeFormRow label="Số ĐT">
                    <input
                        className="form-control"
                        type="tel"
                        inputMode="tel"
                        placeholder="0912345678"
                        value={form.data.phone}
                        onChange={(event) => form.setData('phone', event.target.value)}
                    />
                    {form.errors.phone ? <div className="ps-employee-field-error">{form.errors.phone}</div> : null}
                </EmployeeFormRow>

                <EmployeeFormRow label="Mã nhân viên">
                    <input
                        className="form-control"
                        value={form.data.employee_code}
                        onChange={(event) => form.setData('employee_code', event.target.value)}
                    />
                </EmployeeFormRow>

                <EmployeeFormRow label="Trưởng nhóm/QL trực tiếp" hint="Không bắt buộc — để trống vẫn full quyền theo chức vụ">
                    <select
                        className="form-control"
                        value={form.data.manager_user_id}
                        onChange={(event) => form.setData('manager_user_id', event.target.value)}
                    >
                        <option value="">--Chọn quản lý--</option>
                        {managers.map((manager) => (
                            <option key={manager.id} value={manager.id}>{manager.name}</option>
                        ))}
                    </select>
                </EmployeeFormRow>

                <EmployeeFormRow label="Đội nhóm">
                    <select
                        className="form-control"
                        value={form.data.team_id}
                        onChange={(event) => form.setData('team_id', event.target.value)}
                    >
                        <option value="">--Chọn đội nhóm--</option>
                        {teams.map((team) => (
                            <option key={team.id} value={team.id}>{team.name}</option>
                        ))}
                    </select>
                </EmployeeFormRow>

                <EmployeeFormRow label="Lương cứng">
                    <CurrencyInput
                        className="form-control"
                        value={form.data.base_salary === '' || form.data.base_salary == null ? '' : Number(form.data.base_salary)}
                        onChange={(amount) => form.setData('base_salary', amount)}
                    />
                </EmployeeFormRow>

                <EmployeeFormRow label="Ca làm việc">
                    <select
                        className="form-control"
                        value={form.data.work_shift_id}
                        onChange={(event) => form.setData('work_shift_id', event.target.value)}
                    >
                        <option value="">--Ca làm việc--</option>
                        {workShifts.map((shift) => (
                            <option key={shift.id} value={shift.id}>{shift.name}</option>
                        ))}
                    </select>
                </EmployeeFormRow>

                <div className="ps-employee-form-row">
                    <label />
                    <div className="ps-employee-inline">
                        <label>
                            <input
                                type="checkbox"
                                checked={form.data.is_team_leader}
                                onChange={(event) => form.setData('is_team_leader', event.target.checked)}
                            />
                            Trưởng nhóm
                        </label>
                        <label>
                            <input
                                type="checkbox"
                                checked={form.data.receive_data}
                                onChange={(event) => form.setData('receive_data', event.target.checked)}
                            />
                            Nhận dữ liệu
                        </label>
                        <label>
                            <input
                                type="checkbox"
                                checked={!form.data.is_locked}
                                onChange={(event) => form.setData('is_locked', !event.target.checked)}
                            />
                            Đang sử dụng
                        </label>
                    </div>
                </div>

                <div className="ps-employee-dialog-note">
                    {isUpdate
                        ? 'Cập nhật chức vụ/đội nhóm/nhận dữ liệu sẽ ảnh hưởng trực tiếp đến phân bổ data, báo cáo và quyền tác nghiệp.'
                        : 'Tài khoản mới sẽ được gắn vào đơn vị hiện tại và dùng ngay trong phân bổ data, báo cáo, kho/sale/marketing theo chức vụ.'}
                </div>

                <div className="ps-employee-action-row">
                    <button type="button" className="btn btn-default btn-sm" onClick={onClose}>Đóng</button>
                    <button className="btn btn-primary btn-sm" disabled={form.processing}>
                        <i className={`fa ${form.processing ? 'fa-spinner fa-spin' : 'fa-save'}`} /> {actionText}
                    </button>
                </div>
            </form>
        </PushsaleDialog>
    );
}

export function BulkAccountDialog({ open, onClose, roles = [] }) {
    const form = useForm({
        template: '',
        quantity: '',
        start: '',
        role: '',
        accounts: '',
        password: '',
        password_confirmation: '',
        receive_data: false,
    });

    if (!open) return null;

    const generateAccounts = () => {
        const qty = Math.max(0, Number.parseInt(form.data.quantity || '0', 10));
        const start = Number.parseInt(form.data.start || '1', 10);
        const template = form.data.template || 'user{n}';
        if (!qty) return;
        const lines = Array.from({ length: qty }, (_, index) => {
            const n = start + index;
            return template.includes('{n}') ? template.replaceAll('{n}', String(n)) : `${template}${n}`;
        });
        form.setData('accounts', lines.join('\n'));
    };

    const submit = (event) => {
        event.preventDefault();
        form.post('/admin/users/bulk', {
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                onClose();
            },
        });
    };

    return (
        <PushsaleDialog
            open={open}
            onOpenChange={(nextOpen) => !nextOpen && onClose()}
            title="THÊM NHIỀU TÀI KHOẢN"
            width="800px"
            bodyClassName="ps-employee-dialog-body ps-employee-bulk-dialog"
        >
            <form className="ps-employee-form" onSubmit={submit}>
                {Object.keys(form.errors).length > 0 && (
                    <div className="ps-employee-error">{Object.values(form.errors).join(' · ')}</div>
                )}

                <EmployeeFormRow label="Hỗ trợ tạo nhanh:">
                    <div className="ps-employee-support">
                        <input
                            className="form-control"
                            placeholder="Mẫu, ví dụ sale{n}"
                            value={form.data.template}
                            onChange={(event) => form.setData('template', event.target.value)}
                        />
                        <input
                            className="form-control"
                            placeholder="Số lượng"
                            type="number"
                            min="1"
                            value={form.data.quantity}
                            onChange={(event) => form.setData('quantity', event.target.value)}
                        />
                        <input
                            className="form-control"
                            placeholder="Bắt đầu"
                            type="number"
                            min="1"
                            value={form.data.start}
                            onChange={(event) => form.setData('start', event.target.value)}
                        />
                        <button type="button" className="btn btn-default btn-sm" onClick={generateAccounts}>Tạo TK</button>
                    </div>
                </EmployeeFormRow>

                <EmployeeFormRow label="Chức vụ" required>
                    <select
                        className="form-control"
                        value={form.data.role}
                        onChange={(event) => form.setData('role', event.target.value)}
                        required
                    >
                        <option value="">--Chức vụ--</option>
                        {roles.map((role) => (
                            <option key={role.value} value={role.value}>{role.label}</option>
                        ))}
                    </select>
                </EmployeeFormRow>

                <EmployeeFormRow
                    label="Tài khoản"
                    required
                    hint="Mỗi dòng một tài khoản, không nhập đuôi email"
                >
                    <textarea
                        className="form-control"
                        rows="10"
                        value={form.data.accounts}
                        onChange={(event) => form.setData('accounts', event.target.value)}
                        required
                        placeholder={'Mỗi dòng một tài khoản. Ví dụ:\nsale01\nsale02\nmarketing01'}
                    />
                </EmployeeFormRow>

                <EmployeeFormRow label="Mật khẩu" required>
                    <input
                        className="form-control"
                        type="password"
                        value={form.data.password}
                        onChange={(event) => {
                            const password = event.target.value;
                            form.setData({ ...form.data, password, password_confirmation: password });
                        }}
                        required
                    />
                </EmployeeFormRow>

                <div className="ps-employee-form-row">
                    <label />
                    <label className="ps-employee-check">
                        <input
                            type="checkbox"
                            checked={form.data.receive_data}
                            onChange={(event) => form.setData('receive_data', event.target.checked)}
                        />
                        Nhận dữ liệu ngay sau khi tạo
                    </label>
                </div>

                <div className="ps-employee-dialog-note">
                    Nhập mỗi dòng một mã tài khoản, ví dụ sale01, sale02, mkt01. Không nhập khoảng trắng hoặc đuôi email;
                    hệ thống tự sinh email theo đơn vị hiện tại, tạo hồ sơ vận hành và đưa tài khoản vào luồng phân bổ data.
                </div>

                <div className="ps-employee-action-row">
                    <button type="button" className="btn btn-default btn-sm" onClick={onClose}>Đóng</button>
                    <button className="btn btn-primary btn-sm" disabled={form.processing}>
                        <i className={`fa ${form.processing ? 'fa-spinner fa-spin' : 'fa-save'}`} /> Tạo tài khoản
                    </button>
                </div>
            </form>
        </PushsaleDialog>
    );
}

export function ReceiveDataDialog({ open, onClose, initialAccounts = '' }) {
    const form = useForm({ accounts: initialAccounts });

    if (!open) return null;

    const submit = (receiveData) => {
        router.post('/admin/users/bulk-receive-data', {
            accounts: form.data.accounts,
            receive_data: receiveData,
        }, {
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                onClose();
            },
        });
    };

    return (
        <PushsaleDialog
            open={open}
            onOpenChange={(nextOpen) => !nextOpen && onClose()}
            title="CẬP NHẬT NHẬN DỮ LIỆU"
            width="600px"
            bodyClassName="ps-employee-dialog-body ps-employee-receive-dialog"
        >
            <form className="ps-employee-form" onSubmit={(event) => event.preventDefault()}>
                {Object.keys(form.errors).length > 0 && (
                    <div className="ps-employee-error">{Object.values(form.errors).join(' · ')}</div>
                )}

                <EmployeeFormRow label="Danh sách tài khoản" hint="Mỗi dòng một mã ID hoặc tài khoản">
                    <textarea
                        className="form-control"
                        rows="12"
                        value={form.data.accounts}
                        onChange={(event) => form.setData('accounts', event.target.value)}
                        placeholder="Nhập ID hoặc mã tài khoản, mỗi dòng một tài khoản"
                    />
                </EmployeeFormRow>

                <div className="ps-employee-action-row ps-employee-receive-actions">
                    <button
                        type="button"
                        className="btn btn-primary btn-sm"
                        disabled={form.processing || !form.data.accounts.trim()}
                        onClick={() => submit(true)}
                    >
                        <i className={`fa ${form.processing ? 'fa-spinner fa-spin' : 'fa-check'}`} /> Nhận dữ liệu
                    </button>
                    <button
                        type="button"
                        className="btn btn-default btn-sm"
                        disabled={form.processing || !form.data.accounts.trim()}
                        onClick={() => submit(false)}
                    >
                        <i className="fa fa-ban" /> Không nhận dữ liệu
                    </button>
                    <button type="button" className="btn btn-default btn-sm" onClick={onClose}>Đóng</button>
                </div>
            </form>
        </PushsaleDialog>
    );
}

export function GoogleAuthDialog({ user, onClose }) {
    if (!user) return null;

    return (
        <PushsaleDialog
            open={Boolean(user)}
            onOpenChange={(nextOpen) => !nextOpen && onClose()}
            title="GOOGLE AUTHENTICATOR"
            width="600px"
            bodyClassName="ps-employee-dialog-body ps-employee-google-dialog"
        >
            <div className="ps-employee-form">
                <div className="ps-employee-form-row">
                    <label>Tài khoản:</label>
                    <div className="ps-employee-readonly">{user.name} ({user.email})</div>
                </div>

                <div className="ps-employee-qr-panel">
                    <div className="ps-employee-qr-placeholder" aria-hidden="true">
                        <i className="fa fa-qrcode" />
                    </div>
                    <p className="ps-employee-qr-note">
                        Quét mã QR bằng ứng dụng Google Authenticator để thiết lập xác thực hai yếu tố cho tài khoản này.
                    </p>
                </div>

                <div className="ps-employee-action-row ps-employee-google-actions">
                    <button type="button" className="btn btn-primary btn-sm" disabled title="Chức năng đang được triển khai">
                        <i className="fa fa-refresh" /> Sinh mã Qr Code
                    </button>
                    <button type="button" className="btn btn-default btn-sm" disabled title="Chức năng đang được triển khai">
                        <i className="fa fa-download" /> Lưu ảnh
                    </button>
                    <button type="button" className="btn btn-default btn-sm" onClick={onClose}>Đóng</button>
                </div>
            </div>
        </PushsaleDialog>
    );
}
