import { useEffect, useState } from 'react';

import { formatMoneyInput, parseMoneyInput } from '@/lib/format';
import { cn } from '@/lib/utils';

export function CurrencyInput({
    value,
    onChange,
    className,
    inputMode = 'numeric',
    placeholder,
    showSuffix = true,
    suffix = 'đ',
    ...props
}) {
    const [display, setDisplay] = useState(() => formatMoneyInput(value));
    const useBootstrap = typeof className === 'string' && className.includes('form-control');

    useEffect(() => {
        setDisplay(formatMoneyInput(value));
    }, [value]);

    const handleChange = (event) => {
        const parsed = parseMoneyInput(event.target.value);
        setDisplay(event.target.value.replace(/[^\d.,\s]/g, ''));
        onChange?.(parsed);
    };

    const handleBlur = () => {
        setDisplay(formatMoneyInput(value));
    };

    const input = (
        <input
            {...props}
            type="text"
            inputMode={inputMode}
            autoComplete="off"
            placeholder={placeholder ?? '0'}
            className={cn(
                useBootstrap ? null : 'h-9 w-full min-w-0 rounded-lg border border-transparent bg-muted/70 px-3 py-1 text-base tabular-nums outline-none md:text-sm',
                'tabular-nums',
                className,
            )}
            value={display}
            onChange={handleChange}
            onBlur={handleBlur}
        />
    );

    if (!showSuffix) {
        return input;
    }

    return (
        <div className={cn('ps-currency-input', useBootstrap && 'ps-currency-input--bootstrap')}>
            {input}
            <span className="ps-currency-input__suffix" aria-hidden="true">{suffix}</span>
        </div>
    );
}
