
import * as React from "react";
import { X, ChevronsUpDown } from "lucide-react";
import { cn } from "@/lib/utils";
import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";
import { Label } from "@/components/ui/label";
import {
    DropdownMenu,
    DropdownMenuCheckboxItem,
    DropdownMenuContent,
    DropdownMenuTrigger,
    DropdownMenuLabel,
    DropdownMenuSeparator
} from "@/components/ui/dropdown-menu";

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
    placeholder = "Select items...",
    error
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
            <Label className="text-zinc-300 font-medium">{label}</Label>

            <DropdownMenu>
                <DropdownMenuTrigger asChild>
                    <Button
                        variant="outline"
                        role="combobox"
                        className={cn(
                            "w-full justify-between h-auto min-h-[2.75rem] py-2 bg-zinc-950 border-zinc-800 text-zinc-100 hover:bg-zinc-900 group",
                            error && "border-red-500 ring-red-500/20"
                        )}
                    >
                        <div className="flex flex-wrap gap-1 items-center text-left">
                            {!displayPlaceholder ? (
                                selectedValues.map((val) => {
                                    const option = options.find((o) => o.value === val);
                                    return (
                                        <Badge
                                            key={val}
                                            variant="secondary"
                                            className="bg-zinc-800 text-zinc-100 hover:bg-zinc-700 font-normal pr-1 shrink-0"
                                        >
                                            {option?.label || val}
                                            <div
                                                className="ml-1 ring-offset-background rounded-full outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2 cursor-pointer"
                                                onMouseDown={(e) => {
                                                    e.preventDefault();
                                                    e.stopPropagation();
                                                }}
                                                onClick={(e) => handleRemove(val, e)}
                                            >
                                                <X className="h-3 w-3 text-zinc-400 hover:text-zinc-200" />
                                            </div>
                                        </Badge>
                                    );
                                })
                            ) : (
                                <span className="text-zinc-500 font-normal">{placeholder}</span>
                            )}
                        </div>
                        <ChevronsUpDown className="ml-2 h-4 w-4 shrink-0 opacity-50 text-zinc-500" />
                    </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent className="w-[--radix-dropdown-menu-trigger-width] min-w-[var(--radix-dropdown-menu-trigger-width)] max-h-[300px] overflow-y-auto bg-zinc-900 border-zinc-800 text-zinc-100">
                    <DropdownMenuLabel className="text-zinc-400 text-xs">{label}</DropdownMenuLabel>
                    <DropdownMenuSeparator className="bg-zinc-800" />
                    {options.length > 0 ? (
                        options.map((option) => (
                            <DropdownMenuCheckboxItem
                                key={option.value}
                                checked={selectedValues.includes(option.value)}
                                onCheckedChange={(checked) => handleSelect(option.value, checked)}
                                className="text-zinc-100 focus:bg-zinc-800 focus:text-zinc-50 cursor-pointer"
                            >
                                {option.label}
                            </DropdownMenuCheckboxItem>
                        ))
                    ) : (
                        <div className="p-2 text-sm text-zinc-500 text-center">No items available</div>
                    )}
                </DropdownMenuContent>
            </DropdownMenu>

            {error && <div className="text-red-500 text-sm">{error}</div>}
        </div>
    );
}
