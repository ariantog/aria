import { useState, useEffect } from 'react';
import { router } from '@inertiajs/react';
import { Input } from '@/components/ui/input';
import { Search, X, Filter } from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import { Separator } from '@/components/ui/separator';
import { AsyncCombobox } from '@/components/AsyncCombobox';

interface Option {
    value: string;
    label: string;
}

interface Props {
    baseUrl: string;
    filters: {
        search?: string;
        jahit?: string | number;
        size?: string | number;
        warna?: string | number;
        item_type?: string | number;
        code?: string;
        name?: string;
        alias?: string;
        desc?: string;
        [key: string]: any;
    };
    tags: Record<number, any[]>;
    isAsset?: boolean;
}

export default function FilterItem({ baseUrl, filters, tags, isAsset = false }: Props) {
    const [code, setCode] = useState(filters.code || '');
    const [name, setName] = useState(filters.name || '');
    const [alias, setAlias] = useState(filters.alias || '');
    const [desc, setDesc] = useState(filters.desc || '');

    // Tag States (Objects for AsyncCombobox)
    const [selectedJahit, setSelectedJahit] = useState<any>(null);
    const [selectedType, setSelectedType] = useState<any>(null);
    const [selectedColor, setSelectedColor] = useState<any>(null);
    const [selectedSize, setSelectedSize] = useState<any>(null);

    // Initial load of tag objects from IDs in filters
    useEffect(() => {
        const findTag = (id: any) => {
            if (!id) return null;
            for (const group in tags) {
                const found = tags[group].find(t => t.id.toString() === id.toString());
                if (found) return found;
            }
            return null;
        };

        if (filters.jahit) setSelectedJahit(findTag(filters.jahit));
        if (filters.item_type) setSelectedType(findTag(filters.item_type));
        if (filters.warna) setSelectedColor(findTag(filters.warna));
        if (filters.size) setSelectedSize(findTag(filters.size));
    }, []);

    const applyFilters = (newFilters?: any) => {
        const params: any = {
            code,
            name,
            alias,
            desc,
            jahit: selectedJahit?.id || null,
            item_type: selectedType?.id || null,
            warna: selectedColor?.id || null,
            size: selectedSize?.id || null,
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

    // Auto-apply logic for text inputs (Debounced)
    useEffect(() => {
        const timer = setTimeout(() => {
            if (code !== (filters.code || '')) {
                applyFilters({ code });
            }
        }, 500);
        return () => clearTimeout(timer);
    }, [code]);

    useEffect(() => {
        const timer = setTimeout(() => {
            if (name !== (filters.name || '')) {
                applyFilters({ name });
            }
        }, 500);
        return () => clearTimeout(timer);
    }, [name]);

    useEffect(() => {
        const timer = setTimeout(() => {
            if (alias !== (filters.alias || '')) {
                applyFilters({ alias });
            }
        }, 500);
        return () => clearTimeout(timer);
    }, [alias]);

    useEffect(() => {
        const timer = setTimeout(() => {
            if (desc !== (filters.desc || '')) {
                applyFilters({ desc });
            }
        }, 500);
        return () => clearTimeout(timer);
    }, [desc]);

    const handleReset = () => {
        setCode('');
        setName('');
        setAlias('');
        setDesc('');
        setSelectedJahit(null);
        setSelectedType(null);
        setSelectedColor(null);
        setSelectedSize(null);
        router.get(baseUrl, {}, { preserveState: true, replace: true });
    };

    const hasFilters = code || name || alias || desc ||
        selectedJahit || selectedType || selectedColor || selectedSize;

    return (
        <div className="bg-white dark:bg-zinc-900 p-3 rounded-xl border shadow-sm mb-6">
            <div className="flex flex-wrap items-end gap-3">
                {/* Barcode / SKU */}
                <div className="flex-1 min-w-[180px] space-y-1.5">
                    <label className="text-xs font-medium text-zinc-500 ml-1">Barcode / SKU</label>
                    <div className="relative">
                        <Search className="absolute left-3 top-2.5 h-4 w-4 text-zinc-400" />
                        <Input
                            placeholder="Barcode / SKU"
                            className="pl-9 h-9 bg-zinc-50 dark:bg-zinc-800/50 border-zinc-200 dark:border-zinc-700 font-medium"
                            value={code}
                            onChange={(e) => setCode(e.target.value)}
                        />
                    </div>
                </div>

                {/* Long SKU */}
                <div className="flex-1 min-w-[180px] space-y-1.5">
                    <label className="text-xs font-medium text-zinc-500 ml-1">Long SKU</label>
                    <Input
                        placeholder="Long SKU name..."
                        className="h-9 bg-zinc-50 dark:bg-zinc-800/50 border-zinc-200 dark:border-zinc-700"
                        value={name}
                        onChange={(e) => setName(e.target.value)}
                    />
                </div>

                {/* Alias */}
                <div className="flex-1 min-w-[180px] space-y-1.5">
                    <label className="text-xs font-medium text-zinc-500 ml-1">Alias</label>
                    <Input
                        placeholder="Alias name..."
                        className="h-9 bg-zinc-50 dark:bg-zinc-800/50 border-zinc-200 dark:border-zinc-700"
                        value={alias}
                        onChange={(e) => setAlias(e.target.value)}
                    />
                </div>

                {/* Description */}
                <div className="flex-1 min-w-[150px] space-y-1.5">
                    <label className="text-xs font-medium text-zinc-500 ml-1">Description</label>
                    <Input
                        placeholder="Description..."
                        className="h-9 bg-zinc-50 dark:bg-zinc-800/50 border-zinc-200 dark:border-zinc-700"
                        value={desc}
                        onChange={(e) => setDesc(e.target.value)}
                    />
                </div>

                {/* More Filters Dropdown */}
                {!isAsset && (
                    <div className="shrink-0">
                        <DropdownMenu>
                            <DropdownMenuTrigger asChild>
                                <Button variant="outline" size="sm" className="h-9 border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800/50 font-medium">
                                    <Filter className="mr-2 h-4 w-4" /> More Filters
                                </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end" className="w-[500px] p-4 shadow-2xl">
                                <DropdownMenuLabel className="px-0">Advanced Filters</DropdownMenuLabel>
                                <DropdownMenuSeparator className="my-2" />
                                <div className="grid grid-cols-2 gap-4">
                                    <div className="space-y-1.5">
                                        <label className="text-xs font-medium text-zinc-500 ml-1">Jahit</label>
                                        <AsyncCombobox
                                            endpoint="/tags/lookup"
                                            additionalParams={{ type: 2 }}
                                            value={selectedJahit}
                                            onChange={(val) => {
                                                setSelectedJahit(val);
                                                applyFilters({ jahit: val?.id || null });
                                            }}
                                            placeholder="Search jahit..."
                                            className="h-9 rounded-md"
                                        />
                                    </div>
                                    <div className="space-y-1.5">
                                        <label className="text-xs font-medium text-zinc-500 ml-1">Type (Genre)</label>
                                        <AsyncCombobox
                                            endpoint="/tags/lookup"
                                            additionalParams={{ type: 3 }}
                                            value={selectedType}
                                            onChange={(val) => {
                                                setSelectedType(val);
                                                applyFilters({ item_type: val?.id || null });
                                            }}
                                            placeholder="Search type..."
                                            className="h-9 rounded-md"
                                        />
                                    </div>
                                    <div className="space-y-1.5">
                                        <label className="text-xs font-medium text-zinc-500 ml-1">Color</label>
                                        <AsyncCombobox
                                            endpoint="/tags/lookup"
                                            additionalParams={{ type: 20 }}
                                            value={selectedColor}
                                            onChange={(val) => {
                                                setSelectedColor(val);
                                                applyFilters({ warna: val?.id || null });
                                            }}
                                            placeholder="Search color..."
                                            className="h-9 rounded-md"
                                        />
                                    </div>
                                    <div className="space-y-1.5">
                                        <label className="text-xs font-medium text-zinc-500 ml-1">Size</label>
                                        <AsyncCombobox
                                            endpoint="/tags/lookup"
                                            additionalParams={{ type: 7 }}
                                            value={selectedSize}
                                            onChange={(val) => {
                                                setSelectedSize(val);
                                                applyFilters({ size: val?.id || null });
                                            }}
                                            placeholder="Search size..."
                                            className="h-9 rounded-md"
                                        />
                                    </div>
                                </div>
                            </DropdownMenuContent>
                        </DropdownMenu>
                    </div>
                )}

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
