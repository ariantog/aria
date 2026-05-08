import { Head, router, Link } from '@inertiajs/react';
import { format, differenceInDays } from 'date-fns';
import {
    Search,
    Filter,
    X,
    Zap,
    Snowflake,
    AlertCircle,
    ArrowRightLeft,
} from 'lucide-react';
import { useState } from 'react';
import Pagination from '@/components/Partial/Pagination';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { BreadcrumbItem } from '@/types';

interface ItemHealth {
    id: number;
    name: string;
    code: string;
    sold_30: number;
    sold_90: number;
    last_sold_at: string | null;
    current_stock: number;
}

interface Props {
    items: {
        data: ItemHealth[];
        links: any[];
        meta: any;
    };
    warehouses: { id: number; name: string }[];
    filters: {
        warehouse_id?: string;
        search?: string;
    };
}

export default function InventoryHealth({ items, warehouses, filters }: Props) {
    const [search, setSearch] = useState(filters.search || '');
    const [warehouseId, setWarehouseId] = useState(filters.warehouse_id || '0');

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Reports', href: '#' },
        { title: 'Inventory Health', href: '/reports/inventory-health' },
    ];

    const handleFilter = (e: React.FormEvent) => {
        e.preventDefault();
        router.get(
            '/reports/inventory-health',
            {
                search,
                warehouse_id: warehouseId === '0' ? undefined : warehouseId,
            },
            { preserveState: true },
        );
    };

    const handleClear = () => {
        setSearch('');
        setWarehouseId('0');
        router.get('/reports/inventory-health');
    };

    const getStatus = (item: ItemHealth) => {
        const sold30 = Math.abs(Number(item.sold_30 || 0));
        const sold90 = Math.abs(Number(item.sold_90 || 0));
        const stock = Number(item.current_stock || 0);
        const daysSinceLastSale = item.last_sold_at
            ? differenceInDays(new Date(), new Date(item.last_sold_at))
            : 999;

        if (sold30 > 10 && stock < sold30 * 0.2) {
            return {
                label: 'Fast Moving / Low Stock',
                color: 'bg-amber-500',
                icon: Zap,
                recommendation: 'Restock immediately',
            };
        }
        if (sold30 > 0) {
            return {
                label: 'Healthy',
                color: 'bg-emerald-500',
                icon: Zap,
                recommendation: 'Maintain levels',
            };
        }
        if (sold90 === 0 && stock > 0) {
            return {
                label: 'Dead Stock',
                color: 'bg-rose-500',
                icon: Snowflake,
                recommendation: 'Move to active warehouse or clearance',
            };
        }
        if (daysSinceLastSale > 30 && stock > 0) {
            return {
                label: 'Slow Moving',
                color: 'bg-zinc-500',
                icon: AlertCircle,
                recommendation: 'Monitor & reduce stock',
            };
        }

        return {
            label: 'Inactive',
            color: 'bg-zinc-300',
            icon: AlertCircle,
            recommendation: 'N/A',
        };
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Inventory Health" />

            <div className="flex flex-col gap-6 p-4 sm:p-6 lg:p-8">
                <div className="flex flex-col justify-between gap-4 md:flex-row md:items-center">
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight">
                            Inventory Health & Intelligence
                        </h1>
                        <p className="text-zinc-500 dark:text-zinc-400">
                            Identify Fast Moving items and Dead Stock to
                            optimize your inventory.
                        </p>
                    </div>
                </div>

                <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
                    <Card className="border-l-4 border-l-emerald-500">
                        <CardHeader className="pb-2">
                            <CardTitle className="flex items-center text-sm font-medium text-emerald-600">
                                <Zap className="mr-2 h-4 w-4" /> Healthy / Fast
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="text-xs text-zinc-500">
                                Items sold in the last 30 days. High turnover.
                            </div>
                        </CardContent>
                    </Card>
                    <Card className="border-l-4 border-l-amber-500">
                        <CardHeader className="pb-2">
                            <CardTitle className="flex items-center text-sm font-medium text-amber-600">
                                <AlertCircle className="mr-2 h-4 w-4" /> Slow
                                Moving
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="text-xs text-zinc-500">
                                No sales in 30 days. Consider re-evaluating
                                stock levels.
                            </div>
                        </CardContent>
                    </Card>
                    <Card className="border-l-4 border-l-rose-500">
                        <CardHeader className="pb-2">
                            <CardTitle className="flex items-center text-sm font-medium text-rose-600">
                                <Snowflake className="mr-2 h-4 w-4" /> Dead
                                Stock
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="text-xs text-zinc-500">
                                No sales in 90 days. Recommended for
                                rebalancing.
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <Card>
                    <CardHeader className="p-4">
                        <form
                            onSubmit={handleFilter}
                            className="flex flex-wrap items-end gap-4"
                        >
                            <div className="grid w-[200px] gap-1.5">
                                <Label htmlFor="warehouse">Warehouse</Label>
                                <Select
                                    value={warehouseId}
                                    onValueChange={setWarehouseId}
                                >
                                    <SelectTrigger id="warehouse">
                                        <SelectValue placeholder="All Warehouses" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="0">
                                            All Warehouses
                                        </SelectItem>
                                        {warehouses.map((wh) => (
                                            <SelectItem
                                                key={wh.id}
                                                value={wh.id.toString()}
                                            >
                                                {wh.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                            <div className="grid min-w-[200px] flex-1 gap-1.5">
                                <Label htmlFor="search">Search Product</Label>
                                <div className="relative">
                                    <Search className="absolute top-2.5 left-2.5 h-4 w-4 text-zinc-500" />
                                    <Input
                                        id="search"
                                        placeholder="Name or SKU..."
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
                                    <Filter className="mr-2 h-4 w-4" /> Filter
                                </Button>
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={handleClear}
                                >
                                    <X className="mr-2 h-4 w-4" /> Clear
                                </Button>
                            </div>
                        </form>
                    </CardHeader>
                    <CardContent className="p-0">
                        <div className="overflow-x-auto">
                            <Table>
                                <TableHeader className="bg-zinc-50 dark:bg-zinc-900/50">
                                    <TableRow>
                                        <TableHead>Product</TableHead>
                                        <TableHead className="text-right">
                                            Sold (30d)
                                        </TableHead>
                                        <TableHead className="text-right">
                                            Sold (90d)
                                        </TableHead>
                                        <TableHead className="text-right">
                                            Current Stock
                                        </TableHead>
                                        <TableHead>Last Sold</TableHead>
                                        <TableHead>Status</TableHead>
                                        <TableHead>Recommendation</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {items.data.length === 0 ? (
                                        <TableRow>
                                            <TableCell
                                                colSpan={7}
                                                className="h-32 text-center text-zinc-500"
                                            >
                                                No items found.
                                            </TableCell>
                                        </TableRow>
                                    ) : (
                                        items.data.map((item) => {
                                            const status = getStatus(item);
                                            const StatusIcon = status.icon;
                                            return (
                                                <TableRow
                                                    key={item.id}
                                                    className="transition-colors hover:bg-zinc-50/50 dark:hover:bg-zinc-900/50"
                                                >
                                                    <TableCell>
                                                        <div className="flex flex-col">
                                                            <Link
                                                                href={`/items/${item.id}`}
                                                                className="font-bold text-blue-600 hover:underline dark:text-blue-400"
                                                            >
                                                                {item.name}
                                                            </Link>
                                                            <span className="font-mono text-xs text-zinc-500">
                                                                {item.code}
                                                            </span>
                                                        </div>
                                                    </TableCell>
                                                    <TableCell className="text-right font-medium">
                                                        {Number(
                                                            item.sold_30 || 0,
                                                        ).toLocaleString()}
                                                    </TableCell>
                                                    <TableCell className="text-right font-medium">
                                                        {Number(
                                                            item.sold_90 || 0,
                                                        ).toLocaleString()}
                                                    </TableCell>
                                                    <TableCell className="text-right font-bold tabular-nums">
                                                        {Number(
                                                            item.current_stock ||
                                                                0,
                                                        ).toLocaleString()}
                                                    </TableCell>
                                                    <TableCell className="text-sm">
                                                        {item.last_sold_at
                                                            ? format(
                                                                  new Date(
                                                                      item.last_sold_at,
                                                                  ),
                                                                  'dd MMM yyyy',
                                                              )
                                                            : '-'}
                                                    </TableCell>
                                                    <TableCell>
                                                        <Badge
                                                            className={`${status.color} flex w-fit items-center gap-1 border-none text-white`}
                                                        >
                                                            <StatusIcon className="h-3 w-3" />
                                                            {status.label}
                                                        </Badge>
                                                    </TableCell>
                                                    <TableCell>
                                                        <div className="flex items-center gap-2 text-xs font-medium text-zinc-700 dark:text-zinc-300">
                                                            {status.recommendation !==
                                                                'N/A' && (
                                                                <ArrowRightLeft className="h-3.5 w-3.5 text-blue-500" />
                                                            )}
                                                            {
                                                                status.recommendation
                                                            }
                                                        </div>
                                                    </TableCell>
                                                </TableRow>
                                            );
                                        })
                                    )}
                                </TableBody>
                            </Table>
                        </div>
                        <div className="border-t p-4">
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
                                label="items"
                            />
                        </div>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
