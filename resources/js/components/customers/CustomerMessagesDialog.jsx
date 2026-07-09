import { usePage } from '@inertiajs/react';
import { Loader2, MessageSquare, Send } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { toast } from 'sonner';

import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { apiGet, apiPost } from '@/lib/api';
import { getEcho } from '@/lib/echo';
import { formatDateTime } from '@/lib/format';
import { cn } from '@/lib/utils';
import { useT } from '@/providers/I18nProvider';

function MessageBubble({ message, external = false }) {
    const t = useT();
    const isMine = Boolean(message.isMine) || message.direction === 'outbound';
    const senderName = message.authorName ?? message.senderName;
    const senderMeta = message.authorOrgLevel ?? message.authorRole ?? message.senderType;
    const createdAt = message.createdAt ?? message.sentAt;

    return (
        <div className={cn('flex', isMine ? 'justify-end' : 'justify-start')}>
            <div
                className={cn(
                    'max-w-[82%] rounded-2xl border px-4 py-3 shadow-sm',
                    isMine
                        ? 'border-primary/30 bg-primary text-primary-foreground'
                        : 'bg-card text-card-foreground',
                )}
            >
                <div className="flex flex-wrap items-center gap-x-2 gap-y-1 text-xs">
                    <span className="font-semibold">{senderName || (external ? t('operations.customer_interactions.customer_actor') : '—')}</span>
                    {senderMeta && (
                        <span className={cn(isMine ? 'text-primary-foreground/75' : 'text-muted-foreground')}>
                            {senderMeta}
                        </span>
                    )}
                    {message.orderCode && (
                        <span className={cn(isMine ? 'text-primary-foreground/75' : 'text-muted-foreground')}>
                            · {message.orderCode}
                        </span>
                    )}
                </div>
                <div className="mt-2 whitespace-pre-wrap break-words text-sm leading-relaxed">
                    {message.message || '—'}
                </div>
                {Array.isArray(message.attachments) && message.attachments.length > 0 && (
                    <div className={cn('mt-2 text-xs', isMine ? 'text-primary-foreground/75' : 'text-muted-foreground')}>
                        {t('operations.customer_interactions.attachments_count', { count: message.attachments.length })}
                    </div>
                )}
                <div
                    className={cn(
                        'mt-2 text-right text-[11px]',
                        isMine ? 'text-primary-foreground/70' : 'text-muted-foreground',
                    )}
                >
                    {formatDateTime(createdAt)}
                </div>
            </div>
        </div>
    );
}

function ThreadBody({ loading, messages, emptyText, listRef, external = false }) {
    const t = useT();

    return (
        <div ref={listRef} className="max-h-[54vh] min-h-72 overflow-y-auto px-6 py-5">
            {loading ? (
                <div className="flex h-full min-h-64 items-center justify-center text-muted-foreground">
                    <Loader2 className="mr-2 size-5 animate-spin" />
                    {t('operations.customer_interactions.loading')}
                </div>
            ) : messages.length ? (
                <div className="space-y-4">
                    {messages.map((message) => (
                        <MessageBubble
                            key={`${external ? 'pancake' : 'internal'}-${message.id ?? message.externalId}`}
                            message={message}
                            external={external}
                        />
                    ))}
                </div>
            ) : (
                <div className="flex min-h-64 items-center justify-center rounded-lg border border-dashed text-sm text-muted-foreground">
                    {emptyText}
                </div>
            )}
        </div>
    );
}

function Composer({ value, setValue, canWrite, sending, onSend, placeholder, readOnlyText }) {
    const t = useT();

    const handleKeyDown = (event) => {
        if ((event.ctrlKey || event.metaKey) && event.key === 'Enter') {
            event.preventDefault();
            onSend();
        }
    };

    return (
        <div className="border-t bg-background px-6 py-4">
            {canWrite ? (
                <div className="flex items-end gap-3">
                    <textarea
                        value={value}
                        onChange={(event) => setValue(event.target.value)}
                        onKeyDown={handleKeyDown}
                        maxLength={2000}
                        rows={3}
                        placeholder={placeholder}
                        className="min-h-20 flex-1 resize-y rounded-lg border border-input bg-background px-3 py-2 text-sm outline-none transition focus:border-ring focus:ring-3 focus:ring-ring/20"
                    />
                    <Button
                        type="button"
                        onClick={onSend}
                        disabled={sending || !value.trim()}
                        className="mb-0.5"
                    >
                        {sending ? <Loader2 className="size-4 animate-spin" /> : <Send className="size-4" />}
                        {t('operations.customer_interactions.send')}
                    </Button>
                </div>
            ) : (
                <div className="rounded-lg border border-dashed bg-muted/30 px-4 py-3 text-sm text-muted-foreground">
                    {readOnlyText}
                </div>
            )}
            {canWrite && (
                <div className="mt-2 flex justify-between text-[11px] text-muted-foreground">
                    <span>{t('operations.customer_interactions.send_hint')}</span>
                    <span>{value.length}/2000</span>
                </div>
            )}
        </div>
    );
}


function upsertMessage(list, message) {
    const key = String(message.id ?? message.externalId ?? '');
    if (!key) return [...list, message];

    const index = list.findIndex((item) => String(item.id ?? item.externalId ?? '') === key);
    if (index === -1) {
        return [...list, message];
    }

    const next = [...list];
    next[index] = { ...next[index], ...message };
    return next;
}

function withViewerFlag(message, authUserId) {
    const actorId = message.authorId ?? message.sentByUserId;

    return {
        ...message,
        isMine: actorId !== undefined && actorId !== null && String(actorId) === authUserId,
    };
}

export function CustomerMessagesDialog({ order }) {
    const t = useT();
    const { props } = usePage();
    const authUserId = props.auth?.user?.id !== undefined && props.auth?.user?.id !== null
        ? String(props.auth.user.id)
        : null;
    const internalListRef = useRef(null);
    const pancakeListRef = useRef(null);
    const [open, setOpen] = useState(false);
    const [activeTab, setActiveTab] = useState('internal');

    const [loading, setLoading] = useState(false);
    const [sending, setSending] = useState(false);
    const [customer, setCustomer] = useState(null);
    const [messages, setMessages] = useState([]);
    const [canWrite, setCanWrite] = useState(false);
    const [draft, setDraft] = useState('');
    const [internalRealtime, setInternalRealtime] = useState(null);

    const [pancakeLoading, setPancakeLoading] = useState(false);
    const [pancakeSending, setPancakeSending] = useState(false);
    const [pancakeMessages, setPancakeMessages] = useState([]);
    const [pancakeCanWrite, setPancakeCanWrite] = useState(false);
    const [pancakeDraft, setPancakeDraft] = useState('');
    const [pancakeStatus, setPancakeStatus] = useState(null);
    const [pancakeLoaded, setPancakeLoaded] = useState(false);
    const [pancakeRealtime, setPancakeRealtime] = useState(null);

    const loadMessages = async ({ silent = false } = {}) => {
        if (!silent) setLoading(true);
        try {
            const data = await apiGet(`/customers/orders/${order.id}/messages`);
            setCustomer(data.customer ?? null);
            setMessages(data.messages ?? []);
            setCanWrite(Boolean(data.canWrite));
            setInternalRealtime(data.realtime ?? null);
        } catch (error) {
            if (!silent) toast.error(error.message ?? t('operations.customer_interactions.load_failed'));
        } finally {
            if (!silent) setLoading(false);
        }
    };

    const loadPancakeMessages = async ({ silent = false } = {}) => {
        if (!silent) setPancakeLoading(true);
        try {
            const data = await apiGet(`/customers/orders/${order.id}/pancake-messages`);
            setPancakeMessages(data.messages ?? []);
            setPancakeCanWrite(Boolean(data.canWrite));
            setPancakeStatus(data);
            setPancakeRealtime(data.realtime ?? null);
            setPancakeLoaded(true);
        } catch (error) {
            if (!silent) toast.error(error.message ?? t('operations.customer_interactions.load_failed'));
        } finally {
            if (!silent) setPancakeLoading(false);
        }
    };

    useEffect(() => {
        if (!open) return;
        loadMessages();
    }, [open, order.id]);

    useEffect(() => {
        if (!open || activeTab !== 'pancake' || pancakeLoaded) return;
        loadPancakeMessages();
    }, [open, activeTab, pancakeLoaded, order.id]);

    useEffect(() => {
        if (!open) {
            setPancakeLoaded(false);
            setInternalRealtime(null);
            setPancakeRealtime(null);
        }
    }, [open, order.id]);

    useEffect(() => {
        if (!open || !internalRealtime?.channel) return undefined;

        const echo = getEcho(props.reverb);
        if (!echo) return undefined;

        const channel = echo.private(internalRealtime.channel);
        const eventName = internalRealtime.event ?? '.customer.internal-message.created';
        const handler = (payload) => {
            if (!payload?.message) return;
            setMessages((current) => upsertMessage(current, withViewerFlag(payload.message, authUserId)));
        };

        channel.listen(eventName, handler);

        return () => {
            try {
                channel.stopListening(eventName, handler);
                echo.leave(internalRealtime.channel);
            } catch {
                // Realtime is a progressive enhancement.
            }
        };
    }, [open, internalRealtime?.channel, internalRealtime?.event, props.reverb, authUserId]);

    useEffect(() => {
        if (!open || !pancakeRealtime?.channel) return undefined;

        const echo = getEcho(props.reverb);
        if (!echo) return undefined;

        const channel = echo.private(pancakeRealtime.channel);
        const eventName = pancakeRealtime.event ?? '.customer.pancake-message.created';
        const handler = (payload) => {
            if (!payload?.message) return;
            setPancakeMessages((current) => upsertMessage(current, withViewerFlag(payload.message, authUserId)));
        };

        channel.listen(eventName, handler);

        return () => {
            try {
                channel.stopListening(eventName, handler);
                echo.leave(pancakeRealtime.channel);
            } catch {
                // ignore
            }
        };
    }, [open, pancakeRealtime?.channel, pancakeRealtime?.event, props.reverb, authUserId]);

    useEffect(() => {
        if (!open || activeTab !== 'internal') return undefined;
        const pollMs = internalRealtime?.pollMs ?? 15000;
        if (!pollMs) return undefined;

        const timer = window.setInterval(() => loadMessages({ silent: true }), pollMs);
        return () => window.clearInterval(timer);
    }, [open, activeTab, internalRealtime?.pollMs, order.id]);

    useEffect(() => {
        if (!open || activeTab !== 'pancake' || !pancakeStatus?.connected) return undefined;
        const pollMs = pancakeRealtime?.pollMs ?? 7000;
        if (!pollMs) return undefined;

        const timer = window.setInterval(() => loadPancakeMessages({ silent: true }), pollMs);
        return () => window.clearInterval(timer);
    }, [open, activeTab, pancakeStatus?.connected, pancakeRealtime?.pollMs, order.id]);

    useEffect(() => {
        if (!open || loading || activeTab !== 'internal') return;
        requestAnimationFrame(() => {
            if (internalListRef.current) {
                internalListRef.current.scrollTop = internalListRef.current.scrollHeight;
            }
        });
    }, [loading, messages, open, activeTab]);

    useEffect(() => {
        if (!open || pancakeLoading || activeTab !== 'pancake') return;
        requestAnimationFrame(() => {
            if (pancakeListRef.current) {
                pancakeListRef.current.scrollTop = pancakeListRef.current.scrollHeight;
            }
        });
    }, [pancakeLoading, pancakeMessages, open, activeTab]);

    const sendMessage = async () => {
        const content = draft.trim();
        if (!content || sending || !canWrite) return;

        setSending(true);
        try {
            const data = await apiPost(`/customers/orders/${order.id}/messages`, { message: content });
            setMessages((current) => upsertMessage(current, data.message));
            setDraft('');
        } catch (error) {
            toast.error(error.message ?? t('operations.customer_interactions.send_failed'));
        } finally {
            setSending(false);
        }
    };

    const sendPancakeMessage = async () => {
        const content = pancakeDraft.trim();
        if (!content || pancakeSending || !pancakeCanWrite) return;

        setPancakeSending(true);
        try {
            const data = await apiPost(`/customers/orders/${order.id}/pancake-messages`, { message: content });
            setPancakeMessages((current) => upsertMessage(current, data.message));
            setPancakeDraft('');
        } catch (error) {
            toast.error(error.message ?? t('operations.customer_interactions.send_failed'));
        } finally {
            setPancakeSending(false);
        }
    };

    const tabClass = (tab) => cn(
        'rounded-md px-3 py-1.5 text-sm font-medium transition-colors',
        activeTab === tab
            ? 'bg-primary text-primary-foreground shadow-sm'
            : 'text-muted-foreground hover:bg-muted hover:text-foreground',
    );

    const renderPancakeNotice = () => {
        if (!pancakeStatus) return null;

        if (!pancakeStatus.connected) {
            return (
                <div className="border-b bg-amber-50 px-6 py-3 text-sm text-amber-800 dark:bg-amber-950/30 dark:text-amber-200">
                    {t('operations.customer_interactions.pancake_not_connected')}
                </div>
            );
        }

        if (pancakeStatus.source === 'cache') {
            return (
                <div className="border-b bg-amber-50 px-6 py-3 text-sm text-amber-800 dark:bg-amber-950/30 dark:text-amber-200">
                    {t('operations.customer_interactions.pancake_cache_notice')}
                </div>
            );
        }

        if (pancakeStatus.source === 'error') {
            return (
                <div className="border-b bg-destructive/10 px-6 py-3 text-sm text-destructive">
                    {t('operations.customer_interactions.pancake_error_notice')}
                </div>
            );
        }

        return null;
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button
                    type="button"
                    variant="ghost"
                    size="icon-xs"
                    className="text-sky-300 hover:bg-sky-50 hover:text-sky-600 dark:hover:bg-sky-950/40"
                    title={t('operations.customer_interactions.messages_title')}
                    aria-label={t('operations.customer_interactions.messages_title')}
                >
                    <MessageSquare className="size-4" />
                </Button>
            </DialogTrigger>

            <DialogContent className="max-h-[88vh] max-w-[min(980px,calc(100vw-2rem))] overflow-hidden p-0">
                <DialogHeader className="border-b px-6 py-5 pr-14">
                    <DialogTitle>{t('operations.customer_interactions.messages_title')}</DialogTitle>
                    <DialogDescription>
                        {(customer?.name ?? order.customerName ?? '—')} · {(customer?.phone ?? order.customerPhone ?? '—')}
                        {' · '}
                        {customer?.orderCode ?? order.orderCode}
                    </DialogDescription>
                </DialogHeader>

                <div className="flex items-center justify-between gap-3 border-b px-6 py-3">
                    <div className="inline-flex rounded-lg border bg-background p-1">
                        <button type="button" onClick={() => setActiveTab('internal')} className={tabClass('internal')}>
                            {t('operations.customer_interactions.internal_tab')}
                        </button>
                        <button type="button" onClick={() => setActiveTab('pancake')} className={tabClass('pancake')}>
                            {t('operations.customer_interactions.pancake_tab')}
                        </button>
                    </div>
                    <div className="text-xs text-muted-foreground">
                        {t('operations.customer_interactions.live_status')}
                    </div>
                </div>

                <div className="grid min-h-0 grid-rows-[auto_1fr_auto]">
                    <div className="border-b bg-muted/30 px-6 py-3 text-sm">
                        {customer?.address && (
                            <div>
                                <span className="font-semibold">{t('operations.customer_interactions.address')}:</span>{' '}
                                {customer.address}
                            </div>
                        )}
                        {customer?.note && (
                            <div className="mt-1 whitespace-pre-wrap text-muted-foreground">
                                <span className="font-semibold text-foreground">{t('operations.customer_interactions.customer_note')}:</span>{' '}
                                {customer.note}
                            </div>
                        )}
                    </div>

                    {activeTab === 'internal' ? (
                        <>
                            <ThreadBody
                                listRef={internalListRef}
                                loading={loading}
                                messages={messages}
                                emptyText={t('operations.customer_interactions.messages_empty')}
                            />
                            <Composer
                                value={draft}
                                setValue={setDraft}
                                canWrite={canWrite}
                                sending={sending}
                                onSend={sendMessage}
                                placeholder={t('operations.customer_interactions.message_placeholder')}
                                readOnlyText={t('operations.customer_interactions.read_only')}
                            />
                        </>
                    ) : (
                        <>
                            {renderPancakeNotice()}
                            <ThreadBody
                                listRef={pancakeListRef}
                                loading={pancakeLoading}
                                messages={pancakeMessages}
                                emptyText={t('operations.customer_interactions.pancake_messages_empty')}
                                external
                            />
                            <Composer
                                value={pancakeDraft}
                                setValue={setPancakeDraft}
                                canWrite={pancakeCanWrite}
                                sending={pancakeSending}
                                onSend={sendPancakeMessage}
                                placeholder={t('operations.customer_interactions.pancake_message_placeholder')}
                                readOnlyText={t('operations.customer_interactions.pancake_read_only')}
                            />
                        </>
                    )}
                </div>
            </DialogContent>
        </Dialog>
    );
}
