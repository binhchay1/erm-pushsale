import { Tree, TreeNode } from 'react-organizational-chart';

import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { cn } from '@/lib/utils';

function PersonCard({ node }) {
    return (
        <div
            className={cn(
                'mx-auto w-[220px] rounded-xl border bg-card px-4 py-3 text-center shadow-sm transition-shadow hover:shadow-md',
                node.is_self && 'border-primary ring-2 ring-primary/20'
            )}
        >
            <Avatar className="mx-auto mb-2 size-12 border border-border/80">
                {node.avatar_url ? <AvatarImage src={node.avatar_url} alt={node.name} /> : null}
                <AvatarFallback>{node.initials}</AvatarFallback>
            </Avatar>
            <p className="truncate text-sm font-semibold">{node.name}</p>
            {node.job_title ? (
                <p className="truncate text-xs text-muted-foreground">{node.job_title}</p>
            ) : null}
            <div className="mt-2 flex flex-wrap justify-center gap-1">
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
        <div className="overflow-x-auto pb-6">
            <div className="inline-block min-w-full px-4 py-2 [&_ul]:!p-0">
                {roots.map((root) =>
                    root.children?.length ? (
                        <Tree
                            key={root.id}
                            lineWidth="2px"
                            lineColor="hsl(var(--border))"
                            lineBorderRadius="8px"
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
