import React from 'react';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from "@/components/ui/select";
import { Label } from '@/components/ui/label';
import { LucideIcon } from 'lucide-react';
import { cn } from '@/lib/utils';

interface SelectOption {
    value: string;
    label: string;
}

interface FormSelectProps {
    label: string;
    value: string;
    onValueChange: (value: string) => void;
    options: SelectOption[];
    placeholder?: string;
    error?: string;
    touched?: boolean;
    icon?: LucideIcon;
    required?: boolean;
    disabled?: boolean;
}

export default function FormSelect({
    label,
    value,
    onValueChange,
    options,
    placeholder = "Select an option",
    error,
    touched,
    icon: Icon,
    required,
    disabled
}: FormSelectProps) {
    const isInvalid = !!error;
    const isValid = !error && !!touched && value !== '' && value !== undefined;

    return (
        <div className="space-y-2">
            <Label className="text-zinc-300 font-medium">
                {label} {required && <span className="text-red-500">*</span>}
            </Label>
            <Select
                value={value}
                onValueChange={onValueChange}
                disabled={disabled}
            >
                <SelectTrigger className={cn(
                    "bg-zinc-950 border-zinc-800 focus:border-blue-600 focus:ring-blue-600/20 h-11 text-zinc-100",
                    isInvalid && "border-red-500 ring-red-500/20 focus:border-red-500 focus:ring-red-500/20 bg-red-500/10 text-red-500",
                    isValid && "border-green-500 ring-green-500/20 focus:border-green-500 focus:ring-green-500/20 bg-green-500/10 text-green-500"
                )}>
                    <div className="flex items-center gap-2">
                        {Icon ? (
                            <div className="bg-zinc-800 rounded-full p-0.5">
                                <Icon className="h-3 w-3 text-zinc-400 p-[1px]" />
                            </div>
                        ) : (
                            <div className="bg-zinc-800 rounded-full p-0.5">
                                <div className="h-3 w-3 bg-zinc-400 rounded-full"></div>
                            </div>
                        )}
                        <SelectValue placeholder={placeholder} />
                    </div>
                </SelectTrigger>
                <SelectContent className="bg-zinc-900 border-zinc-800 text-zinc-100">
                    {options.map((option) => (
                        <SelectItem key={option.value} value={option.value} className="focus:bg-zinc-800 focus:text-zinc-100 cursor-pointer">
                            {option.label}
                        </SelectItem>
                    ))}
                    {options.length === 0 && <div className="p-2 text-sm text-zinc-500">No options available</div>}
                </SelectContent>
            </Select>
            {error && <div className="text-red-500 text-sm mt-1">{error}</div>}
        </div>
    );
}
