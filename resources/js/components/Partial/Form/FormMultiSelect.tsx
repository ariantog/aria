import { X, ChevronsUpDown } from 'lucide-react';
import * as React from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuCheckboxItem,
    DropdownMenuContent,
    DropdownMenuTrigger,
    DropdownMenuLabel,
    DropdownMenuSeparator,
} from '@/components/ui/dropdown-menu';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';

interface Option {
    value: string;
    label: string;
}

interface FormMultiSelectProps {
    label: string;
    options: Option[];
    value: string[];
    onValueChange: (value: string[]) => void;
    placeholder?: string;
    error?: string;
}

export default function FormMultiSelect({
    label,
    options,
    value = [], // Ensure default empty array
    onValueChange,
    placeholder = 'Select items...',
    error,
}: FormMultiSelectProps) {
    const selectedValues = Array.isArray(value) ? value : [];

    const handleSelect = (val: string, checked: boolean) => {
        if (checked) {
            onValueChange([...selectedValues, val]);
        } else {
            onValueChange(selectedValues.filter((v) => v !== val));
        }
    };

    const handleRemove = (valueToRemove: string, e: React.MouseEvent) => {
        e.stopPropagation();
        onValueChange(selectedValues.filter((v) => v !== valueToRemove));
    };

    const displayPlaceholder = selectedValues.length === 0;

    return (
        <div className="space-y-2">
            <Label className="font-medium text-zinc-300">{label}</Label>

            <DropdownMenu>
                <DropdownMenuTrigger asChild>
                    <Button
                        variant="outline"
                        role="combobox"
                        className={cn(
                            'group h-auto min-h-[2.75rem] w-full justify-between border-zinc-800 bg-zinc-950 py-2 text-zinc-100 hover:bg-zinc-900',
                            error && 'border-red-500 ring-red-500/20',
                        )}
                    >
                        <div className="flex flex-wrap items-center gap-1 text-left">
                            {!displayPlaceholder ? (
                                selectedValues.map((val) => {
                                    const option = options.find(
                                        (o) => o.value === val,
                                    );
                                    return (
                                        <Badge
                                            key={val}
                                            variant="secondary"
                                            className="shrink-0 bg-zinc-800 pr-1 font-normal text-zinc-100 hover:bg-zinc-700"
                                        >
                                            {option?.label || val}
                                            <div
                                                className="ml-1 cursor-pointer rounded-full ring-offset-background outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2"
                                                onMouseDown={(e) => {
                                                    e.preventDefault();
                                                    e.stopPropagation();
                                                }}
                                                onClick={(e) =>
                                                    handleRemove(val, e)
                                                }
                                            >
                                                <X className="h-3 w-3 text-zinc-400 hover:text-zinc-200" />
                                            </div>
                                        </Badge>
                                    );
                                })
                            ) : (
                                <span className="font-normal text-zinc-500">
                                    {placeholder}
                                </span>
                            )}
                        </div>
                        <ChevronsUpDown className="ml-2 h-4 w-4 shrink-0 text-zinc-500 opacity-50" />
                    </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent className="max-h-[300px] w-[--radix-dropdown-menu-trigger-width] min-w-[var(--radix-dropdown-menu-trigger-width)] overflow-y-auto border-zinc-800 bg-zinc-900 text-zinc-100">
                    <DropdownMenuLabel className="text-xs text-zinc-400">
                        {label}
                    </DropdownMenuLabel>
                    <DropdownMenuSeparator className="bg-zinc-800" />
                    {options.length > 0 ? (
                        options.map((option) => (
                            <DropdownMenuCheckboxItem
                                key={option.value}
                                checked={selectedValues.includes(option.value)}
                                onCheckedChange={(checked) =>
                                    handleSelect(option.value, checked)
                                }
                                className="cursor-pointer text-zinc-100 focus:bg-zinc-800 focus:text-zinc-50"
                            >
                                {option.label}
                            </DropdownMenuCheckboxItem>
                        ))
                    ) : (
                        <div className="p-2 text-center text-sm text-zinc-500">
                            No items available
                        </div>
                    )}
                </DropdownMenuContent>
            </DropdownMenu>

            {error && <div className="text-sm text-red-500">{error}</div>}
        </div>
    );
}
