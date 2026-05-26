import { Head, Link, useForm, router } from '@inertiajs/react';
import { Trash2, Plus, Save, Check } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import { useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Stuff', href: '#' },
    { title: 'Restock', href: '/restock' },
    { title: 'New Restock', href: '/restock/create' },
];

export default function RestockCreate({ items }: any) {
    const { data, setData, post, processing, errors, reset } = useForm({
        code: '',
        qty: 1,
    });

    const { data: storeData, setData: setStoreData, post: postStore, processing: storeProcessing } = useForm({
        date: new Date().toISOString().split('T')[0],
    });

    const [editingQty, setEditingQty] = useState<Record<string, number>>({});
    const [updatingKey, setUpdatingKey] = useState<string | null>(null);

    const handleAddItem = (e: React.FormEvent) => {
        e.preventDefault();
        post('/restock/add-item', {
            onSuccess: () => reset('code', 'qty'),
        });
    };

    const handleUpdateQty = (uniqueKey: string) => {
        const qty = editingQty[uniqueKey];
        if (qty === undefined) return;

        setUpdatingKey(uniqueKey);
        router.post(`/restock/update-item-qty/${uniqueKey}`, { qty }, {
            onSuccess: () => {
                setUpdatingKey(null);
                const newEditing = { ...editingQty };
                delete newEditing[uniqueKey];
                setEditingQty(newEditing);
            },
            onFinish: () => setUpdatingKey(null),
            preserveScroll: true
        });
    };

    const handleStore = (e: React.FormEvent) => {
        e.preventDefault();
        postStore('/restock');
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="New Restock" />
            <div className="p-4 sm:p-6 lg:p-8">
                <div className="mb-8">
                    <h1 className="mb-1 text-3xl font-bold tracking-tight text-zinc-900 dark:text-zinc-50">
                        New Restock
                    </h1>
                    <p className="text-zinc-500 dark:text-zinc-400">
                        Enter an Item SKU or Group Code to add items to the restock list.
                    </p>
                </div>

                <div className="grid grid-cols-1 gap-8 lg:grid-cols-3">
                    <div className="lg:col-span-1">
                        <div className="rounded-xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-800 dark:bg-zinc-900/50">
                            <h2 className="mb-4 text-lg font-semibold">Add Item</h2>
                            <form onSubmit={handleAddItem} className="space-y-4">
                                <div>
                                    <Label htmlFor="code">ID, SKU, or Group Code</Label>
                                    <Input
                                        id="code"
                                        value={data.code}
                                        onChange={(e) => setData('code', e.target.value)}
                                        placeholder="Enter code..."
                                        autoFocus
                                    />
                                    {errors.code && <p className="mt-1 text-xs text-red-500">{errors.code}</p>}
                                </div>
                                <div>
                                    <Label htmlFor="qty">Quantity</Label>
                                    <Input
                                        id="qty"
                                        type="number"
                                        value={data.qty}
                                        onChange={(e) => setData('qty', parseInt(e.target.value))}
                                        min="1"
                                    />
                                    {errors.qty && <p className="mt-1 text-xs text-red-500">{errors.qty}</p>}
                                </div>
                                <Button type="submit" className="w-full" disabled={processing}>
                                    <Plus className="mr-2 h-4 w-4" /> Add to List
                                </Button>
                                {items.length > 0 && (
                                    <Button 
                                        type="button" 
                                        variant="outline" 
                                        className="w-full border-red-200 text-red-600 hover:bg-red-50 hover:text-red-700 dark:border-red-900/30 dark:text-red-400 dark:hover:bg-red-900/20"
                                        onClick={() => router.post('/restock/clear-items')}
                                    >
                                        <Trash2 className="mr-2 h-4 w-4" /> Clear Entire List
                                    </Button>
                                )}
                            </form>
                        </div>
                    </div>

                    <div className="lg:col-span-2">
                        <div className="rounded-xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-800 dark:bg-zinc-900/50">
                            <div className="border-b border-zinc-200 p-6 dark:border-zinc-800">
                                <h2 className="text-lg font-semibold">Restock List</h2>
                            </div>
                            <div className="overflow-x-auto">
                                <table className="w-full text-left text-sm">
                                    <thead className="bg-zinc-50 text-xs text-zinc-500 uppercase dark:bg-zinc-900/50">
                                        <tr>
                                            <th className="px-6 py-4 font-bold">Group / Item</th>
                                            <th className="px-6 py-4 font-bold">Color</th>
                                            <th className="px-6 py-4 font-bold">Size / Type</th>
                                            <th className="px-6 py-4 font-bold">Quantity</th>
                                            <th className="px-6 py-4 text-right font-bold">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-zinc-200 dark:divide-zinc-800/50">
                                        {items.length > 0 ? (
                                            items.map((item: any, index: number) => {
                                                const uniqueKey = item.unique_key || item.code;
                                                const isEditing = editingQty[uniqueKey] !== undefined;
                                                const currentQty = isEditing ? editingQty[uniqueKey] : item.qty;

                                                return (
                                                    <tr key={index} className="transition-colors hover:bg-zinc-50 dark:hover:bg-zinc-800/40">
                                                        <td className="px-6 py-4">
                                                            <div className="font-medium text-zinc-900 dark:text-zinc-100">
                                                                {item.group_name || item.name}
                                                            </div>
                                                            <div className="text-xs text-zinc-500">{item.code}</div>
                                                        </td>
                                                        <td className="px-6 py-4">
                                                            {item.color_name ? <Badge variant="outline">{item.color_name}</Badge> : '-'}
                                                        </td>
                                                        <td className="px-6 py-4">
                                                            <div className="font-semibold">
                                                                {item.size_name || (item.size_id === 'all' ? 'All Sizes' : '-')}
                                                            </div>
                                                            <div className="text-[10px] text-zinc-400 uppercase tracking-tighter">
                                                                {item.size_type?.replace('-', ' ')}
                                                            </div>
                                                        </td>
                                                        <td className="px-6 py-4 font-mono">
                                                            <div className="flex items-center gap-2">
                                                                <Input 
                                                                    type="number"
                                                                    className="h-8 w-20 text-center"
                                                                    value={currentQty}
                                                                    onChange={(e) => setEditingQty({
                                                                        ...editingQty,
                                                                        [uniqueKey]: parseInt(e.target.value) || 0
                                                                    })}
                                                                    min="1"
                                                                />
                                                                {isEditing && (
                                                                    <Button 
                                                                        size="icon" 
                                                                        className="h-8 w-8 bg-emerald-600 hover:bg-emerald-700"
                                                                        onClick={() => handleUpdateQty(uniqueKey)}
                                                                        disabled={updatingKey === uniqueKey}
                                                                    >
                                                                        <Check className="h-4 w-4" />
                                                                    </Button>
                                                                )}
                                                            </div>
                                                        </td>
                                                        <td className="px-6 py-4 text-right">
                                                            <Link
                                                                href={`/restock/remove-item/${uniqueKey}`}
                                                                method="post"
                                                                as="button"
                                                                className="rounded-lg p-2 text-zinc-400 transition-colors hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-900/20 dark:hover:text-red-400"
                                                            >
                                                                <Trash2 className="h-4 w-4" />
                                                            </Link>
                                                        </td>
                                                    </tr>
                                                );
                                            })
                                        ) : (
                                            <tr>
                                                <td colSpan={5} className="px-6 py-12 text-center text-zinc-400 italic">
                                                    No items added yet.
                                                </td>
                                            </tr>
                                        )}
                                    </tbody>
                                </table>
                            </div>
                            {items.length > 0 && (
                                <div className="border-t border-zinc-200 p-6 dark:border-zinc-800">
                                    <form onSubmit={handleStore} className="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                                        <div className="flex-1 max-w-xs">
                                            <Label htmlFor="date">Restock Date</Label>
                                            <Input
                                                id="date"
                                                type="date"
                                                value={storeData.date}
                                                onChange={(e) => setStoreData('date', e.target.value)}
                                                className="mt-1"
                                            />
                                        </div>
                                        <Button type="submit" className="bg-emerald-600 text-white hover:bg-emerald-700" disabled={storeProcessing}>
                                            <Save className="mr-2 h-4 w-4" /> Save Restock
                                        </Button>
                                    </form>
                                </div>
                            )}
                        </div>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
