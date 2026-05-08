import { router } from '@inertiajs/react';
import { Search, X, Filter } from 'lucide-react';
import { useState, useEffect } from 'react';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

interface Props {
    baseUrl: string;
    filters: {
        from?: string;
        to?: string;
        type?: string | number;
        invoice_number?: string;
        min_total?: string | number;
        max_total?: string | number;
        [key: string]: any;
    };
    typeOptions?: { id: number; name: string }[];
}

export default function FilterTransaction({
    baseUrl,
    filters,
    typeOptions = [],
}: Props) {
    const [from, setFrom] = useState(filters.from || '');
    const [to, setTo] = useState(filters.to || '');
    const [invoice, setInvoice] = useState(filters.invoice_number || '');
    const [minTotal, setMinTotal] = useState(filters.min_total || '');
    const [maxTotal, setMaxTotal] = useState(filters.max_total || '');
    const [type, setType] = useState(filters.type?.toString() || 'all');

    const applyFilters = (newFilters: any) => {
        const mergedFilters = { ...filters, ...newFilters };

        const cleanFilters = Object.keys(mergedFilters).reduce(
            (acc: any, key) => {
                const value = mergedFilters[key];
                if (
                    value !== null &&
                    value !== undefined &&
                    value !== '' &&
                    value !== 'all'
                ) {
                    acc[key] = value;
                }
                return acc;
            },
            {},
        );

        router.get(baseUrl, cleanFilters, {
            preserveState: true,
            replace: true,
        });
    };

    // Debounce invoice
    useEffect(() => {
        const timer = setTimeout(() => {
            if (invoice !== (filters.invoice_number || '')) {
                applyFilters({ invoice_number: invoice });
            }
        }, 500);
        return () => clearTimeout(timer);
    }, [invoice]);

    const handleDateChange = (dateType: 'from' | 'to', value: string) => {
        if (dateType === 'from') {
            setFrom(value);
            applyFilters({ from: value });
        } else {
            setTo(value);
            applyFilters({ to: value });
        }
    };

    const handleTypeChange = (value: string) => {
        setType(value);
        applyFilters({ type: value === 'all' ? null : value });
    };

    const handleTotalChange = (totalType: 'min' | 'max', value: string) => {
        if (totalType === 'min') {
            setMinTotal(value);
            applyFilters({ min_total: value });
        } else {
            setMaxTotal(value);
            applyFilters({ max_total: value });
        }
    };

    const clearFilters = () => {
        setFrom('');
        setTo('');
        setInvoice('');
        setMinTotal('');
        setMaxTotal('');
        setType('all');
        router.get(baseUrl, {}, { preserveState: true, replace: true });
    };

    const hasFilters =
        from || to || invoice || minTotal || maxTotal || type !== 'all';

    return (
        <div className="mb-6 flex flex-col gap-4 rounded-xl border bg-white p-4 shadow-sm dark:bg-zinc-900">
            <div className="flex flex-wrap items-center gap-4">
                {/* Type Select */}
                <div className="w-full sm:w-48">
                    <Select value={type} onValueChange={handleTypeChange}>
                        <SelectTrigger className="h-9 border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-800/50">
                            <SelectValue placeholder="Type" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">All Types</SelectItem>
                            {typeOptions.map((opt) => (
                                <SelectItem
                                    key={opt.id}
                                    value={opt.id.toString()}
                                >
                                    {opt.name}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </div>

                {/* Date Range */}
                <div className="flex items-center gap-2">
                    <div className="flex h-9 items-center gap-2 rounded-lg border bg-zinc-50 px-2 dark:bg-zinc-800/50">
                        <span className="text-[10px] font-bold text-zinc-500 uppercase">
                            From
                        </span>
                        <input
                            type="date"
                            className="h-full border-none bg-transparent text-xs focus:outline-none dark:text-zinc-200"
                            value={from}
                            onChange={(e) =>
                                handleDateChange('from', e.target.value)
                            }
                        />
                    </div>
                    <div className="flex h-9 items-center gap-2 rounded-lg border bg-zinc-50 px-2 dark:bg-zinc-800/50">
                        <span className="text-[10px] font-bold text-zinc-500 uppercase">
                            To
                        </span>
                        <input
                            type="date"
                            className="h-full border-none bg-transparent text-xs focus:outline-none dark:text-zinc-200"
                            value={to}
                            onChange={(e) =>
                                handleDateChange('to', e.target.value)
                            }
                        />
                    </div>
                </div>

                {/* Advanced Filters Toggle (Dropdown for more space) */}
                <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                        <Button
                            variant="outline"
                            size="sm"
                            className="h-9 border-zinc-200 dark:border-zinc-700"
                        >
                            <Filter className="mr-2 h-4 w-4" /> More Filters
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end" className="w-72 p-4">
                        <DropdownMenuLabel className="px-0">
                            Advanced Filters
                        </DropdownMenuLabel>
                        <DropdownMenuSeparator className="my-2" />
                        <div className="space-y-4">
                            <div className="space-y-1.5">
                                <label className="text-xs font-medium text-zinc-500">
                                    Invoice Number
                                </label>
                                <Input
                                    placeholder="INV-..."
                                    className="h-8 text-xs"
                                    value={invoice}
                                    onChange={(e) => setInvoice(e.target.value)}
                                />
                            </div>
                            <div className="grid grid-cols-2 gap-2">
                                <div className="space-y-1.5">
                                    <label className="text-xs font-medium text-zinc-500">
                                        Min Total
                                    </label>
                                    <Input
                                        type="number"
                                        placeholder="0"
                                        className="h-8 text-xs"
                                        value={minTotal}
                                        onChange={(e) =>
                                            handleTotalChange(
                                                'min',
                                                e.target.value,
                                            )
                                        }
                                    />
                                </div>
                                <div className="space-y-1.5">
                                    <label className="text-xs font-medium text-zinc-500">
                                        Max Total
                                    </label>
                                    <Input
                                        type="number"
                                        placeholder="Max"
                                        className="h-8 text-xs"
                                        value={maxTotal}
                                        onChange={(e) =>
                                            handleTotalChange(
                                                'max',
                                                e.target.value,
                                            )
                                        }
                                    />
                                </div>
                            </div>
                        </div>
                    </DropdownMenuContent>
                </DropdownMenu>

                {hasFilters && (
                    <Button
                        variant="ghost"
                        size="sm"
                        onClick={clearFilters}
                        className="h-9 px-3 text-rose-600 hover:bg-rose-50 hover:text-rose-700 dark:hover:bg-rose-900/10"
                    >
                        <X className="mr-2 h-4 w-4" /> Clear
                    </Button>
                )}
            </div>
        </div>
    );
}
