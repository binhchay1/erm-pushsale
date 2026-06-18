import { Crown, UserRound } from 'lucide-react';

import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { formatCurrency, formatPercent } from '@/lib/format';
import { cn } from '@/lib/utils';
import { useT } from '@/providers/I18nProvider';

const DEPT_ACCENTS = {
    sale: 'border-l-sky-400',
    marketing: 'border-l-amber-400',
    warehouse: 'border-l-emerald-400',
    allocator: 'border-l-cyan-400',
    accounting: 'border-l-slate-400',
    other: 'border-l-border',
};

function PersonRow({ person, badge }) {
    const t = useT();

    return (
        <div
            className={cn(
                'flex items-center gap-2.5 rounded-lg px-2 py-1.5',
                person.is_self && 'bg-primary/5 ring-1 ring-primary/20'
            )}
        >
            <Avatar className="size-8 shrink-0 border border-border/70">
                {person.avatar_url ? <AvatarImage src={person.avatar_url} alt={person.name} /> : null}
                <AvatarFallback className="text-[10px] font-semibold">{person.initials}</AvatarFallback>
            </Avatar>
            <div className="min-w-0 flex-1">
                <p className="flex items-center gap-1.5 truncate text-sm font-medium">
                    {person.name}
                    {badge && (
                        <span className="rounded-full bg-primary/10 px-1.5 py-px text-[10px] font-semibold text-primary">
                            {badge}
                        </span>
                    )}
                    {person.is_self && (
                        <span className="rounded-full bg-primary px-1.5 py-px text-[10px] font-semibold text-primary-foreground">
                            {t('org.you')}
                        </span>
                    )}
                </p>
                <p className="truncate text-[11px] text-muted-foreground">
                    {person.job_title ?? person.role_label}
                </p>
            </div>
            {person.show_metrics && (
                <div className="shrink-0 text-right">
                    <p className="text-xs font-semibold tabular-nums text-primary">
                        {t('org.closing_rate', { rate: formatPercent(person.conversion_rate) })}
                    </p>
                    <p className="text-[11px] tabular-nums text-muted-foreground">
                        {formatCurrency(person.revenue)}
                    </p>
                </div>
            )}
        </div>
    );
}

function HeadCard({ person, label }) {
    const t = useT();
    const headLabel = label ?? t('org.dept_head');

    return (
        <div className="flex items-center gap-3 rounded-xl border border-violet-200 bg-violet-50/60 px-4 py-3 dark:border-violet-900 dark:bg-violet-950/30">
            <Avatar className="size-11 border border-violet-200 dark:border-violet-800">
                {person.avatar_url ? <AvatarImage src={person.avatar_url} alt={person.name} /> : null}
                <AvatarFallback className="text-xs font-semibold">{person.initials}</AvatarFallback>
            </Avatar>
            <div className="min-w-0 flex-1">
                <p className="flex items-center gap-1.5 text-sm font-semibold">
                    <Crown className="size-3.5 text-violet-500" />
                    {person.name}
                    {person.is_self && (
                        <span className="rounded-full bg-primary px-1.5 py-px text-[10px] font-semibold text-primary-foreground">
                            {t('org.you')}
                        </span>
                    )}
                </p>
                <p className="text-[11px] text-muted-foreground">{person.job_title ?? headLabel}</p>
            </div>
            {person.show_metrics && (
                <div className="shrink-0 text-right">
                    <p className="text-xs font-semibold tabular-nums text-violet-600 dark:text-violet-400">
                        {t('org.closing_rate', { rate: formatPercent(person.conversion_rate) })}
                    </p>
                    <p className="text-[11px] tabular-nums text-muted-foreground">
                        {formatCurrency(person.revenue)}
                    </p>
                </div>
            )}
        </div>
    );
}

function TeamCard({ team }) {
    const t = useT();

    return (
        <div className="flex flex-col rounded-xl border border-border/70 bg-card shadow-sm">
            <div className="flex items-center justify-between border-b border-border/60 px-3 py-2">
                <p className="text-sm font-semibold">{team.name}</p>
                <span className="rounded-full bg-muted px-2 py-0.5 text-[11px] font-medium text-muted-foreground">
                    {t('org.members_count', { count: team.member_count })}
                </span>
            </div>
            <div className="space-y-0.5 p-2">
                {team.leader && <PersonRow person={team.leader} badge={t('org.team_lead')} />}
                {team.members.map((person) => (
                    <PersonRow key={person.id} person={person} />
                ))}
                {!team.leader && !team.members.length && (
                    <p className="px-2 py-3 text-center text-xs text-muted-foreground">
                        {t('org.team_empty')}
                    </p>
                )}
            </div>
        </div>
    );
}

function DepartmentSection({ department }) {
    const t = useT();

    return (
        <section
            className={cn(
                'rounded-xl border border-border/70 border-l-4 bg-card p-4 shadow-sm sm:p-5',
                DEPT_ACCENTS[department.key] ?? DEPT_ACCENTS.other
            )}
        >
            <div className="mb-3 flex flex-wrap items-center justify-between gap-2">
                <h2 className="text-base font-semibold">
                    {t('org.dept_label', { name: department.name })}
                </h2>
                <span className="rounded-full bg-muted px-2.5 py-0.5 text-xs font-medium text-muted-foreground">
                    {t('org.staff_count', { count: department.member_count })}
                </span>
            </div>

            {department.head && (
                <div className="mb-4">
                    <HeadCard person={department.head} />
                </div>
            )}

            {department.teams.length > 0 && (
                <div className="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                    {department.teams.map((team) => (
                        <TeamCard key={team.id} team={team} />
                    ))}
                </div>
            )}

            {department.unassigned.length > 0 && (
                <div className="mt-3 rounded-xl border border-dashed border-border/70 p-2">
                    <p className="px-2 pb-1 pt-1.5 text-[11px] font-medium uppercase tracking-wide text-muted-foreground">
                        {t('org.unassigned')}
                    </p>
                    {department.unassigned.map((person) => (
                        <PersonRow key={person.id} person={person} />
                    ))}
                </div>
            )}
        </section>
    );
}

export function OrgChartBoard({ admins = [], departments = [] }) {
    const t = useT();

    if (!admins.length && !departments.length) {
        return (
            <p className="rounded-xl border border-dashed px-6 py-16 text-center text-sm text-muted-foreground">
                {t('org.empty_staff_hint')}
            </p>
        );
    }

    return (
        <div className="space-y-4">
            {admins.length > 0 && (
                <section className="rounded-xl border border-border/70 border-l-4 border-l-violet-400 bg-card p-4 shadow-sm sm:p-5">
                    <div className="mb-3 flex items-center gap-2">
                        <UserRound className="size-4 text-violet-500" />
                        <h2 className="text-base font-semibold">{t('org.admin_board')}</h2>
                    </div>
                    <div className="grid gap-2 sm:grid-cols-2 xl:grid-cols-3">
                        {admins.map((person) => (
                            <PersonRow key={person.id} person={person} badge={t('org.admin_role')} />
                        ))}
                    </div>
                </section>
            )}

            {departments.map((department) => (
                <DepartmentSection key={department.key} department={department} />
            ))}
        </div>
    );
}
