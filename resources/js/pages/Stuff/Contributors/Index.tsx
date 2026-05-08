import { Head, router } from '@inertiajs/react';
import { format } from 'date-fns';
import { ChevronDown, Filter, X } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
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

interface Stats {
    topItems: any[];
    groupByBrand: any[];
    groupByType: any[];
    groupBySize: any[];
}

interface Props {
    stats: Stats;
    filters: {
        from: string;
        to: string;
        customer_id: string | null;
        filterBrand: string | null;
    };
    brandList: { id: number; name: string }[];
    customers: { id: number; name: string }[];
}

export default function ContributorsIndex({ stats, filters, brandList, customers }: Props) {
    const [from, setFrom] = useState(filters.from);
    const [to, setTo] = useState(filters.to);
    const [customerId, setCustomerId] = useState(filters.customer_id?.toString() || 'all');
    const [filterBrand, setFilterBrand] = useState(filters.filterBrand?.toString() || 'all');

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Stuff', href: '#' },
        { title: 'Contributors', href: '/contributors' },
    ];

    const handleFilter = (e: React.FormEvent) => {
        e.preventDefault();
        router.get('/contributors', {
            from,
            to,
            customer_id: customerId === 'all' ? null : customerId,
            filterBrand: filterBrand === 'all' ? null : filterBrand,
        }, { preserveState: true });
    };

    const handleClear = () => {
        router.get('/contributors');
    };

    const formatCurrency = (amount: number) => {
        return new Intl.NumberFormat('id-ID', {
            style: 'currency',
            currency: 'IDR',
            minimumFractionDigits: 0,
        }).format(amount);
    };

    const formatDate = (dateStr: string) => {
        try {
            return format(new Date(dateStr), 'dd/MM/yyyy');
        } catch (e) {
            return dateStr;
        }
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Contributors Report" />

            <div className="mx-auto w-full max-w-7xl flex flex-col gap-6 p-4 sm:p-6 lg:p-8">
                <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <h1 className="text-2xl font-bold tracking-tight text-zinc-900 dark:text-zinc-50">
                        Contributors from {formatDate(filters.from)} - {formatDate(filters.to)}
                    </h1>
                </div>

                {/* Filter Section */}
                <div className="rounded-xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-800 dark:bg-zinc-900/50">
                    <form onSubmit={handleFilter} className="flex flex-col gap-4">
                        <div className="grid grid-cols-1 gap-4 md:grid-cols-4 lg:grid-cols-5">
                            <div className="space-y-2">
                                <Label htmlFor="from">From</Label>
                                <Input
                                    id="from"
                                    type="date"
                                    value={from}
                                    onChange={(e) => setFrom(e.target.value)}
                                />
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="to">To</Label>
                                <Input
                                    id="to"
                                    type="date"
                                    value={to}
                                    onChange={(e) => setTo(e.target.value)}
                                />
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="customer">Addr Book</Label>
                                <Select value={customerId} onValueChange={setCustomerId}>
                                    <SelectTrigger>
                                        <SelectValue placeholder="All Entries" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">All Entries</SelectItem>
                                        {customers.map((c) => (
                                            <SelectItem key={c.id} value={c.id.toString()}>
                                                {c.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="type">Brand Filter</Label>
                                <Select value={filterBrand} onValueChange={setFilterBrand}>
                                    <SelectTrigger>
                                        <SelectValue placeholder="Choose Brand" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">All Brands</SelectItem>
                                        {brandList.map((b) => (
                                            <SelectItem key={b.id} value={b.id.toString()}>
                                                {b.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                            <div className="flex items-end gap-2 md:col-span-1 lg:col-span-1">
                                <Button type="submit" className="w-full sm:w-auto bg-blue-600 hover:bg-blue-700 text-white">
                                    <Filter className="mr-2 h-4 w-4" />
                                    Filter
                                </Button>
                                <Button type="button" variant="outline" onClick={handleClear} className="w-full sm:w-auto">
                                    <X className="mr-2 h-4 w-4" />
                                    Clear
                                </Button>
                            </div>
                        </div>
                    </form>
                </div>

                {/* Accordions Section */}
                <div className="flex flex-col gap-4">
                    {/* Top 50 Item */}
                    <AccordionSection title="Top 50 Item" defaultOpen={true}>
                        <Table>
                            <TableHeader className="bg-zinc-50 dark:bg-zinc-900/50">
                                <TableRow>
                                    <TableHead className="font-semibold text-zinc-900 dark:text-zinc-100">Item Name</TableHead>
                                    <TableHead className="font-semibold text-zinc-900 dark:text-zinc-100">Brand</TableHead>
                                    <TableHead className="font-semibold text-zinc-900 dark:text-zinc-100">Type</TableHead>
                                    <TableHead className="font-semibold text-zinc-900 dark:text-zinc-100">Size</TableHead>
                                    <TableHead className="text-right font-semibold text-zinc-900 dark:text-zinc-100">Qty</TableHead>
                                    <TableHead className="text-right font-semibold text-zinc-900 dark:text-zinc-100">Value</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {stats.topItems.map((row) => (
                                    <TableRow key={row.item_id}>
                                        <TableCell className="font-medium text-blue-600 hover:underline dark:text-blue-500">
                                            <a href={`/items/${row.item_id}`}>{row.item?.name || 'Unknown'}</a>
                                        </TableCell>
                                        <TableCell>{row.brand_label || '-'}</TableCell>
                                        <TableCell>{row.type_label || 'Accessories'}</TableCell>
                                        <TableCell>{row.size_label || 'Accessories'}</TableCell>
                                        <TableCell className="text-right">{row.qty}</TableCell>
                                        <TableCell className="text-right">{formatCurrency(row.amount)}</TableCell>
                                    </TableRow>
                                ))}
                                {stats.topItems.length === 0 && (
                                    <TableRow>
                                        <TableCell colSpan={6} className="h-24 text-center text-zinc-500">
                                            No data found for this period.
                                        </TableCell>
                                    </TableRow>
                                )}
                            </TableBody>
                        </Table>
                    </AccordionSection>

                    {/* Group by Brand */}
                    <AccordionSection title="Group by Brand">
                        <Table>
                            <TableHeader className="bg-zinc-50 dark:bg-zinc-900/50">
                                <TableRow>
                                    <TableHead className="font-semibold text-zinc-900 dark:text-zinc-100">Brand</TableHead>
                                    <TableHead className="text-right font-semibold text-zinc-900 dark:text-zinc-100">Quantity</TableHead>
                                    <TableHead className="text-right font-semibold text-zinc-900 dark:text-zinc-100">Value</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {stats.groupByBrand.map((row, idx) => (
                                    <TableRow key={idx}>
                                        <TableCell className="font-medium">{row.brand_label}</TableCell>
                                        <TableCell className="text-right">{row.qty}</TableCell>
                                        <TableCell className="text-right">{formatCurrency(row.amount)}</TableCell>
                                    </TableRow>
                                ))}
                                {stats.groupByBrand.length === 0 && (
                                    <TableRow>
                                        <TableCell colSpan={3} className="h-24 text-center text-zinc-500">
                                            Data Empty
                                        </TableCell>
                                    </TableRow>
                                )}
                            </TableBody>
                        </Table>
                    </AccordionSection>

                    {/* Group by Type */}
                    <AccordionSection title="Group by Type">
                        <Table>
                            <TableHeader className="bg-zinc-50 dark:bg-zinc-900/50">
                                <TableRow>
                                    <TableHead className="font-semibold text-zinc-900 dark:text-zinc-100">Type</TableHead>
                                    <TableHead className="text-right font-semibold text-zinc-900 dark:text-zinc-100">Quantity</TableHead>
                                    <TableHead className="text-right font-semibold text-zinc-900 dark:text-zinc-100">Value</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {stats.groupByType.map((row, idx) => (
                                    <TableRow key={idx}>
                                        <TableCell className="font-medium">{row.type_label}</TableCell>
                                        <TableCell className="text-right">{row.qty}</TableCell>
                                        <TableCell className="text-right">{formatCurrency(row.amount)}</TableCell>
                                    </TableRow>
                                ))}
                                {stats.groupByType.length === 0 && (
                                    <TableRow>
                                        <TableCell colSpan={3} className="h-24 text-center text-zinc-500">
                                            Data Empty
                                        </TableCell>
                                    </TableRow>
                                )}
                            </TableBody>
                        </Table>
                    </AccordionSection>

                    {/* Group by Size */}
                    <AccordionSection title="Group by Size">
                        <Table>
                            <TableHeader className="bg-zinc-50 dark:bg-zinc-900/50">
                                <TableRow>
                                    <TableHead className="font-semibold text-zinc-900 dark:text-zinc-100">Size</TableHead>
                                    <TableHead className="text-right font-semibold text-zinc-900 dark:text-zinc-100">Quantity</TableHead>
                                    <TableHead className="text-right font-semibold text-zinc-900 dark:text-zinc-100">Value</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {stats.groupBySize.map((row, idx) => (
                                    <TableRow key={idx}>
                                        <TableCell className="font-medium">{row.size}</TableCell>
                                        <TableCell className="text-right">{row.qty}</TableCell>
                                        <TableCell className="text-right">{formatCurrency(row.amount)}</TableCell>
                                    </TableRow>
                                ))}
                                {stats.groupBySize.length === 0 && (
                                    <TableRow>
                                        <TableCell colSpan={3} className="h-24 text-center text-zinc-500">
                                            Data Empty
                                        </TableCell>
                                    </TableRow>
                                )}
                            </TableBody>
                        </Table>
                    </AccordionSection>
                </div>
            </div>
        </AppLayout>
    );
}

function AccordionSection({ title, children, defaultOpen = false }: { title: string, children: React.ReactNode, defaultOpen?: boolean }) {
    const [isOpen, setIsOpen] = useState(defaultOpen);

    return (
        <Collapsible open={isOpen} onOpenChange={setIsOpen} className="rounded-xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-800 dark:bg-zinc-900/50 overflow-hidden">
            <CollapsibleTrigger asChild>
                <Button variant="ghost" className="flex w-full items-center justify-between p-6 text-left hover:bg-zinc-50 dark:hover:bg-zinc-800/50 rounded-none h-auto">
                    <span className="text-lg font-semibold text-zinc-900 dark:text-zinc-50">{title}</span>
                    <ChevronDown className={`h-5 w-5 transition-transform duration-200 ${isOpen ? 'rotate-180' : ''}`} />
                </Button>
            </CollapsibleTrigger>
            <CollapsibleContent className="border-t border-zinc-200 dark:border-zinc-800 p-6">
                {children}
            </CollapsibleContent>
        </Collapsible>
    );
}
