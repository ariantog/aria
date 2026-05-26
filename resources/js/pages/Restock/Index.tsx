import { Head, Link, router } from '@inertiajs/react';
import {
    Plus,
    Search,
    History,
    Edit,
    ArrowUpDown,
    ShoppingCart,
    FileSpreadsheet,
    CheckSquare
} from 'lucide-react';
import { useState, useMemo } from 'react';
import Pagination from '@/components/Partial/Pagination';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Checkbox } from '@/components/ui/checkbox';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Stuff', href: '#' },
    { title: 'Restock', href: '/restock' },
];

export default function RestockIndex({ restocks, cartCount, restockCacheCount, filters, targetSizes = [] }: any) {
    const [search, setSearch] = useState(filters.code || '');
    const [sizeType, setSizeType] = useState(filters.size_type || 'alpha');
    const [status, setStatus] = useState(filters.status || 'restocked');
    
    // Selection state
    const [selectedRows, setSelectedRows] = useState<Set<string>>(new Set());
    const [editableValues, setEditableValues] = useState<Record<string, Record<string, number>>>({});

    const handleSearch = (e: React.FormEvent) => {
        e.preventDefault();
        router.get('/restock', { code: search, size_type: sizeType, status: status }, { preserveState: true });
    };

    const handleTabChange = (value: string) => {
        setSizeType(value);
        setSelectedRows(new Set());
        router.get('/restock', { code: search, size_type: value, status: status }, { preserveState: true });
    };

    const handleStatusChange = (value: string) => {
        setStatus(value);
        setSelectedRows(new Set());
        router.get('/restock', { code: search, size_type: sizeType, status: value }, { preserveState: true });
    };

    const getSafeSize = (size: string) => str_replace(['.', ' '], '_', size.toLowerCase());
    
    // Helper to replace characters in JS (simplified)
    function str_replace(search: string[], replace: string, subject: string) {
        let res = subject;
        search.forEach(s => {
            res = res.split(s).join(replace);
        });
        return res;
    }

    const getRowId = (row: any) => `${row.group_name}_${row.pcode}_${row.color_name}`;

    const toggleRow = (row: any) => {
        const rowId = getRowId(row);
        const newSelected = new Set(selectedRows);
        
        if (newSelected.has(rowId)) {
            newSelected.delete(rowId);
        } else {
            newSelected.add(rowId);
            
            // Initialize quantities for this row
            const values: Record<string, number> = {};
            if (targetSizes.length > 0) {
                targetSizes.forEach((size: string) => {
                    values[size] = row[`qty_${getSafeSize(size)}`] || 0;
                });
            } else {
                values['default'] = row.total_display_qty || 0;
            }
            setEditableValues(prev => ({ ...prev, [rowId]: values }));
        }
        setSelectedRows(newSelected);
    };

    const toggleAll = () => {
        if (selectedRows.size === restocks.data.length) {
            setSelectedRows(new Set());
        } else {
            const newSelected = new Set<string>();
            const newValues: Record<string, Record<string, number>> = {};
            
            restocks.data.forEach((row: any) => {
                const rowId = getRowId(row);
                newSelected.add(rowId);
                
                const values: Record<string, number> = {};
                if (targetSizes.length > 0) {
                    targetSizes.forEach((size: string) => {
                        values[size] = row[`qty_${getSafeSize(size)}`] || 0;
                    });
                } else {
                    values['default'] = row.total_display_qty || 0;
                }
                newValues[rowId] = values;
            });
            
            setSelectedRows(newSelected);
            setEditableValues(newValues);
        }
    };

    const handleQtyChange = (rowId: string, size: string, value: string) => {
        const numVal = parseInt(value) || 0;
        setEditableValues(prev => ({
            ...prev,
            [rowId]: {
                ...(prev[rowId] || {}),
                [size]: numVal
            }
        }));
    };

    const handleBulkAction = (action: string) => {
        const selectionData = Array.from(selectedRows).map(rowId => {
            const row = restocks.data.find((r: any) => getRowId(r) === rowId);
            return {
                id: rowId,
                group_id: row.group_id,
                color_id: row.color_id,
                pcode: row.pcode,
                values: editableValues[rowId]
            };
        });

        router.post('/restock/bulk-update', {
            status: status,
            action: action,
            selection: selectionData,
            date: new Date().toISOString().split('T')[0]
        }, {
            onSuccess: () => {
                setSelectedRows(new Set());
                setEditableValues({});
            }
        });
    };

    const statusOptions = [
        { label: 'Restocked', value: 'restocked', color: 'bg-blue-600' },
        { label: 'In Production', value: 'production', color: 'bg-orange-500' },
        { label: 'Shipped', value: 'shipped', color: 'bg-emerald-500' },
        { label: 'Missing', value: 'missing', color: 'bg-rose-500' },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Restock Management" />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                {/* Stat Cards - Dashboard Style */}
                <div className="grid auto-rows-min gap-4 md:grid-cols-3">
                    <div className="rounded-xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-800 dark:bg-zinc-900/50">
                        <div className="flex items-center gap-3">
                            <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-blue-100 dark:bg-blue-900/30">
                                <ShoppingCart className="h-5 w-5 text-blue-600 dark:text-blue-400" />
                            </div>
                            <div>
                                <p className="text-sm font-medium text-zinc-500 dark:text-zinc-400">Total Groups</p>
                                <p className="text-2xl font-bold text-zinc-900 dark:text-zinc-50">{restocks.total}</p>
                            </div>
                        </div>
                    </div>
                    <div className="rounded-xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-800 dark:bg-zinc-900/50">
                        <div className="flex items-center gap-3">
                            <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-orange-100 dark:bg-orange-900/30">
                                <History className="h-5 w-5 text-orange-600 dark:text-orange-400" />
                            </div>
                            <div>
                                <p className="text-sm font-medium text-zinc-500 dark:text-zinc-400">Total In Production</p>
                                <p className="text-2xl font-bold text-zinc-900 dark:text-zinc-50">
                                    {restocks.data.reduce((acc: number, item: any) => acc + Number(item.total_prod), 0)}
                                </p>
                            </div>
                        </div>
                    </div>
                    <div className="rounded-xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-800 dark:bg-zinc-900/50">
                        <div className="flex items-center justify-between">
                            <div className="flex items-center gap-3">
                                <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-emerald-100 dark:bg-emerald-900/30">
                                    <ShoppingCart className="h-5 w-5 text-emerald-600 dark:text-emerald-400" />
                                </div>
                                <div>
                                    <p className="text-sm font-medium text-zinc-500 dark:text-zinc-400">Received Cart</p>
                                    <p className="text-2xl font-bold text-zinc-900 dark:text-zinc-50">{cartCount}</p>
                                </div>
                            </div>
                            <Link href="/restock/received">
                                <Button size="sm" variant="outline">View Cart</Button>
                            </Link>
                        </div>
                    </div>
                </div>

                {/* Main Content Area */}
                <div className="flex flex-1 flex-col gap-6 rounded-xl border border-zinc-200 bg-white p-6 dark:border-zinc-800 dark:bg-zinc-900/50">
                    
                    {/* Header Row */}
                    <div className="flex items-center justify-between">
                        <div>
                            <h2 className="text-xl font-bold tracking-tight text-zinc-900 dark:text-zinc-50">
                                Restock Registry
                            </h2>
                            <p className="text-sm text-zinc-500 dark:text-zinc-400">
                                Track items from restock to warehouse arrival.
                            </p>
                        </div>
                        <div className="flex items-center gap-3">
                            {selectedRows.size > 0 && (
                                <div className="flex items-center gap-2">
                                    {status === 'restocked' && (
                                        <>
                                            <Button size="sm" className="bg-blue-600 text-white hover:bg-blue-700" onClick={() => handleBulkAction('add_stock')}>
                                                Add Stock
                                            </Button>
                                            <Button size="sm" className="bg-orange-600 text-white hover:bg-orange-700" onClick={() => handleBulkAction('to_production')}>
                                                To Production
                                            </Button>
                                        </>
                                    )}
                                    {status === 'production' && (
                                        <Button size="sm" className="bg-emerald-600 text-white hover:bg-emerald-700" onClick={() => handleBulkAction('to_shipped')}>
                                            To Shipped
                                        </Button>
                                    )}
                                    {status === 'shipped' && (
                                        <>
                                            <Button size="sm" className="bg-blue-600 text-white hover:bg-blue-700" onClick={() => handleBulkAction('to_arrived')}>
                                                Arrived
                                            </Button>
                                            <Button size="sm" variant="destructive" onClick={() => handleBulkAction('to_missing')}>
                                                To Missing
                                            </Button>
                                        </>
                                    )}
                                    {status !== 'shipped' && status !== 'missing' && (
                                        <Button size="sm" variant="destructive" onClick={() => handleBulkAction('to_missing')}>
                                            To Missing
                                        </Button>
                                    )}
                                </div>
                            )}
                            <Link href="/restock/create">
                                <Button size="sm" className="bg-blue-600 text-white hover:bg-blue-700 relative">
                                    <Plus className="mr-2 h-4 w-4" /> New Restock
                                    {restockCacheCount > 0 && (
                                        <Badge className="ml-2 bg-white text-blue-600 hover:bg-zinc-100">
                                            {restockCacheCount}
                                        </Badge>
                                    )}
                                </Button>
                            </Link>
                            <Link href="/restock/upload">
                                <Button size="sm" variant="secondary">
                                    <FileSpreadsheet className="mr-2 h-4 w-4" /> Import
                                </Button>
                            </Link>
                        </div>
                    </div>

                    {/* Status Tabs Row */}
                    <div className="border-b border-zinc-200 dark:border-zinc-800">
                        <Tabs value={status} onValueChange={handleStatusChange}>
                            <TabsList>
                                {statusOptions.map((opt) => (
                                    <TabsTrigger key={opt.value} value={opt.value}>
                                        {opt.label}
                                    </TabsTrigger>
                                ))}
                            </TabsList>
                        </Tabs>
                    </div>

                    {/* Secondary Row: Search & Size Tabs */}
                    <div className="flex flex-col items-center justify-between gap-4 md:flex-row">
                        <Tabs value={sizeType} onValueChange={handleTabChange}>
                            <TabsList>
                                <TabsTrigger value="alpha">Alpha Size</TabsTrigger>
                                <TabsTrigger value="volume">Volume Size</TabsTrigger>
                                <TabsTrigger value="all">All Size</TabsTrigger>
                            </TabsList>
                        </Tabs>
                        
                        <form onSubmit={handleSearch} className="relative w-full md:w-64">
                            <Search className="absolute left-2.5 top-2.5 h-4 w-4 text-zinc-500" />
                            <Input
                                placeholder="Search groups/sku..."
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                                className="h-9 bg-zinc-50 pl-9 dark:bg-zinc-900"
                            />
                        </form>
                    </div>

                    <div className="overflow-hidden rounded-lg border border-zinc-200 dark:border-zinc-800">
                        <div className="overflow-x-auto">
                            <table className="w-full text-left text-sm">
                                <thead className="bg-zinc-50/50 text-xs text-zinc-500 uppercase dark:bg-zinc-900/50">
                                    <tr>
                                        <th className="px-4 py-3 w-10">
                                            <Checkbox 
                                                checked={restocks.data.length > 0 && selectedRows.size === restocks.data.length}
                                                onCheckedChange={toggleAll}
                                            />
                                        </th>
                                        <th className="px-6 py-3 font-bold tracking-wider">Group</th>
                                        <th className="px-6 py-3 font-bold tracking-wider">SKU/Code</th>
                                        <th className="px-6 py-3 font-bold tracking-wider">Color</th>
                                        {targetSizes.length === 0 && (
                                            <th className="px-6 py-3 font-bold tracking-wider">Size</th>
                                        )}
                                        {targetSizes.map((size: string) => (
                                            <th key={size} className="px-4 py-3 font-bold tracking-wider text-center">{size}</th>
                                        ))}
                                        <th className="px-6 py-3 font-bold tracking-wider text-center bg-zinc-100/50 dark:bg-zinc-800/50">Total</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-zinc-200 dark:divide-zinc-800/50">
                                    {restocks.data.length > 0 ? (
                                        restocks.data.map((row: any, index: number) => {
                                            const rowId = getRowId(row);
                                            const isSelected = selectedRows.has(rowId);
                                            
                                            return (
                                                <tr key={index} className={`hover:bg-zinc-50 dark:hover:bg-zinc-800/40 transition-colors ${isSelected ? 'bg-blue-50/50 dark:bg-blue-900/10' : ''}`}>
                                                    <td className="px-4 py-4">
                                                        <Checkbox 
                                                            checked={isSelected}
                                                            onCheckedChange={() => toggleRow(row)}
                                                        />
                                                    </td>
                                                    <td className="px-6 py-4 font-medium text-zinc-900 dark:text-zinc-100">
                                                        {row.group_name || '-'}
                                                    </td>
                                                    <td className="px-6 py-4 text-zinc-600 dark:text-zinc-400">
                                                        {row.pcode || '-'}
                                                    </td>
                                                    <td className="px-6 py-4">
                                                        {row.color_name ? (
                                                            <Badge variant="outline" className="border-zinc-300 dark:border-zinc-700">
                                                                {row.color_name}
                                                            </Badge>
                                                        ) : '-'}
                                                    </td>
                                                    {targetSizes.length === 0 && (
                                                        <td className="px-6 py-4 text-zinc-600 dark:text-zinc-400">
                                                            {row.size_name || '-'}
                                                        </td>
                                                    )}
                                                    
                                                    {/* Qty Columns */}
                                                    {targetSizes.length === 0 ? (
                                                        <td className="px-4 py-4 text-center">
                                                            {isSelected ? (
                                                                <Input 
                                                                    type="number"
                                                                    className="h-8 w-20 mx-auto text-center"
                                                                    value={editableValues[rowId]?.['default'] ?? 0}
                                                                    onChange={(e) => handleQtyChange(rowId, 'default', e.target.value)}
                                                                />
                                                            ) : (
                                                                row.total_display_qty > 0 ? (
                                                                    <span className="font-mono font-semibold text-blue-600 dark:text-blue-400">{row.total_display_qty}</span>
                                                                ) : (
                                                                    <span className="text-zinc-300 dark:text-zinc-700">0</span>
                                                                )
                                                            )}
                                                        </td>
                                                    ) : (
                                                        targetSizes.map((size: string) => {
                                                            const safeSize = getSafeSize(size);
                                                            const originalVal = row[`qty_${safeSize}`];
                                                            
                                                            return (
                                                                <td key={size} className="px-4 py-4 text-center">
                                                                    {isSelected ? (
                                                                        <Input 
                                                                            type="number"
                                                                            className="h-8 w-16 mx-auto text-center"
                                                                            value={editableValues[rowId]?.[size] ?? 0}
                                                                            onChange={(e) => handleQtyChange(rowId, size, e.target.value)}
                                                                            max={originalVal}
                                                                        />
                                                                    ) : (
                                                                        originalVal > 0 ? (
                                                                            <span className="font-mono font-semibold text-blue-600 dark:text-blue-400">{originalVal}</span>
                                                                        ) : (
                                                                            <span className="text-zinc-300 dark:text-zinc-700">0</span>
                                                                        )
                                                                    )}
                                                                </td>
                                                            );
                                                        })
                                                    )}

                                                    <td className="px-6 py-4 text-center bg-zinc-50/30 dark:bg-zinc-900/20">
                                                        <Badge className="bg-zinc-900 text-white dark:bg-zinc-100 dark:text-zinc-900 font-mono">
                                                            {row.total_display_qty}
                                                        </Badge>
                                                    </td>
                                                </tr>
                                            );
                                        })
                                    ) : (
                                        <tr>
                                            <td colSpan={targetSizes.length + (targetSizes.length === 0 ? 6 : 5)} className="px-6 py-12 text-center text-zinc-500">
                                                No aggregated restock records found for this category.
                                            </td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </div>
                        {restocks.total > 0 && (
                            <div className="border-t border-zinc-200 bg-zinc-50/30 px-6 py-4 dark:border-zinc-800 dark:bg-zinc-900/30">
                                <Pagination 
                                    links={restocks.links} 
                                    from={restocks.from} 
                                    to={restocks.to} 
                                    total={restocks.total} 
                                />
                            </div>
                        )}
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}

