import { Link } from '@inertiajs/react';
import { ChevronDown, ChevronRight, Pencil, Plus, Trash2, Users } from 'lucide-react';
import { useState } from 'react';

import { DeleteRowButton } from '@/components/ui/delete-row-button';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

function DepartmentNode({ node, depth = 0 }) {
    const [open, setOpen] = useState(depth < 2);
    const hasChildren = node.children?.length > 0;

    return (
        <li className="list-none">
            <div
                className={cn(
                    'group flex items-center gap-2 rounded-lg border border-transparent px-2 py-2 transition-colors hover:border-border/60 hover:bg-muted/30',
                    depth > 0 && 'ml-4 border-l border-border/40 pl-3'
                )}
            >
                {hasChildren ? (
                    <button
                        type="button"
                        className="flex size-6 shrink-0 items-center justify-center rounded-md text-muted-foreground hover:bg-muted"
                        onClick={() => setOpen((v) => !v)}
                        aria-label={open ? 'Thu gọn' : 'Mở rộng'}
                    >
                        {open ? <ChevronDown className="size-4" /> : <ChevronRight className="size-4" />}
                    </button>
                ) : (
                    <span className="size-6 shrink-0" />
                )}
                <div className="min-w-0 flex-1">
                    <p className="truncate font-medium">{node.name}</p>
                    <p className="truncate text-xs text-muted-foreground">
                        {node.type_label}
                        {node.leader_name ? ` · Trưởng: ${node.leader_name}` : ''}
                        {' · '}
                        <Users className="mr-0.5 inline size-3" />
                        {node.users_count} nhân sự
                    </p>
                </div>
                <div className="flex shrink-0 items-center gap-1 opacity-100 transition-opacity sm:opacity-0 sm:group-hover:opacity-100">
                    <Button variant="ghost" size="icon-xs" asChild title="Thêm phòng ban con">
                        <Link href={`/admin/teams/create?parent_id=${node.id}`}>
                            <Plus className="size-3.5" />
                        </Link>
                    </Button>
                    <Button variant="ghost" size="icon-xs" asChild title="Sửa">
                        <Link href={`/admin/teams/${node.id}/edit`}>
                            <Pencil className="size-3.5" />
                        </Link>
                    </Button>
                    <DeleteRowButton
                        url={`/admin/teams/${node.id}`}
                        label={node.name}
                        confirmMessage={`Xóa phòng ban "${node.name}"?`}
                    />
                </div>
            </div>
            {hasChildren && open && (
                <ul className="mt-0.5">
                    {node.children.map((child) => (
                        <DepartmentNode key={child.id} node={child} depth={depth + 1} />
                    ))}
                </ul>
            )}
        </li>
    );
}

export function DepartmentTree({ tree }) {
    if (!tree?.length) {
        return (
            <p className="rounded-lg border border-dashed px-4 py-8 text-center text-sm text-muted-foreground">
                Chưa có phòng ban. Tạo phòng ban gốc (ví dụ Marketing, Telesale) rồi thêm nhánh con.
            </p>
        );
    }

    return (
        <ul className="space-y-0.5">
            {tree.map((node) => (
                <DepartmentNode key={node.id} node={node} />
            ))}
        </ul>
    );
}
