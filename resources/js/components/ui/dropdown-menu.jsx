import * as React from 'react';
import { DropdownMenu as DropdownMenuPrimitive } from 'radix-ui';
import { CheckIcon, ChevronRightIcon } from 'lucide-react';

import { cn } from '@/lib/utils';

function DropdownMenu(props) {
    return <DropdownMenuPrimitive.Root data-slot="dropdown-menu" {...props} />;
}

function DropdownMenuTrigger(props) {
    return <DropdownMenuPrimitive.Trigger data-slot="dropdown-menu-trigger" {...props} />;
}

function DropdownMenuGroup(props) {
    return <DropdownMenuPrimitive.Group data-slot="dropdown-menu-group" {...props} />;
}

function DropdownMenuPortal(props) {
    return <DropdownMenuPrimitive.Portal data-slot="dropdown-menu-portal" {...props} />;
}

function DropdownMenuSub(props) {
    return <DropdownMenuPrimitive.Sub data-slot="dropdown-menu-sub" {...props} />;
}

function DropdownMenuRadioGroup(props) {
    return <DropdownMenuPrimitive.RadioGroup data-slot="dropdown-menu-radio-group" {...props} />;
}

function DropdownMenuSubTrigger({ className, inset, children, ...props }) {
    return (
        <DropdownMenuPrimitive.SubTrigger
            data-slot="dropdown-menu-sub-trigger"
            className={cn(
                'flex cursor-default items-center gap-2 rounded-md px-2 py-1.5 text-sm outline-none select-none focus:bg-muted data-[state=open]:bg-muted',
                inset && 'pl-8',
                className
            )}
            {...props}
        >
            {children}
            <ChevronRightIcon className="ml-auto size-4 text-muted-foreground" />
        </DropdownMenuPrimitive.SubTrigger>
    );
}

function DropdownMenuSubContent({ className, ...props }) {
    return (
        <DropdownMenuPrimitive.SubContent
            data-slot="dropdown-menu-sub-content"
            className={cn(
                'z-50 min-w-[8rem] overflow-hidden rounded-lg border border-border bg-popover p-1 text-popover-foreground shadow-md data-open:animate-in data-open:fade-in-0 data-open:zoom-in-95 data-closed:animate-out data-closed:fade-out-0 data-closed:zoom-out-95',
                className
            )}
            {...props}
        />
    );
}

function DropdownMenuContent({ className, sideOffset = 4, ...props }) {
    return (
        <DropdownMenuPortal>
            <DropdownMenuPrimitive.Content
                data-slot="dropdown-menu-content"
                sideOffset={sideOffset}
                className={cn(
                    'z-50 max-h-[min(var(--radix-dropdown-menu-content-available-height),24rem)] min-w-[12rem] overflow-y-auto rounded-lg border border-border bg-popover p-1 text-popover-foreground shadow-md',
                    'data-open:animate-in data-open:fade-in-0 data-open:zoom-in-95 data-closed:animate-out data-closed:fade-out-0 data-closed:zoom-out-95',
                    className
                )}
                {...props}
            />
        </DropdownMenuPortal>
    );
}

function DropdownMenuItem({ className, inset, ...props }) {
    return (
        <DropdownMenuPrimitive.Item
            data-slot="dropdown-menu-item"
            className={cn(
                'relative flex cursor-default items-center gap-2 rounded-md px-2 py-1.5 text-sm outline-none select-none transition-colors focus:bg-muted focus:text-foreground data-[disabled]:pointer-events-none data-[disabled]:opacity-50',
                inset && 'pl-8',
                className
            )}
            {...props}
        />
    );
}

function DropdownMenuCheckboxItem({ className, children, checked, ...props }) {
    return (
        <DropdownMenuPrimitive.CheckboxItem
            data-slot="dropdown-menu-checkbox-item"
            className={cn(
                'relative flex cursor-default items-center gap-2 rounded-md py-1.5 pr-2 pl-8 text-sm outline-none select-none focus:bg-muted',
                className
            )}
            checked={checked}
            {...props}
        >
            <span className="pointer-events-none absolute left-2 flex size-3.5 items-center justify-center">
                <DropdownMenuPrimitive.ItemIndicator>
                    <CheckIcon className="size-4" />
                </DropdownMenuPrimitive.ItemIndicator>
            </span>
            {children}
        </DropdownMenuPrimitive.CheckboxItem>
    );
}

function DropdownMenuRadioItem({ className, children, ...props }) {
    return (
        <DropdownMenuPrimitive.RadioItem
            data-slot="dropdown-menu-radio-item"
            className={cn(
                'relative flex cursor-default items-center gap-2 rounded-md py-1.5 pr-2 pl-8 text-sm outline-none select-none focus:bg-muted',
                className
            )}
            {...props}
        >
            <span className="pointer-events-none absolute left-2 flex size-3.5 items-center justify-center">
                <DropdownMenuPrimitive.ItemIndicator>
                    <CheckIcon className="size-4 fill-current" />
                </DropdownMenuPrimitive.ItemIndicator>
            </span>
            {children}
        </DropdownMenuPrimitive.RadioItem>
    );
}

function DropdownMenuLabel({ className, inset, ...props }) {
    return (
        <DropdownMenuPrimitive.Label
            data-slot="dropdown-menu-label"
            className={cn('px-2 py-1.5 text-xs font-medium text-muted-foreground', inset && 'pl-8', className)}
            {...props}
        />
    );
}

function DropdownMenuSeparator({ className, ...props }) {
    return (
        <DropdownMenuPrimitive.Separator
            data-slot="dropdown-menu-separator"
            className={cn('-mx-1 my-1 h-px bg-border', className)}
            {...props}
        />
    );
}

function DropdownMenuShortcut({ className, ...props }) {
    return (
        <span
            data-slot="dropdown-menu-shortcut"
            className={cn('ml-auto text-xs tracking-widest text-muted-foreground', className)}
            {...props}
        />
    );
}

export {
    DropdownMenu,
    DropdownMenuTrigger,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuCheckboxItem,
    DropdownMenuRadioItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuShortcut,
    DropdownMenuGroup,
    DropdownMenuPortal,
    DropdownMenuSub,
    DropdownMenuSubContent,
    DropdownMenuSubTrigger,
    DropdownMenuRadioGroup,
};
