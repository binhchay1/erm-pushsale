import { PushsalePagination as SharedPushsalePagination } from '@/components/pagination/PushsalePagination';

/**
 * Sale-workspace pagination chrome (variant=ops).
 * Prefer importing SharedPushsalePagination with variant="ops" for new code.
 */
export function PushsalePagination(props) {
    return <SharedPushsalePagination variant="ops" {...props} />;
}
