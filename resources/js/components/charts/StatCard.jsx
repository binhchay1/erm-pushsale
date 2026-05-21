import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { cn } from '@/lib/utils';

export function StatCard({ title, value, hint, className, accent }) {
    return (
        <Card className={cn('overflow-hidden transition-shadow hover:shadow-md', className)}>
            <CardHeader className="pb-2">
                <CardTitle className="text-sm font-medium text-muted-foreground">
                    {title}
                </CardTitle>
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
