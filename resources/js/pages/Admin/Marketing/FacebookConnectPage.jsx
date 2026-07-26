import { Head, router } from '@inertiajs/react';

import { PageHeader } from '@/components/layout/PageHeader';
import AppLayout from '@/layouts/AppLayout';
import { useT } from '@/providers/I18nProvider';

export default function FacebookConnectPage({ pages = [], syncUrl = '/admin/marketing/facebook/connect/sync', postsUrl = '/admin/marketing/facebook/posts', activeMenuCode = '2.5.2' }) {
    const t = useT();
    const f = (key) => t(`pages.facebook_connection.${key}`);

    const sync = () => router.post(syncUrl, {}, { preserveScroll: true });

    return (
        <AppLayout activeMenuCode={activeMenuCode}>
            <Head title={f('connect_title')} />
            <PageHeader title={f('connect_title')} pageCode={activeMenuCode} />
            <section className="ps-facebook-connect-page" data-page-code={activeMenuCode}>
                <div className="ps-facebook-connect-card">
                    <div className="box">
                        <div className="box-body">
                            <button type="button" className="btn btn-block btn-facebook" onClick={sync}>
                                <i className="fa fa-facebook-official" /> <b>{f('continue_with_facebook')}</b>
                            </button>
                            <div className="ps-facebook-connect-actions">
                                <button type="button" className="btn btn-default btn-sm" onClick={sync}><i className="fa fa-refresh" /> {f('sync_demo')}</button>
                                <a className="btn btn-primary btn-sm" href={postsUrl}><i className="fa fa-list" /> {f('posts_title')}</a>
                            </div>
                        </div>
                    </div>
                </div>

                <div className="ps-facebook-page-list">
                    <h4>{f('connected_pages')}</h4>
                    <table className="table table-bordered table-multi-select">
                        <thead>
                            <tr>
                                <th>{f('page_id')}</th>
                                <th>{f('page_name')}</th>
                                <th>{f('marketer')}</th>
                                <th>{f('status')}</th>
                            </tr>
                        </thead>
                        <tbody>
                            {pages.length ? pages.map((page) => (
                                <tr key={page.id ?? page.page_id}>
                                    <td>{page.page_id}</td>
                                    <td>{page.page_name}</td>
                                    <td>{page.marketer_name ?? '—'}{page.marketer_email ? <><br /><span className="small-tip">({page.marketer_email})</span></> : null}</td>
                                    <td className="text-center">{page.is_active ? f('active') : f('inactive')}</td>
                                </tr>
                            )) : (
                                <tr><td colSpan={4} className="text-center text-muted">{f('empty_pages')}</td></tr>
                            )}
                        </tbody>
                    </table>
                </div>
            </section>
        </AppLayout>
    );
}
