import React from 'react';
import { Label } from '@/components/ui/label';
import { AsyncCombobox, AsyncComboboxProps } from '@/components/AsyncCombobox';

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
            <Label htmlFor={id} className="text-zinc-300 font-medium">
                {label} {required && <span className="text-red-500">*</span>}
            </Label>
            <AsyncCombobox
                className={`bg-zinc-950 border-zinc-800 text-zinc-100 placeholder:text-zinc-600 focus:ring-blue-600/20 ${className || ''}`}
                isInvalid={!!error}
                {...props}
            />
            {error && <div className="text-red-500 text-sm">{error}</div>}
        </div>
    );
}

export default FormAsyncCombobox;
