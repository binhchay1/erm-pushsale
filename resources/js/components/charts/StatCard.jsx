import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { cn } from '@/lib/utils';

export function StatCard({ title, value, hint, className, accent, icon: Icon }) {
    return (
        <Card className={cn('overflow-hidden transition-shadow hover:shadow-md', className)}>
            <CardHeader className="flex flex-row items-start justify-between gap-3 pb-2">
                <CardTitle className="text-sm font-medium text-muted-foreground">
                    {title}
                </CardTitle>
                {Icon && (
                    <div className="rounded-lg border bg-muted/50 p-2 text-muted-foreground">
                        <Icon className="size-4" />
                    </div>
                )}
            </CardHeader>
            <CardContent>
                <p
                    className={cn(
                        'text-2xl font-bold tracking-tight tabular-nums',
                        accent && 'text-emerald-600 dark:text-emerald-400'
                    )}
                >
                    {value}
                </p>
                {hint && <p className="mt-1 text-xs text-muted-foreground">{hint}</p>}
            </CardContent>
        </Card>
    );
}
