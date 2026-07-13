import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';

import AppLayout from '@/layouts/AppLayout';

function navigate(url) {
    if (url) router.get(url, {}, { preserveScroll: true, preserveState: true });
}

export default function TeamsIndex({ teams, filters = {}, types = [], leaders = [] }) {
    const [form, setForm] = useState({
        type: filters.type ?? '',
        leader_id: filters.leader_id ?? '',
        search: filters.search ?? '',
    });
    const rows = teams?.data ?? [];

    const submit = (event) => {
        event.preventDefault();
        router.get('/admin/teams', Object.fromEntries(Object.entries(form).filter(([, value]) => value !== '')), {
            preserveState: true,
            replace: true,
        });
    };

    return (
        <AppLayout>
            <Head title="Quản lý đội, nhóm" />
            <section className="ps-adminlte-page ps-teams-page" data-page-code="1.2.2">
                <form className="m-header-wrap" onSubmit={submit}>
                    <div className="m-header ps-team-header">
                        <div className="ps-title">Quản lý đội, nhóm</div>
                        <div className="ps-team-filters">
                            <select className="form-control" value={form.type} onChange={(event) => setForm((old) => ({ ...old, type: event.target.value }))}>
                                <option value="">--Kiểu nhóm--</option>
                                {types.map((type) => <option key={type.value} value={type.value}>{type.label}</option>)}
                            </select>
                            <select className="form-control" value={form.leader_id} onChange={(event) => setForm((old) => ({ ...old, leader_id: event.target.value }))}>
                                <option value="">-- Trưởng nhóm --</option>
                                {leaders.map((leader) => <option key={leader.id} value={leader.id}>{leader.name}</option>)}
                            </select>
                            <input className="form-control text-center" placeholder="Tên nhóm" value={form.search} onChange={(event) => setForm((old) => ({ ...old, search: event.target.value }))} />
                            <button className="btn btn-sm btn-primary" type="submit"><i className="fa fa-search" /> Tìm kiếm</button>
                        </div>
                    </div>
                </form>

                <div className="ps-table-scroll">
                    <table className="table table-bordered ps-source-table ps-team-table">
                        <thead>
                            <tr>
                                <th>STT</th>
                                <th>Loại nhóm</th>
                                <th>Mã</th>
                                <th>Tên</th>
                                <th>Trưởng nhóm</th>
                                <th>Số thành viên</th>
                                <th>Thành viên</th>
                                <th>Nhóm liên kết</th>
                                <th>Cập nhật</th>
                                <th><i className="fa fa-plus" /> Thêm</th>
                            </tr>
                        </thead>
                        <tbody>
                            {rows.length ? rows.map((team, index) => (
                                <tr key={team.id}>
                                    <td className="text-center">{(teams.from ?? 1) + index}</td>
                                    <td className="text-center">{team.type_label}</td>
                                    <td className="text-center">{team.code}</td>
                                    <td className="text-center">{team.name}</td>
                                    <td className="text-center">
                                        {team.leader_name || ''}
                                        {team.leader_email && <small>({team.leader_email.split('@')[0]})</small>}
                                    </td>
                                    <td className="text-center">{team.members_count}</td>
                                    <td className="ps-members-cell">
                                        {team.members.map((member) => <span key={member.id}>{member.account}({member.name})</span>)}
                                    </td>
                                    <td className="text-center">{team.parent_name ?? ''}</td>
                                    <td className="text-center">{team.updated_at}</td>
                                    <td className="text-center ps-row-actions">
                                        <Link href={`/admin/teams/${team.id}/edit`} title="Cập nhật"><i className="fa fa-pencil-square-o" /></Link>
                                        <button type="button" title="Xóa" onClick={() => window.confirm(`Xóa nhóm ${team.name}?`) && router.delete(`/admin/teams/${team.id}`, { preserveScroll: true })}><i className="fa fa-trash" /></button>
                                    </td>
                                </tr>
                            )) : <tr><td colSpan="10" className="ps-empty">Không có đội, nhóm phù hợp.</td></tr>}
                        </tbody>
                    </table>
                </div>

                <div className="ps-pagination-bar">
                    <span>{teams.from ?? 0} - {teams.to ?? 0} / {teams.total ?? 0}</span>
                    <ul className="pagination pagination-sm">
                        {(teams.links ?? []).map((link, index) => (
                            <li key={`${link.label}-${index}`} className={`${link.active ? 'active' : ''} ${!link.url ? 'disabled' : ''}`}>
                                <button type="button" disabled={!link.url} onClick={() => navigate(link.url)} dangerouslySetInnerHTML={{ __html: link.label }} />
                            </li>
                        ))}
                    </ul>
                </div>

                <Link className="ps-floating-add btn btn-primary" href="/admin/teams/create"><i className="fa fa-plus" /> Thêm đội, nhóm</Link>
            </section>
        </AppLayout>
    );
}
