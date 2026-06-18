import { usePage } from '@inertiajs/react';
import { Info } from 'lucide-react';
import { useState } from 'react';

import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import { useI18n } from '@/providers/I18nProvider';
import { findPageGuide } from '@/lib/page-guides';

/**
 * Header "i" button — opens a popup explaining the current page's business logic.
 * Hidden when no guide content exists for the page.
 */
export function PageInfoButton() {
    const { url } = usePage();
    const { locale, t } = useI18n();
    const [open, setOpen] = useState(false);

    const pathname = url.split('?')[0];
    const guide = findPageGuide(pathname, locale);

    if (!guide) return null;

    return (
        <>
            <Tooltip>
                <TooltipTrigger asChild>
                    <Button
                        variant="ghost"
                        size="icon-sm"
                        aria-label={t('page_info.tooltip')}
                        onClick={() => setOpen(true)}
                    >
                        <Info className="size-4" />
                    </Button>
                </TooltipTrigger>
                <TooltipContent>{t('page_info.what_is_this')}</TooltipContent>
            </Tooltip>

            <Dialog open={open} onOpenChange={setOpen}>
                <DialogContent className="max-h-[85vh] max-w-xl gap-0 overflow-hidden p-0">
                    <DialogHeader className="border-b border-border/60 px-6 py-4">
                        <div className="flex items-center gap-2">
                            <span className="flex size-7 items-center justify-center rounded-full bg-primary/10 text-primary">
                                <Info className="size-4" />
                            </span>
                            <DialogTitle>{guide.title}</DialogTitle>
                        </div>
                        <DialogDescription className="pt-1">{guide.intro}</DialogDescription>
                    </DialogHeader>

                    <div className="max-h-[60vh] space-y-5 overflow-y-auto px-6 py-5">
                        {guide.sections.map((section) => (
                            <div key={section.heading}>
                                <h3 className="mb-2 text-sm font-semibold text-foreground">
                                    {section.heading}
                                </h3>
                                <ul className="space-y-1.5">
                                    {section.items.map((item, index) => (
                                        <li
                                            key={index}
                                            className="flex gap-2 text-sm leading-relaxed text-muted-foreground"
                                        >
                                            <span className="mt-[7px] size-1.5 shrink-0 rounded-full bg-primary/60" />
                                            <span>{item}</span>
                                        </li>
                                    ))}
                                </ul>
                            </div>
                        ))}
                    </div>
                </DialogContent>
            </Dialog>
        </>
    );
}
