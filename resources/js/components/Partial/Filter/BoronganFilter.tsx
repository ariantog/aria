import { router } from '@inertiajs/react';
import { X, Calendar as CalendarIcon, User as UserIcon } from 'lucide-react';
import { useState, useEffect } from 'react';
import { AsyncCombobox } from '@/components/AsyncCombobox';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

interface Props {
    filters: {
        from?: string;
        to?: string;
        jahit_id?: string | number;
        [key: string]: any;
    };
}

export default function BoronganFilter({ filters }: Props) {
    const [from, setFrom] = useState(filters.from || '');
    const [to, setTo] = useState(filters.to || '');
    const [selectedWorker, setSelectedWorker] = useState<any>(null);

    // Initial load for worker object if jahit_id exists
    useEffect(() => {
        const fetchWorker = async () => {
            if (!filters.jahit_id) return;
            try {
                const response = await fetch(
                    `/produksi/workers/lookup?type=jahit&search=${filters.jahit_id}`,
                );
                const data = await response.json();
                const found = data.find(
                    (w: any) =>
                        w.id.toString() === filters.jahit_id?.toString(),
                );
                if (found) setSelectedWorker(found);
            } catch (error) {
                console.error('Failed to fetch worker for filter:', error);
            }
        };
        fetchWorker();
    }, []);

    const applyFilters = (newFilters?: any) => {
        const params: any = {
            from,
            to,
            jahit_id: selectedWorker?.id || null,
            ...(newFilters || {}),
        };

        // Clean up empty values
        Object.keys(params).forEach((key) => {
            if (
                params[key] === null ||
                params[key] === undefined ||
                params[key] === ''
            ) {
                delete params[key];
            }
        });

        router.get('/borongan', params, {
            preserveState: true,
            replace: true,
            preserveScroll: true,
        });
    };

    const handleReset = () => {
        setFrom('');
        setTo('');
        setSelectedWorker(null);
        router.get('/borongan', {}, { preserveState: true, replace: true });
    };

    const hasFilters = from || to || selectedWorker;

    return (
        <div className="mb-6 rounded-xl border border-zinc-200 bg-white p-3 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
            <div className="flex flex-wrap items-end gap-3">
                {/* Date Range */}
                <div className="flex min-w-[300px] gap-2">
                    <div className="flex-1 space-y-1.5">
                        <label className="ml-1 text-xs font-medium text-zinc-500">
                            Date From
                        </label>
                        <div className="relative">
                            <CalendarIcon className="absolute top-2.5 left-3 h-4 w-4 text-zinc-400" />
                            <Input
                                type="date"
                                className="h-9 border-zinc-200 bg-zinc-50 pl-9 font-medium dark:border-zinc-700 dark:bg-zinc-800/50"
                                value={from}
                                onChange={(e) => {
                                    setFrom(e.target.value);
                                    applyFilters({ from: e.target.value });
                                }}
                            />
                        </div>
                    </div>
                    <div className="flex-1 space-y-1.5">
                        <label className="ml-1 text-xs font-medium text-zinc-500">
                            Date To
                        </label>
                        <Input
                            type="date"
                            className="h-9 border-zinc-200 bg-zinc-50 font-medium dark:border-zinc-700 dark:bg-zinc-800/50"
                            value={to}
                            onChange={(e) => {
                                setTo(e.target.value);
                                applyFilters({ to: e.target.value });
                            }}
                        />
                    </div>
                </div>

                {/* Worker Filter */}
                <div className="min-w-[200px] flex-1 space-y-1.5">
                    <label className="ml-1 text-xs font-medium text-zinc-500">
                        Penjahit (Worker)
                    </label>
                    <div className="relative">
                        <AsyncCombobox
                            endpoint="/produksi/workers/lookup"
                            additionalParams={{ type: 'jahit' }}
                            value={selectedWorker}
                            onChange={(val) => {
                                setSelectedWorker(val);
                                applyFilters({ jahit_id: val?.id || null });
                            }}
                            placeholder="Select worker..."
                            className="h-9"
                        />
                    </div>
                </div>

                {/* Clear Actions */}
                {hasFilters && (
                    <div className="shrink-0 pb-0.5">
                        <Button
                            variant="ghost"
                            size="sm"
                            onClick={handleReset}
                            className="h-9 px-3 text-zinc-500 transition-colors hover:bg-rose-50 hover:text-rose-600 dark:hover:bg-rose-900/10"
                        >
                            <X className="mr-2 h-4 w-4" /> Clear
                        </Button>
                    </div>
                )}
            </div>
        </div>
    );
}
