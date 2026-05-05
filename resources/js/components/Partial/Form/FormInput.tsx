import React from 'react';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { LucideIcon } from 'lucide-react';

interface FormInputProps extends React.InputHTMLAttributes<HTMLInputElement> {
    label: string;
    id: string;
    error?: string;
    touched?: boolean;
    icon?: LucideIcon;
    required?: boolean;
}

const FormInput = React.forwardRef<HTMLInputElement, FormInputProps>(
    (
        {
            label,
            id,
            error,
            touched,
            icon: Icon,
            required,
            className,
            value,
            ...props
        },
        ref,
    ) => {
        const isInvalid = !!error;
        const isValid =
            !error && !!touched && value !== '' && value !== undefined;

        return (
            <div className="space-y-2">
                <Label htmlFor={id} className="font-medium text-zinc-300">
                    {label}{' '}
                    {required && <span className="text-red-500">*</span>}
                </Label>
                <div className="relative">
                    {Icon && (
                        <div className="absolute top-2.5 left-3 text-zinc-500">
                            <Icon className="h-5 w-5" />
                        </div>
                    )}
                    <Input
                        id={id}
                        ref={ref}
                        value={value}
                        isInvalid={isInvalid}
                        isValid={isValid}
                        className={`h-11 border-zinc-800 bg-zinc-950 text-zinc-100 placeholder:text-zinc-600 focus:border-blue-600 focus:ring-blue-600/20 ${Icon ? 'pl-10' : ''} ${className || ''}`}
                        {...props}
                    />
                </div>
                {error && <div className="text-sm text-red-500">{error}</div>}
            </div>
        );
    },
);

FormInput.displayName = 'FormInput';

export default FormInput;
