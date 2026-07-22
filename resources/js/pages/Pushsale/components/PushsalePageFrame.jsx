import { PushsalePageShell } from '@/components/layout/PushsalePageShell';

export function PushsalePageFrame({ title, actions = null, filters = null, children, className = '', ...props }) {
    return (
        <PushsalePageShell
            title={title}
            filters={filters}
            actions={actions}
            className={`pushsale-page-frame ${className}`.trim()}
            {...props}
        >
            {children}
        </PushsalePageShell>
    );
}
