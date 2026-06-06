import { ChevronRight, GitBranch, Users } from 'lucide-react';

import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';

export function OrgStructureCard({ org }) {
    const hasPath = org?.team_path?.length > 0;
    const hasManagers = org?.manager_chain?.length > 0;
    const hasReports = org?.direct_reports?.length > 0;

    if (!hasPath && !hasManagers && !hasReports) {
        return null;
    }

    return (
        <Card className="transition-shadow duration-200 hover:shadow-sm">
            <CardHeader>
                <CardTitle className="flex items-center gap-2 text-base">
                    <GitBranch className="size-4 text-primary" />
                    Cơ cấu tổ chức
                </CardTitle>
                <CardDescription>Phòng ban và báo cáo trực tiếp của bạn</CardDescription>
            </CardHeader>
            <CardContent className="space-y-5 text-sm">
                {hasPath && (
                    <div>
                        <p className="mb-2 text-xs font-medium uppercase tracking-wide text-muted-foreground">
                            Phòng ban
                        </p>
                        <ol className="flex flex-wrap items-center gap-1">
                            {org.team_path.map((node, i) => (
                                <li key={`${node.name}-${i}`} className="flex items-center gap-1">
                                    {i > 0 && <ChevronRight className="size-3 text-muted-foreground" />}
                                    <span className="rounded-md bg-muted px-2 py-0.5">
                                        {node.name}
                                        <span className="ml-1 text-xs text-muted-foreground">({node.type_label})</span>
                                    </span>
                                </li>
                            ))}
                        </ol>
                    </div>
                )}

                {hasManagers && (
                    <div>
                        <p className="mb-2 text-xs font-medium uppercase tracking-wide text-muted-foreground">
                            Quản lý trên
                        </p>
                        <ul className="space-y-1">
                            {org.manager_chain.map((m, i) => (
                                <li
                                    key={`${m.name}-${i}`}
                                    className="flex items-center justify-between rounded-md border border-border/60 px-3 py-2 transition-colors hover:bg-muted/40"
                                >
                                    <span>{m.name}</span>
                                    {m.org_level_label && (
                                        <span className="text-xs text-muted-foreground">{m.org_level_label}</span>
                                    )}
                                </li>
                            ))}
                        </ul>
                    </div>
                )}

                {hasReports && (
                    <div>
                        <p className="mb-2 flex items-center gap-1 text-xs font-medium uppercase tracking-wide text-muted-foreground">
                            <Users className="size-3" />
                            Cấp dưới trực tiếp
                        </p>
                        <ul className="space-y-1">
                            {org.direct_reports.map((m, i) => (
                                <li
                                    key={`${m.name}-${i}`}
                                    className="flex items-center justify-between rounded-md border border-border/60 px-3 py-2"
                                >
                                    <span>{m.name}</span>
                                    {m.org_level_label && (
                                        <span className="text-xs text-muted-foreground">{m.org_level_label}</span>
                                    )}
                                </li>
                            ))}
                        </ul>
                    </div>
                )}
            </CardContent>
        </Card>
    );
}
