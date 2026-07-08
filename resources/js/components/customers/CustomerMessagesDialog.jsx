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
import { formatDateTime } from '@/lib/format';
import { cn } from '@/lib/utils';
import { useT } from '@/providers/I18nProvider';


export function CustomerMessagesDialog({ order }) {
    const t = useT();
    const listRef = useRef(null);
    const [open, setOpen] = useState(false);
    const [loading, setLoading] = useState(false);
    const [sending, setSending] = useState(false);
    const [customer, setCustomer] = useState(null);
    const [messages, setMessages] = useState([]);
    const [canWrite, setCanWrite] = useState(false);
    const [draft, setDraft] = useState('');

    const loadMessages = async () => {
        setLoading(true);
        try {
            const data = await apiGet(`/customers/orders/${order.id}/messages`);
            setCustomer(data.customer ?? null);
            setMessages(data.messages ?? []);
            setCanWrite(Boolean(data.canWrite));
        } catch (error) {
            toast.error(error.message ?? t('operations.customer_interactions.load_failed'));
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        if (!open) return;
        loadMessages();
    }, [open, order.id]);

    useEffect(() => {
        if (!open || loading) return;
        requestAnimationFrame(() => {
            if (listRef.current) {
                listRef.current.scrollTop = listRef.current.scrollHeight;
            }
        });
    }, [loading, messages, open]);

    const sendMessage = async () => {
        const content = draft.trim();
        if (!content || sending || !canWrite) return;

        setSending(true);
        try {
            const data = await apiPost(`/customers/orders/${order.id}/messages`, { message: content });
            setMessages((current) => [...current, data.message]);
            setDraft('');
        } catch (error) {
            toast.error(error.message ?? t('operations.customer_interactions.send_failed'));
        } finally {
            setSending(false);
        }
    };

    const handleKeyDown = (event) => {
        if ((event.ctrlKey || event.metaKey) && event.key === 'Enter') {
            event.preventDefault();
            sendMessage();
        }
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

            <DialogContent className="max-h-[88vh] max-w-[min(920px,calc(100vw-2rem))] overflow-hidden p-0">
                <DialogHeader className="border-b px-6 py-5 pr-14">
                    <DialogTitle>{t('operations.customer_interactions.messages_title')}</DialogTitle>
                    <DialogDescription>
                        {(customer?.name ?? order.customerName ?? '—')} · {(customer?.phone ?? order.customerPhone ?? '—')}
                        {' · '}
                        {customer?.orderCode ?? order.orderCode}
                    </DialogDescription>
                </DialogHeader>

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

                    <div ref={listRef} className="max-h-[54vh] min-h-72 overflow-y-auto px-6 py-5">
                        {loading ? (
                            <div className="flex h-full min-h-64 items-center justify-center text-muted-foreground">
                                <Loader2 className="mr-2 size-5 animate-spin" />
                                {t('operations.customer_interactions.loading')}
                            </div>
                        ) : messages.length ? (
                            <div className="space-y-4">
                                {messages.map((message) => (
                                    <div
                                        key={message.id}
                                        className={cn('flex', message.isMine ? 'justify-end' : 'justify-start')}
                                    >
                                        <div
                                            className={cn(
                                                'max-w-[82%] rounded-2xl border px-4 py-3 shadow-sm',
                                                message.isMine
                                                    ? 'border-primary/30 bg-primary text-primary-foreground'
                                                    : 'bg-card text-card-foreground',
                                            )}
                                        >
                                            <div className="flex flex-wrap items-center gap-x-2 gap-y-1 text-xs">
                                                <span className="font-semibold">{message.authorName}</span>
                                                {(message.authorOrgLevel || message.authorRole) && (
                                                    <span className={cn(message.isMine ? 'text-primary-foreground/75' : 'text-muted-foreground')}>
                                                        {message.authorOrgLevel || message.authorRole}
                                                    </span>
                                                )}
                                                {message.orderCode && (
                                                    <span className={cn(message.isMine ? 'text-primary-foreground/75' : 'text-muted-foreground')}>
                                                        · {message.orderCode}
                                                    </span>
                                                )}
                                            </div>
                                            <div className="mt-2 whitespace-pre-wrap break-words text-sm leading-relaxed">
                                                {message.message}
                                            </div>
                                            <div
                                                className={cn(
                                                    'mt-2 text-right text-[11px]',
                                                    message.isMine ? 'text-primary-foreground/70' : 'text-muted-foreground',
                                                )}
                                            >
                                                {formatDateTime(message.createdAt)}
                                            </div>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        ) : (
                            <div className="flex min-h-64 items-center justify-center rounded-lg border border-dashed text-sm text-muted-foreground">
                                {t('operations.customer_interactions.messages_empty')}
                            </div>
                        )}
                    </div>

                    <div className="border-t bg-background px-6 py-4">
                        {canWrite ? (
                            <div className="flex items-end gap-3">
                                <textarea
                                    value={draft}
                                    onChange={(event) => setDraft(event.target.value)}
                                    onKeyDown={handleKeyDown}
                                    maxLength={2000}
                                    rows={3}
                                    placeholder={t('operations.customer_interactions.message_placeholder')}
                                    className="min-h-20 flex-1 resize-y rounded-lg border border-input bg-background px-3 py-2 text-sm outline-none transition focus:border-ring focus:ring-3 focus:ring-ring/20"
                                />
                                <Button
                                    type="button"
                                    onClick={sendMessage}
                                    disabled={sending || !draft.trim()}
                                    className="mb-0.5"
                                >
                                    {sending ? <Loader2 className="size-4 animate-spin" /> : <Send className="size-4" />}
                                    {t('operations.customer_interactions.send')}
                                </Button>
                            </div>
                        ) : (
                            <div className="rounded-lg border border-dashed bg-muted/30 px-4 py-3 text-sm text-muted-foreground">
                                {t('operations.customer_interactions.read_only')}
                            </div>
                        )}
                        {canWrite && (
                            <div className="mt-2 flex justify-between text-[11px] text-muted-foreground">
                                <span>{t('operations.customer_interactions.send_hint')}</span>
                                <span>{draft.length}/2000</span>
                            </div>
                        )}
                    </div>
                </div>
            </DialogContent>
        </Dialog>
    );
}
