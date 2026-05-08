import React from 'react';
import type { AsyncComboboxProps } from '@/components/AsyncCombobox';
import { AsyncCombobox } from '@/components/AsyncCombobox';
import { Label } from '@/components/ui/label';

interface FormAsyncComboboxProps<T> extends AsyncComboboxProps<T> {
    label: string;
    id?: string;
    error?: string;
    touched?: boolean;
    required?: boolean;
}

export function FormAsyncCombobox<T extends { id: string | number }>({
    label,
    id,
    error,
    touched,
    required,
    className,
    ...props
}: FormAsyncComboboxProps<T>) {
    return (
        <div className="space-y-2">
            <Label htmlFor={id} className="font-medium text-zinc-300">
                {label} {required && <span className="text-red-500">*</span>}
            </Label>
            <AsyncCombobox
                className={`border-zinc-800 bg-zinc-950 text-zinc-100 placeholder:text-zinc-600 focus:ring-blue-600/20 ${className || ''}`}
                isInvalid={!!error}
                {...props}
            />
            {error && <div className="text-sm text-red-500">{error}</div>}
        </div>
    );
}

export default FormAsyncCombobox;
