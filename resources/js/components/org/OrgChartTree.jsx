import { Tree, TreeNode } from 'react-organizational-chart';

import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { cn } from '@/lib/utils';
import { formatCurrency, formatPercent } from '@/lib/format';

const ROLE_STYLES = {
    admin: 'border-violet-500/50 bg-gradient-to-b from-violet-500/10 to-card',
    sales: 'border-sky-500/50 bg-gradient-to-b from-sky-500/10 to-card',
    marketing: 'border-amber-500/50 bg-gradient-to-b from-amber-500/10 to-card',
    warehouse: 'border-emerald-500/50 bg-gradient-to-b from-emerald-500/10 to-card',
    accounting: 'border-slate-500/40 bg-gradient-to-b from-slate-500/10 to-card',
    allocator: 'border-cyan-500/50 bg-gradient-to-b from-cyan-500/10 to-card',
};

const ORG_LEVEL_STYLES = {
    head: 'ring-2 ring-violet-500/30',
    supervisor: 'ring-2 ring-primary/20',
};

function roleStyle(node) {
    return ROLE_STYLES[node.role] ?? 'border-border bg-card';
}

function PersonCard({ node }) {
    const orgLevelKey = node.org_level_label?.toLowerCase().includes('giám')
        ? 'head'
        : node.org_level_label?.toLowerCase().includes('quản')
          ? 'supervisor'
          : null;

    return (
        <div
            className={cn(
                'mx-auto w-[240px] rounded-2xl border px-4 py-3 text-center shadow-sm transition-shadow hover:shadow-md',
                roleStyle(node),
                orgLevelKey && ORG_LEVEL_STYLES[orgLevelKey],
                node.is_self && 'border-primary ring-2 ring-primary/30'
            )}
        >
            <Avatar className="mx-auto mb-2 size-12 border border-border/80 shadow-sm">
                {node.avatar_url ? <AvatarImage src={node.avatar_url} alt={node.name} /> : null}
                <AvatarFallback className="text-sm font-semibold">{node.initials}</AvatarFallback>
            </Avatar>
            <p className="truncate text-sm font-semibold">{node.name}</p>
            {node.job_title ? (
                <p className="truncate text-xs text-muted-foreground">{node.job_title}</p>
            ) : null}
            <div className="mt-2 flex flex-wrap justify-center gap-1">
                {node.role_label ? (
                    <span className="rounded-full bg-background/80 px-2 py-0.5 text-[10px] font-medium">
                        {node.role_label}
                    </span>
                ) : null}
                {node.org_level_label ? (
                    <span className="rounded-full bg-primary/10 px-2 py-0.5 text-[10px] font-medium text-primary">
                        {node.org_level_label}
                    </span>
                ) : null}
                {node.team_name ? (
                    <span className="rounded-full bg-muted px-2 py-0.5 text-[10px] text-muted-foreground">
                        {node.team_name}
                    </span>
                ) : null}
            </div>

            {node.show_metrics ? (
                <div className="mt-3 grid grid-cols-2 gap-2 border-t border-border/60 pt-3">
                    <div>
                        <p className="text-[10px] uppercase tracking-wide text-muted-foreground">% Chốt</p>
                        <p className="text-sm font-bold tabular-nums text-primary">
                            {formatPercent(node.conversion_rate)}
                        </p>
                    </div>
                    <div>
                        <p className="text-[10px] uppercase tracking-wide text-muted-foreground">Doanh thu</p>
                        <p className="text-xs font-bold tabular-nums leading-tight">
                            {formatCurrency(node.revenue)}
                        </p>
                        <p className="text-[9px] text-muted-foreground">30 ngày</p>
                    </div>
                </div>
            ) : null}

            {node.is_self ? (
                <p className="mt-2 text-[10px] font-medium uppercase tracking-wide text-primary">Bạn</p>
            ) : null}
        </div>
    );
}

function OrgChartBranch({ node }) {
    const label = <PersonCard node={node} />;

    if (!node.children?.length) {
        return <TreeNode label={label} />;
    }

    return (
        <TreeNode label={label}>
            {node.children.map((child) => (
                <OrgChartBranch key={child.id} node={child} />
            ))}
        </TreeNode>
    );
}

export function OrgChartTree({ roots }) {
    if (!roots?.length) {
        return (
            <p className="rounded-xl border border-dashed px-6 py-16 text-center text-sm text-muted-foreground">
                Chưa có dữ liệu sơ đồ tổ chức. Gán quản lý trực tiếp và phòng ban cho nhân viên tại{' '}
                <span className="font-medium text-foreground">/admin/users</span>.
            </p>
        );
    }

    return (
        <div className="max-w-full overflow-x-auto pb-4">
            <div className="mx-auto inline-block min-w-min px-2 py-4 [&_table]:mx-auto [&_ul]:!p-0">
                {roots.map((root) =>
                    root.children?.length ? (
                        <Tree
                            key={root.id}
                            lineWidth="2px"
                            lineColor="hsl(var(--primary) / 0.35)"
                            lineBorderRadius="10px"
                            label={<PersonCard node={root} />}
                        >
                            {root.children.map((child) => (
                                <OrgChartBranch key={child.id} node={child} />
                            ))}
                        </Tree>
                    ) : (
                        <div key={root.id} className="flex justify-center">
                            <PersonCard node={root} />
                        </div>
                    )
                )}
            </div>
        </div>
    );
}
