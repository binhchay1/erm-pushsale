import * as React from 'react';

import { cn } from '@/lib/utils';

function Switch({ className, checked, onCheckedChange, id, ...props }) {
    return (
        <button
            type="button"
            role="switch"
            aria-checked={checked}
            id={id}
            data-state={checked ? 'checked' : 'unchecked'}
            onClick={() => onCheckedChange?.(!checked)}
            className={cn(
                'peer inline-flex h-5 w-9 shrink-0 cursor-pointer items-center rounded-full border-2 border-transparent shadow-xs transition-colors outline-none focus-visible:ring-3 focus-visible:ring-ring/50 disabled:cursor-not-allowed disabled:opacity-50 data-[state=checked]:bg-primary data-[state=unchecked]:bg-input',
                className
            )}
            {...props}
        >
            <span
                className={cn(
                    'pointer-events-none block size-4 rounded-full bg-background shadow-sm ring-0 transition-transform',
                    checked ? 'translate-x-4' : 'translate-x-0'
                )}
            />
        </button>
    );
}

export { Switch };
