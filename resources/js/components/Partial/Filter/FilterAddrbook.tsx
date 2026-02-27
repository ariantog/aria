import { useState, useEffect } from 'react';
import { router } from '@inertiajs/react';
import { Input } from '@/components/ui/input';
import { Search, X } from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from "@/components/ui/select";

interface Props {
    baseUrl: string;
    filters: {
        search?: string;
        trashed?: string;
        [key: string]: any;
    };
}

export default function FilterAddrbook({ baseUrl, filters }: Props) {
    const [search, setSearch] = useState(filters.search || '');
    const [trashed, setTrashed] = useState(filters.trashed || '');

    const applyFilters = (newFilters?: any) => {
        const params: any = {
            search,
            trashed: trashed === 'normal' ? '' : trashed,
            ...(newFilters || {})
        };

        // Clean up empty values
        Object.keys(params).forEach(key => {
            if (params[key] === null || params[key] === undefined || params[key] === '') {
                delete params[key];
            }
        });

        router.get(baseUrl, params, { preserveState: true, replace: true, preserveScroll: true });
    };

    // Auto-apply logic for search input (Debounced)
    useEffect(() => {
        const timer = setTimeout(() => {
            if (search !== (filters.search || '')) {
                applyFilters({ search });
            }
        }, 500);
        return () => clearTimeout(timer);
    }, [search]);

    const handleReset = () => {
        setSearch('');
        setTrashed('');
        router.get(baseUrl, {}, { preserveState: true, replace: true });
    };

    const hasFilters = search || trashed;

    return (
        <div className="bg-white dark:bg-zinc-900 p-3 rounded-xl border shadow-sm mb-6">
            <div className="flex flex-wrap items-end gap-3">
                {/* Search */}
                <div className="flex-1 min-w-[250px] space-y-1.5">
                    <label className="text-xs font-medium text-zinc-500 ml-1">Search Name / Contact / ID / Phone</label>
                    <div className="relative">
                        <Search className="absolute left-3 top-2.5 h-4 w-4 text-zinc-400" />
                        <Input
                            placeholder="Type to search..."
                            className="pl-9 h-9 bg-zinc-50 dark:bg-zinc-800/50 border-zinc-200 dark:border-zinc-700 font-medium"
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                        />
                    </div>
                </div>

                {/* Show Deleted */}
                <div className="w-[180px] space-y-1.5">
                    <label className="text-xs font-medium text-zinc-500 ml-1">Show Deleted</label>
                    <Select
                        value={trashed || 'normal'}
                        onValueChange={(val) => {
                            setTrashed(val);
                            applyFilters({ trashed: val === 'normal' ? '' : val });
                        }}
                    >
                        <SelectTrigger className="h-9 bg-zinc-50 dark:bg-zinc-800/50 border-zinc-200 dark:border-zinc-700">
                            <SelectValue placeholder="Excluded" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="normal">Excluded</SelectItem>
                            <SelectItem value="with">With Deleted</SelectItem>
                            <SelectItem value="only">Only Deleted</SelectItem>
                        </SelectContent>
                    </Select>
                </div>

                {/* Clear Actions */}
                {hasFilters && (
                    <div className="shrink-0 pb-0.5">
                        <Button
                            variant="ghost"
                            size="sm"
                            onClick={handleReset}
                            className="h-9 px-3 text-zinc-500 hover:text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-900/10 transition-colors"
                        >
                            <X className="mr-2 h-4 w-4" /> Clear
                        </Button>
                    </div>
                )}
            </div>
        </div>
    );
}
