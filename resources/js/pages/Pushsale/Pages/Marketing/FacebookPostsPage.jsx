import { Head, router } from '@inertiajs/react';
import { useEffect, useState } from 'react';

import AppLayout from '@/layouts/AppLayout';
import { PushsalePagination } from '@/components/pagination/PushsalePagination';
import { PushsaleSelect } from '@/components/pushsale/PushsaleSelect';
import { useT } from '@/providers/I18nProvider';

export default function FacebookPostsPage({
    posts,
    filters = {},
    pageOptions = [],
    sourceOptions = [],
    routeUrl = '/admin/marketing/facebook/posts',
    syncUrl = '/admin/marketing/facebook/posts/sync',
    recordsUrl = '/admin/marketing/facebook/posts',
    activeMenuCode = '2.5.3',
}) {
    const t = useT();
    const f = (key) => t(`pages.facebook_connection.${key}`);
    const [form, setForm] = useState({
        page_id: filters.page_id ?? '',
        attached: filters.attached ?? '',
        search: filters.search ?? '',
        per_page: filters.per_page ?? 20,
    });

    useEffect(() => {
        setForm({
            page_id: filters.page_id ?? '',
            attached: filters.attached ?? '',
            search: filters.search ?? '',
            per_page: filters.per_page ?? 20,
        });
    }, [filters.page_id, filters.attached, filters.search, filters.per_page]);

    const set = (key, value) => setForm((current) => ({ ...current, [key]: value, page: 1 }));
    const search = (event) => {
        event?.preventDefault();
        router.get(routeUrl, Object.fromEntries(Object.entries(form).filter(([, value]) => value !== '' && value !== null)), {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    };
    const syncPosts = () => router.post(syncUrl, {}, { preserveScroll: true });
    const updatePost = (post, payload) => router.patch(`${recordsUrl}/${post.id}`, payload, {
        preserveScroll: true,
        preserveState: true,
        only: ['posts', 'flash'],
    });

    const rows = posts?.data ?? [];
    const attachedOptions = [
        { value: '-1', label: f('all') },
        { value: '1', label: f('attached') },
        { value: '0', label: f('unattached') },
    ];

    return (
        <AppLayout activeMenuCode={activeMenuCode}>
            <Head title={f('posts_title')} />
            <section className="ps-facebook-posts-page pushsale-template-host-v83" data-page-code={activeMenuCode}>
                <div className="m-header-wrap">
                    <div className="m-header">
                        <div className="ps-page-title">{f('posts_title')}</div>
                    </div>
                </div>
                <form className="ps-facebook-post-filter" onSubmit={search}>
                    <PushsaleSelect
                        options={[{ value: '', label: f('select_many') }, ...pageOptions.map((page) => ({ value: page.page_id, label: page.page_name, subLabel: page.page_id }))]}
                        value={form.page_id}
                        placeholder={f('select_many')}
                        onChange={(value) => set('page_id', value)}
                        searchable
                    />
                    <PushsaleSelect
                        options={attachedOptions}
                        value={form.attached || '-1'}
                        placeholder={f('all')}
                        onChange={(value) => set('attached', value === '-1' ? '' : value)}
                        searchable={false}
                    />
                    <input
                        type="text"
                        className="form-control text-center"
                        value={form.search}
                        placeholder={f('keyword_placeholder')}
                        onChange={(event) => set('search', event.target.value)}
                    />
                    <button type="submit" className="btn btn-sm btn-primary"><i className="fa fa-search" /> {f('search')}</button>
                    <button type="button" className="btn btn-sm btn-default" onClick={syncPosts}><i className="fa fa-refresh" /> {f('sync_posts')}</button>
                </form>

                <div className="ps-facebook-post-table-wrap">
                    <table className="table table-bordered table-multi-select ps-facebook-post-table">
                        <thead>
                            <tr>
                                <th>{f('stt')}</th>
                                <th>{f('fanpage')}</th>
                                <th>{f('post_id')}</th>
                                <th>{f('content')}</th>
                                <th>{f('posted_at')}</th>
                                <th>{f('use')}</th>
                                <th>{f('data_source')} <span className="text-danger">(*)</span></th>
                                <th>{f('status')}</th>
                                <th>{f('action')}</th>
                            </tr>
                        </thead>
                        <tbody>
                            {rows.length ? rows.map((post, index) => (
                                <tr key={post.id}>
                                    <td className="text-center">{(posts?.from ?? 1) + index}</td>
                                    <td>{post.page_name ?? post.page_id}<br /><span className="small-tip">{post.page_id}</span></td>
                                    <td>{post.post_id}</td>
                                    <td className="ps-facebook-post-content">{post.content}</td>
                                    <td className="text-center">{post.posted_at}</td>
                                    <td className="text-center">
                                        <input type="checkbox" checked={Boolean(post.is_used)} onChange={(event) => updatePost(post, { is_used: event.target.checked })} />
                                    </td>
                                    <td>
                                        <PushsaleSelect
                                            options={sourceOptions}
                                            value={post.landing_connection_id ?? ''}
                                            placeholder={f('all')}
                                            onChange={(value) => updatePost(post, { landing_connection_id: value || null, is_used: Boolean(value) })}
                                            searchable
                                        />
                                    </td>
                                    <td className="text-center">{post.status === 'active' ? f('active') : f('inactive')}</td>
                                    <td className="text-center">
                                        <button type="button" className="btn-icon" title={f('save')} onClick={() => updatePost(post, { is_used: true })}>
                                            <i className="fa fa-save" />
                                        </button>
                                    </td>
                                </tr>
                            )) : (
                                <tr><td colSpan={9} className="text-center text-muted">{f('empty_posts')}</td></tr>
                            )}
                        </tbody>
                    </table>
                    <PushsalePagination meta={posts} routeUrl={routeUrl} filters={form} itemLabel={f('posts_title')} />
                </div>
            </section>
        </AppLayout>
    );
}
