import { Head, Link, useForm } from '@inertiajs/react';
import { Trash2, PackageCheck } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Stuff', href: '#' },
    { title: 'Restock', href: '/restock' },
    { title: 'Received Cart', href: '/restock/received' },
];

export default function RestockReceived({ items }: any) {
    const { data, setData, post, processing, errors } = useForm({
        date: new Date().toISOString().split('T')[0],
        invoice: '',
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post('/restock/received/store');
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Received Cart" />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div className="mb-8 text-left">
                    <h1 className="mb-1 text-3xl font-bold tracking-tight text-zinc-900 dark:text-zinc-50">
                        Received Cart
                    </h1>
                    <p className="text-zinc-500 dark:text-zinc-400">
                        Verify and receive items into the warehouse (Gudang).
                    </p>
                </div>

                <div className="grid grid-cols-1 gap-8 lg:grid-cols-3">
                    <div className="lg:col-span-2">
                        <div className="rounded-xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-800 dark:bg-zinc-900/50">
                            <div className="border-b border-zinc-200 p-6 dark:border-zinc-800">
                                <h2 className="text-lg font-semibold">Items to Receive</h2>
                            </div>
                            <div className="overflow-x-auto text-left">
                                <table className="w-full text-left text-sm">
                                    <thead className="bg-zinc-50 text-xs text-zinc-500 uppercase dark:bg-zinc-900/50">
                                        <tr>
                                            <th className="px-6 py-4 font-bold">Item</th>
                                            <th className="px-6 py-4 font-bold">Quantity</th>
                                            <th className="px-6 py-4 text-right font-bold">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-zinc-200 dark:divide-zinc-800/50">
                                        {items.length > 0 ? (
                                            items.map((item: any, index: number) => (
                                                <tr key={index} className="transition-colors hover:bg-zinc-50 dark:hover:bg-zinc-800/40">
                                                    <td className="px-6 py-4">
                                                        <div className="font-medium text-zinc-900 dark:text-zinc-100">{item.name}</div>
                                                        <div className="text-xs text-zinc-500">{item.code}</div>
                                                    </td>
                                                    <td className="px-6 py-4 font-mono">{item.quantity}</td>
                                                    <td className="px-6 py-4 text-right">
                                                        <Link
                                                            href={`/restock/remove-cart-item/${item.code}`}
                                                            method="post"
                                                            as="button"
                                                            className="rounded-lg p-2 text-zinc-400 transition-colors hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-900/20 dark:hover:text-red-400"
                                                        >
                                                            <Trash2 className="h-4 w-4" />
                                                        </Link>
                                                    </td>
                                                </tr>
                                            ))
                                        ) : (
                                            <tr>
                                                <td colSpan={3} className="px-6 py-12 text-center text-zinc-400 italic">
                                                    Your cart is empty. Add items from the Restock list.
                                                </td>
                                            </tr>
                                        )}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <div className="lg:col-span-1">
                        <div className="rounded-xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-800 dark:bg-zinc-900/50 text-left">
                            <h2 className="mb-4 text-lg font-semibold">Complete Receiving</h2>
                            <form onSubmit={handleSubmit} className="space-y-4">
                                <div>
                                    <Label htmlFor="date">Arrival Date</Label>
                                    <Input
                                        id="date"
                                        type="date"
                                        value={data.date}
                                        onChange={(e) => setData('date', e.target.value)}
                                        className="mt-1"
                                    />
                                    {errors.date && <p className="mt-1 text-xs text-red-500">{errors.date}</p>}
                                </div>
                                <div>
                                    <Label htmlFor="invoice">Invoice / Ref (Optional)</Label>
                                    <Input
                                        id="invoice"
                                        value={data.invoice}
                                        onChange={(e) => setData('invoice', e.target.value)}
                                        placeholder="e.g. SJ-123"
                                        className="mt-1"
                                    />
                                    {errors.invoice && <p className="mt-1 text-xs text-red-500">{errors.invoice}</p>}
                                </div>
                                {errors.gudang && (
                                    <div className="rounded-lg bg-red-50 p-3 text-sm text-red-600 dark:bg-red-900/20 dark:text-red-400">
                                        {errors.gudang}
                                    </div>
                                )}
                                <Button type="submit" className="w-full bg-emerald-600 text-white hover:bg-emerald-700" disabled={processing || items.length === 0}>
                                    <PackageCheck className="mr-2 h-4 w-4" /> Confirm & Add to Stock
                                </Button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
