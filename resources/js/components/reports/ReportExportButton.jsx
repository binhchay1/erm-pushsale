import { ReportExportControl } from '@/components/reports/ReportExportControl';

/** Thin alias → ReportExportControl dropdown (DRY #5). */
export function ReportExportButton(props) {
    return <ReportExportControl mode="dropdown" {...props} />;
}
