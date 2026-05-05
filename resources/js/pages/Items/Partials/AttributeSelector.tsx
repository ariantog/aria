import { useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Check, Search, X } from 'lucide-react';
import { cn } from '@/lib/utils';
import { Badge } from '@/components/ui/badge';

interface Option {
    value: string | number;
    label: string;
}

interface AttributeSelectorProps {
    label: string;
    value: string | string[]; // Single value or array of values (for multi-select)
    options: Option[];
    onSelect: (value: string | string[]) => void;
    modalTitle: string;
    searchPlaceholder?: string;
    multiple?: boolean;
    required?: boolean;
    error?: string;
}

export default function AttributeSelector({
    label,
    value,
    options,
    onSelect,
    modalTitle,
    searchPlaceholder = 'Search...',
    multiple = false,
    required = false,
    error,
}: AttributeSelectorProps) {
    const [open, setOpen] = useState(false);
    const [search, setSearch] = useState('');

    const filteredOptions = options.filter((option) =>
        option.label.toLowerCase().includes(search.toLowerCase()),
    );

    const handleSelect = (optionValue: string | number) => {
        const strValue = optionValue.toString();

        if (multiple) {
            const currentValues = Array.isArray(value) ? value : [];
            let newValues;
            if (currentValues.includes(strValue)) {
                newValues = currentValues.filter((v) => v !== strValue);
            } else {
                newValues = [...currentValues, strValue];
            }
            onSelect(newValues);
        } else {
            onSelect(strValue);
            setOpen(false);
        }
    };

    const isSelected = (optionValue: string | number) => {
        const strValue = optionValue.toString();
        if (multiple) {
            return Array.isArray(value) && value.includes(strValue);
        }
        return value === strValue;
    };

    const getSelectedLabel = () => {
        if (multiple) {
            const currentValues = Array.isArray(value) ? value : [];
            if (currentValues.length === 0) return [];
            return currentValues
                .map(
                    (v) => options.find((o) => o.value.toString() === v)?.label,
                )
                .filter(Boolean);
        }
        return options.find((o) => o.value.toString() === value)?.label;
    };

    const selectedLabels = multiple
        ? (getSelectedLabel() as string[])
        : [getSelectedLabel() as string];

    return (
        <div className="space-y-2">
            <Label className={cn('text-zinc-300', error && 'text-red-500')}>
                {label} {required && <span className="text-red-500">*</span>}
            </Label>

            <Dialog open={open} onOpenChange={setOpen}>
                <DialogTrigger asChild>
                    <div
                        className={cn(
                            'flex min-h-[46px] w-full cursor-pointer items-center justify-between rounded-lg border bg-zinc-950 p-3 transition-all hover:border-zinc-700 hover:bg-zinc-900',
                            error ? 'border-red-500' : 'border-zinc-800',
                        )}
                    >
                        <div className="flex flex-wrap gap-2">
                            {selectedLabels.filter(Boolean).length > 0 ? (
                                selectedLabels
                                    .filter(Boolean)
                                    .map((lbl, idx) => (
                                        <Badge
                                            key={idx}
                                            variant="secondary"
                                            className="bg-zinc-800 text-zinc-300 hover:bg-zinc-700"
                                        >
                                            {lbl}
                                            {multiple && (
                                                <X
                                                    className="ml-1 h-3 w-3 cursor-pointer hover:text-white"
                                                    onClick={(e) => {
                                                        e.stopPropagation();
                                                        // Find value by label essentially, or just open modal
                                                        // Ideally we remove it directly but we need the value.
                                                        // For now just letting them open modal is fine or we map back.
                                                        setOpen(true);
                                                    }}
                                                />
                                            )}
                                        </Badge>
                                    ))
                            ) : (
                                <span className="text-sm text-zinc-500">
                                    Select {label}...
                                </span>
                            )}
                        </div>
                        <Button
                            variant="ghost"
                            size="sm"
                            type="button"
                            className="h-8 text-blue-500 hover:bg-blue-900/20 hover:text-blue-400"
                        >
                            Pilih
                        </Button>
                    </div>
                </DialogTrigger>
                <DialogContent
                    className="border-zinc-800 bg-zinc-900 text-zinc-100 sm:max-w-3xl"
                    aria-describedby={undefined}
                >
                    <DialogHeader>
                        <DialogTitle>{modalTitle}</DialogTitle>
                    </DialogHeader>

                    <div className="relative mb-4">
                        <Search className="absolute top-2.5 left-3 h-4 w-4 text-zinc-500" />
                        <Input
                            placeholder={searchPlaceholder}
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            className="border-zinc-800 bg-zinc-950 pl-9 text-zinc-100 focus:border-blue-500 focus:ring-blue-500"
                        />
                    </div>

                    <div className="custom-scrollbar grid max-h-[400px] grid-cols-2 gap-3 overflow-y-auto pr-2 sm:grid-cols-3 md:grid-cols-5">
                        {filteredOptions.map((option) => {
                            const active = isSelected(option.value);
                            return (
                                <div
                                    key={option.value}
                                    onClick={() => handleSelect(option.value)}
                                    className={cn(
                                        'flex cursor-pointer flex-col items-center justify-center gap-2 rounded-lg border p-3 text-center transition-all',
                                        active
                                            ? 'border-blue-500 bg-blue-900/20 text-blue-400'
                                            : 'border-zinc-800 bg-zinc-950 text-zinc-400 hover:border-zinc-700 hover:bg-zinc-900',
                                    )}
                                >
                                    {/* Icon placeholder if needed, for now just text */}

                                    <span className="text-sm font-medium uppercase">
                                        {option.label}
                                    </span>
                                </div>
                            );
                        })}
                        {filteredOptions.length === 0 && (
                            <div className="col-span-full py-8 text-center text-zinc-500">
                                No options found.
                            </div>
                        )}
                    </div>

                    <div className="mt-4 flex justify-end gap-2">
                        <Button
                            variant="outline"
                            type="button"
                            onClick={() => setOpen(false)}
                            className="border-zinc-700 text-zinc-300 hover:bg-zinc-800 hover:text-white"
                        >
                            {multiple ? 'Done' : 'Cancel'}
                        </Button>
                    </div>
                </DialogContent>
            </Dialog>
            {error && <p className="text-sm text-red-500">{error}</p>}
        </div>
    );
}
