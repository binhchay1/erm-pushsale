import { useEffect, useState } from 'react';

import { Input } from '@/components/ui/input';
import { formatMoneyInput, parseMoneyInput } from '@/lib/format';
import { cn } from '@/lib/utils';

export function CurrencyInput({
    value,
    onChange,
    className,
    inputMode = 'numeric',
    placeholder,
    ...props
}) {
    const [display, setDisplay] = useState(() => formatMoneyInput(value));

    useEffect(() => {
        setDisplay(formatMoneyInput(value));
    }, [value]);

    return (
        <Input
            {...props}
            type="text"
            inputMode={inputMode}
            autoComplete="off"
            placeholder={placeholder}
            className={cn('tabular-nums', className)}
            value={display}
            onChange={(event) => {
                const nextDisplay = event.target.value;
                setDisplay(nextDisplay);
                onChange?.(parseMoneyInput(nextDisplay));
            }}
            onBlur={() => {
                setDisplay(formatMoneyInput(value));
            }}
        />
    );
}
