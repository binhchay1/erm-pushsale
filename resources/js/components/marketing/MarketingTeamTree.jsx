import { Tree, TreeNode } from 'react-organizational-chart';

import { cn } from '@/lib/utils';
import { formatCurrency, formatPercent } from '@/lib/format';

function RevenueNodeCard({ node }) {
    return (
        <div
            className={cn(
                'mx-auto w-[240px] rounded-xl border bg-card px-4 py-4 text-center shadow-sm transition-all hover:shadow-md',
                node.isHighPerformer &&
                    'border-emerald-500/60 bg-gradient-to-b from-emerald-500/10 to-card ring-1 ring-emerald-500/20'
            )}
        >
            <p className="truncate text-sm font-semibold">{node.name}</p>
            {node.roleLabel ? (
                <p className="mt-0.5 truncate text-[11px] text-muted-foreground">{node.roleLabel}</p>
            ) : null}
            <div className="mt-3 grid grid-cols-2 gap-2 border-t border-border/60 pt-3">
                <div>
                    <p className="text-[10px] uppercase tracking-wide text-muted-foreground">% Chốt</p>
                    <p
                        className={cn(
                            'text-base font-bold tabular-nums',
                            node.isHighPerformer ? 'text-emerald-600 dark:text-emerald-400' : 'text-foreground'
                        )}
                    >
                        {formatPercent(node.conversionRate)}
                    </p>
                </div>
                <div>
                    <p className="text-[10px] uppercase tracking-wide text-muted-foreground">Doanh thu</p>
                    <p
                        className={cn(
                            'text-sm font-bold tabular-nums leading-tight',
                            node.isHighPerformer ? 'text-emerald-600 dark:text-emerald-400' : 'text-foreground'
                        )}
                    >
                        {formatCurrency(node.revenue)}
                    </p>
                </div>
            </div>
            {node.isHighPerformer ? (
                <p className="mt-2 text-[10px] font-medium uppercase tracking-wide text-emerald-600 dark:text-emerald-400">
                    Hiệu suất cao
                </p>
            ) : null}
        </div>
    );
}

function RevenueBranch({ node }) {
    const label = <RevenueNodeCard node={node} />;

    if (!node.children?.length) {
        return <TreeNode label={label} />;
    }

    return (
        <TreeNode label={label}>
            {node.children.map((child) => (
                <RevenueBranch key={child.id} node={child} />
            ))}
        </TreeNode>
    );
}

export function MarketingTeamTree({ roots }) {
    if (!roots?.length) {
        return (
            <p className="rounded-xl border border-dashed px-6 py-16 text-center text-sm text-muted-foreground">
                Chưa có dữ liệu team Marketing. Gán nhân viên Marketing vào phòng ban tại{' '}
                <span className="font-medium text-foreground">/admin/users</span>.
            </p>
        );
    }

    return (
        <div className="overflow-x-auto pb-4">
            <div className="inline-block min-w-full px-2 py-4 [&_ul]:!p-0">
                {roots.map((root) => (
                    <Tree
                        key={root.id}
                        lineWidth="2px"
                        lineColor="hsl(var(--border))"
                        lineBorderRadius="8px"
                        label={<RevenueNodeCard node={root} />}
                    >
                        {root.children?.map((child) => (
                            <RevenueBranch key={child.id} node={child} />
                        ))}
                    </Tree>
                ))}
            </div>
        </div>
    );
}
