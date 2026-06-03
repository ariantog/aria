import { Head, router, useForm } from '@inertiajs/react';
import { Save, RotateCcw, AlertTriangle } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Stuff', href: '#' },
    { title: 'Restock', href: '/restock' },
    { title: 'Update Quantities', href: '#' },
];

export default function RestockUpdate({ restock }: any) {
    const { data, setData, post, processing, errors } = useForm({
        type: 'restocked',
        qty: 0,
        invoice: '',
        date: new Date().toISOString().split('T')[0],
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post(`/restock/${restock.id}/update-qty`);
    };

    const handleReset = (type: string) => {
        if (confirm(`Are you sure you want to reset ${type} quantity to 0?`)) {
            router.post(`/restock/${restock.id}/reset`, { type });
        }
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Update ${restock.item?.name}`} />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div className="mb-8">
                    <h1 className="mb-1 text-3xl font-bold tracking-tight text-zinc-900 dark:text-zinc-50">
                        Update {restock.item?.name}
                    </h1>
                    <div className="flex flex-wrap items-center gap-x-4 gap-y-2 text-sm text-zinc-500 dark:text-zinc-400">
                        <span className="font-medium text-zinc-700 dark:text-zinc-300">{restock.item?.code}</span>
                        <span className="h-4 w-px bg-zinc-200 dark:bg-zinc-800"></span>
                        <span>Current Status:</span>
                        <div className="flex gap-2 font-mono">
                            <span title="Restocked">R: {restock.restocked_quantity}</span>
                            <span title="Production">P: {restock.in_production_quantity}</span>
                            <span title="Shipped">S: {restock.shipped_quantity}</span>
                            <span title="Missing">M: {restock.missing_quantity}</span>
                        </div>
                    </div>
                </div>

                <div className="grid grid-cols-1 gap-8 lg:grid-cols-3">
                    <div className="lg:col-span-2">
                        <div className="rounded-xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-800 dark:bg-zinc-900/50">
                            <form onSubmit={handleSubmit} className="space-y-6">
                                <div className="grid grid-cols-1 gap-6 sm:grid-cols-2">
                                    <div className="space-y-2">
                                        <Label htmlFor="type">Action Type</Label>
                                        <Select value={data.type} onValueChange={(val) => setData('type', val)}>
                                            <SelectTrigger id="type">
                                                <SelectValue placeholder="Select type" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="restocked">Add Restocked Qty</SelectItem>
                                                <SelectItem value="production">Move to Production</SelectItem>
                                                <SelectItem value="shipped">Move to Shipped</SelectItem>
                                                <SelectItem value="missing">Add Missing Qty</SelectItem>
                                            </SelectContent>
                                        </Select>
                                        {errors.type && <p className="text-xs text-red-500">{errors.type}</p>}
                                    </div>
                                    <div className="space-y-2">
                                        <Label htmlFor="qty">Quantity</Label>
                                        <Input
                                            id="qty"
                                            type="number"
                                            value={data.qty}
                                            onChange={(e) => setData('qty', parseInt(e.target.value))}
                                            min="1"
                                        />
                                        {errors.qty && <p className="text-xs text-red-500">{errors.qty}</p>}
                                    </div>
                                </div>

                                <div className="grid grid-cols-1 gap-6 sm:grid-cols-2">
                                    <div className="space-y-2">
                                        <Label htmlFor="invoice">Invoice / Note (Optional)</Label>
                                        <Input
                                            id="invoice"
                                            value={data.invoice}
                                            onChange={(e) => setData('invoice', e.target.value)}
                                            placeholder="e.g. INV-123"
                                        />
                                        {errors.invoice && <p className="text-xs text-red-500">{errors.invoice}</p>}
                                    </div>
                                    <div className="space-y-2">
                                        <Label htmlFor="date">Date</Label>
                                        <Input
                                            id="date"
                                            type="date"
                                            value={data.date}
                                            onChange={(e) => setData('date', e.target.value)}
                                        />
                                        {errors.date && <p className="text-xs text-red-500">{errors.date}</p>}
                                    </div>
                                </div>

                                <div className="flex items-center justify-end border-t border-zinc-200 pt-6 dark:border-zinc-800">
                                    <Button type="submit" className="bg-blue-600 text-white hover:bg-blue-700" disabled={processing}>
                                        <Save className="mr-2 h-4 w-4" /> Update Stock
                                    </Button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <div className="lg:col-span-1 space-y-6">
                        <div className="rounded-xl border border-red-200 bg-red-50/50 p-6 dark:border-red-900/30 dark:bg-red-900/10">
                            <div className="mb-4 flex items-center gap-2 text-red-800 dark:text-red-400">
                                <AlertTriangle className="h-5 w-5" />
                                <h2 className="text-lg font-semibold">Danger Zone</h2>
                            </div>
                            <p className="mb-6 text-sm text-red-700 dark:text-red-300">
                                Use these actions with caution. Resetting will set the quantity of a specific state back to zero.
                            </p>
                            <div className="space-y-3">
                                <Button 
                                    type="button" 
                                    variant="destructive" 
                                    className="w-full justify-start" 
                                    onClick={() => handleReset('restocked')}
                                >
                                    <RotateCcw className="mr-2 h-4 w-4" /> Reset Restocked Qty
                                </Button>
                                <Button 
                                    type="button" 
                                    variant="destructive" 
                                    className="w-full justify-start" 
                                    onClick={() => handleReset('production')}
                                >
                                    <RotateCcw className="mr-2 h-4 w-4" /> Reset Production Qty
                                </Button>
                                <Button 
                                    type="button" 
                                    variant="destructive" 
                                    className="w-full justify-start" 
                                    onClick={() => handleReset('shipped')}
                                >
                                    <RotateCcw className="mr-2 h-4 w-4" /> Reset Shipped Qty
                                </Button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
