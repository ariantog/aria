import { Head, router, useForm, Link } from '@inertiajs/react';
import {
    Search,
    Plus,
    Trash2,
    ArrowUpDown,
    ArrowUp,
    ArrowDown,
    Package,
    Warehouse,
    Filter,
    X,
} from 'lucide-react';
import { useState, useEffect } from 'react';
import Pagination from '@/components/Partial/Pagination';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
    DialogFooter,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';

interface WarehouseCompare {
    id: number;
    warehouse_id: number;
    warehouse: {
        id: number;
        name: string;
    };
}

interface Item {
    id: number;
    name: string;
    code: string;
    [key: string]: any;
}

interface PaginatedItems {
    data: Item[];
    links: any[];
    meta: {
        current_page: number;
        from: number;
        last_page: number;
        path: string;
        per_page: number;
        to: number;
        total: number;
    };
}

interface Props {
    items: PaginatedItems;
    selectedWarehouses: WarehouseCompare[];
    allWarehouses: { id: number; name: string }[];
    filters: {
        search?: string;
        sort?: string;
        direction?: 'asc' | 'desc';
    };
}

export default function Compare({
    items,
    selectedWarehouses,
    allWarehouses,
    filters,
}: Props) {
    const [search, setSearch] = useState(filters.search || '');
    const [isAddingWarehouse, setIsAddingWarehouse] = useState(false);

    const { data, setData, post, processing, reset, errors } = useForm({
        warehouse_id: '',
    });

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Reports', href: '#' },
        { title: 'Compare Warehouse', href: '/reports/compare' },
    ];

    const handleFilter = (e?: React.FormEvent) => {
        e?.preventDefault();
        router.get(
            '/reports/compare',
            {
                search,
                sort: filters.sort,
                direction: filters.direction,
            },
            { preserveState: true },
        );
    };

    const handleSort = (column: string) => {
        const direction =
            filters.sort === column && filters.direction === 'asc'
                ? 'desc'
                : 'asc';
        router.get(
            '/reports/compare',
            { search, sort: column, direction },
            { preserveState: true },
        );
    };

    const handleAddWarehouse = (e: React.FormEvent) => {
        e.preventDefault();
        post('/reports/compare', {
            onSuccess: () => {
                setIsAddingWarehouse(false);
                reset();
            },
        });
    };

    const handleRemoveWarehouse = (id: number) => {
        if (
            confirm(
                'Are you sure you want to remove this warehouse from comparison?',
            )
        ) {
            router.delete(`/reports/compare/${id}`);
        }
    };

    const handleClear = () => {
        setSearch('');
        router.get('/reports/compare');
    };

    const SortIcon = ({ column }: { column: string }) => {
        if (filters.sort !== column)
            return <ArrowUpDown className="ml-2 h-4 w-4 text-zinc-400" />;
        return filters.direction === 'asc' ? (
            <ArrowUp className="ml-2 h-4 w-4 text-zinc-900 dark:text-zinc-100" />
        ) : (
            <ArrowDown className="ml-2 h-4 w-4 text-zinc-900 dark:text-zinc-100" />
        );
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Compare Warehouse" />

            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div className="flex flex-col justify-between gap-4 md:flex-row md:items-center">
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight">
                            Compare Warehouse
                        </h1>
                        <p className="text-zinc-500 dark:text-zinc-400">
                            Compare item quantities across multiple warehouses.
                        </p>
                    </div>

                    <Dialog
                        open={isAddingWarehouse}
                        onOpenChange={setIsAddingWarehouse}
                    >
                        <DialogTrigger asChild>
                            <Button>
                                <Plus className="mr-2 h-4 w-4" />
                                Add Warehouse
                            </Button>
                        </DialogTrigger>
                        <DialogContent>
                            <DialogHeader>
                                <DialogTitle>
                                    Add Warehouse to Compare
                                </DialogTitle>
                            </DialogHeader>
                            <form
                                onSubmit={handleAddWarehouse}
                                className="space-y-4 py-4"
                            >
                                <div className="space-y-2">
                                    <Label htmlFor="warehouse">
                                        Select Warehouse
                                    </Label>
                                    <Select
                                        value={data.warehouse_id}
                                        onValueChange={(val) =>
                                            setData('warehouse_id', val)
                                        }
                                    >
                                        <SelectTrigger>
                                            <SelectValue placeholder="Choose a warehouse..." />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {allWarehouses
                                                .filter(
                                                    (wh) =>
                                                        !selectedWarehouses.some(
                                                            (sw) =>
                                                                sw.warehouse_id ===
                                                                wh.id,
                                                        ),
                                                )
                                                .map((wh) => (
                                                    <SelectItem
                                                        key={wh.id}
                                                        value={wh.id.toString()}
                                                    >
                                                        {wh.name}
                                                    </SelectItem>
                                                ))}
                                        </SelectContent>
                                    </Select>
                                    {errors.warehouse_id && (
                                        <p className="text-sm text-rose-500">
                                            {errors.warehouse_id}
                                        </p>
                                    )}
                                </div>
                                <DialogFooter>
                                    <Button type="submit" disabled={processing}>
                                        {processing
                                            ? 'Adding...'
                                            : 'Add to Comparison'}
                                    </Button>
                                </DialogFooter>
                            </form>
                        </DialogContent>
                    </Dialog>
                </div>

                <Card>
                    <CardHeader className="p-4 pb-4 sm:p-6 sm:pb-4">
                        <form
                            onSubmit={handleFilter}
                            className="flex flex-wrap items-end gap-4"
                        >
                            <div className="grid min-w-[200px] flex-1 gap-1.5">
                                <Label htmlFor="search">Search Product</Label>
                                <div className="relative">
                                    <Search className="absolute top-2.5 left-2.5 h-4 w-4 text-zinc-500" />
                                    <Input
                                        id="search"
                                        placeholder="Search by name or SKU..."
                                        className="pl-9"
                                        value={search}
                                        onChange={(e) =>
                                            setSearch(e.target.value)
                                        }
                                    />
                                </div>
                            </div>
                            <div className="flex gap-2">
                                <Button type="submit">
                                    <Filter className="mr-2 h-4 w-4" />
                                    Filter
                                </Button>
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={handleClear}
                                >
                                    <X className="mr-2 h-4 w-4" />
                                    Clear
                                </Button>
                            </div>
                        </form>
                    </CardHeader>
                    <CardContent className="p-0 sm:p-0">
                        <div className="overflow-x-auto">
                            <Table>
                                <TableHeader className="bg-zinc-50 dark:bg-zinc-900/50">
                                    <TableRow>
                                        <TableHead
                                            className="min-w-[250px] cursor-pointer transition-colors hover:bg-zinc-100 dark:hover:bg-zinc-800"
                                            onClick={() => handleSort('name')}
                                        >
                                            <div className="flex items-center">
                                                Product{' '}
                                                <SortIcon column="name" />
                                            </div>
                                        </TableHead>
                                        <TableHead
                                            className="cursor-pointer transition-colors hover:bg-zinc-100 dark:hover:bg-zinc-800"
                                            onClick={() => handleSort('code')}
                                        >
                                            <div className="flex items-center">
                                                SKU <SortIcon column="code" />
                                            </div>
                                        </TableHead>
                                        {selectedWarehouses.map((sw) => (
                                            <TableHead
                                                key={sw.id}
                                                className="group relative min-w-[150px] cursor-pointer text-right transition-colors hover:bg-zinc-100 dark:hover:bg-zinc-800"
                                                onClick={() =>
                                                    handleSort(
                                                        `wh_${sw.warehouse_id}`,
                                                    )
                                                }
                                            >
                                                <div className="flex items-center justify-end">
                                                    <span
                                                        className="max-w-[120px] truncate"
                                                        title={
                                                            sw.warehouse.name
                                                        }
                                                    >
                                                        {sw.warehouse.name}
                                                    </span>
                                                    <SortIcon
                                                        column={`wh_${sw.warehouse_id}`}
                                                    />
                                                    <Button
                                                        variant="ghost"
                                                        size="icon"
                                                        className="ml-1 h-6 w-6 text-rose-500 opacity-0 transition-opacity group-hover:opacity-100 hover:bg-rose-50 hover:text-rose-700 dark:hover:bg-rose-950/30"
                                                        onClick={(e) => {
                                                            e.stopPropagation();
                                                            handleRemoveWarehouse(
                                                                sw.id,
                                                            );
                                                        }}
                                                    >
                                                        <Trash2 className="h-3 w-3" />
                                                    </Button>
                                                </div>
                                            </TableHead>
                                        ))}
                                        {selectedWarehouses.length === 0 && (
                                            <TableHead className="text-muted-foreground italic">
                                                Add warehouses to compare
                                                quantities
                                            </TableHead>
                                        )}
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {items.data.length === 0 ? (
                                        <TableRow>
                                            <TableCell
                                                colSpan={
                                                    selectedWarehouses.length +
                                                    2
                                                }
                                                className="h-48 text-center text-muted-foreground"
                                            >
                                                <div className="flex flex-col items-center gap-2">
                                                    <Package className="h-8 w-8 text-zinc-300" />
                                                    <p>No products found.</p>
                                                </div>
                                            </TableCell>
                                        </TableRow>
                                    ) : (
                                        items.data.map((item) => (
                                            <TableRow
                                                key={item.id}
                                                className="transition-colors hover:bg-zinc-50/50 dark:hover:bg-zinc-900/50"
                                            >
                                                <TableCell className="font-medium">
                                                    <Link
                                                        href={`/items/${item.id}`}
                                                        className="text-blue-600 hover:underline dark:text-blue-400"
                                                    >
                                                        {item.name}
                                                    </Link>
                                                </TableCell>
                                                <TableCell className="font-mono text-xs">
                                                    {item.code}
                                                </TableCell>
                                                {selectedWarehouses.map(
                                                    (sw) => {
                                                        const qty = Number(
                                                            item[
                                                                `wh_${sw.warehouse_id}`
                                                            ] || 0,
                                                        );
                                                        return (
                                                            <TableCell
                                                                key={sw.id}
                                                                className="text-right tabular-nums"
                                                            >
                                                                <span
                                                                    className={
                                                                        qty > 0
                                                                            ? 'font-bold text-zinc-900 dark:text-zinc-100'
                                                                            : 'text-zinc-400'
                                                                    }
                                                                >
                                                                    {qty.toLocaleString()}
                                                                </span>
                                                            </TableCell>
                                                        );
                                                    },
                                                )}
                                                {selectedWarehouses.length ===
                                                    0 && <TableCell />}
                                            </TableRow>
                                        ))
                                    )}
                                </TableBody>
                            </Table>
                        </div>

                        <div className="border-t bg-zinc-50/50 p-4 dark:bg-zinc-900/50">
                            <Pagination
                                links={
                                    items.links || (items as any).meta?.links
                                }
                                from={
                                    (items as any).from ||
                                    (items as any).meta?.from
                                }
                                to={
                                    (items as any).to || (items as any).meta?.to
                                }
                                total={
                                    (items as any).total ||
                                    (items as any).meta?.total
                                }
                                label="products"
                            />
                        </div>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
