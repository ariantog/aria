import { Head, useForm, Link } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { BreadcrumbItem } from '@/types';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Trash2, Plus, ArrowLeft, Calendar as CalendarIcon, User, Layers, Hash, Palette, UserCircle, Save } from 'lucide-react';
import { useState } from 'react';

interface Worker {
    id: number;
    name: string;
}

interface Size {
    id: number;
    name: string;
}

interface Props {
    workers: Worker[];
    sizes: Size[];
}

export default function ProduksiCreate({ workers, sizes }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Produksi', href: '#' },
        { title: 'Potong', href: '#' },
        { title: 'New Entry', href: '/produksi/create' },
    ];

    const { data, setData, post, processing, errors } = useForm({
        date: new Date().toISOString().split('T')[0],
        potong_id: '',
        surat_jalan_potong: '',
        items: [
            { name: '', size_id: '', qty: 1, customer: '', warna: '' }
        ],
    });

    const addItem = () => {
        setData('items', [...data.items, { name: '', size_id: '', qty: 1, customer: '', warna: '' }]);
    };

    const removeItem = (index: number) => {
        const newItems = [...data.items];
        newItems.splice(index, 1);
        setData('items', newItems);
    };

    const updateItem = (index: number, field: string, value: any) => {
        const newItems = [...data.items];
        (newItems[index] as any)[field] = value;
        setData('items', newItems);
    };

    const handleKeyDown = (e: React.KeyboardEvent, index?: number, field?: string) => {
        if (e.key === 'Enter') {
            const current = e.target as HTMLElement;
            // Prevent Enter from submitting the form unless it's the submit button
            if (current.tagName === 'BUTTON' && (current as HTMLButtonElement).type === 'submit') {
                return;
            }

            e.preventDefault();

            // If we're in the 'warna' field and it's the last item, add a new row
            if (field === 'warna' && index === data.items.length - 1) {
                addItem();
                // We need to wait for the new row to render before focusing
                setTimeout(() => {
                    const form = current.closest('form');
                    if (form) {
                        const focusables = Array.from(form.querySelectorAll(
                            'table tbody tr:last-child input:first-child'
                        )) as HTMLElement[];
                        if (focusables.length > 0) {
                            focusables[0].focus();
                        }
                    }
                }, 50);
                return;
            }

            const form = current.closest('form');
            if (form) {
                const focusables = Array.from(form.querySelectorAll(
                    'input:not([disabled]), button:not([disabled]):not([type="button"]), [role="combobox"]:not([disabled]), select:not([disabled])'
                )) as HTMLElement[];

                const indexInFocusables = focusables.indexOf(current);
                if (indexInFocusables > -1 && indexInFocusables < focusables.length - 1) {
                    focusables[indexInFocusables + 1].focus();
                }
            }
        }
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post('/produksi');
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="New Potong Entry" />

            <div className="p-4 sm:p-6 lg:p-8">
                <div className="max-w-6xl mx-auto">
                    {/* Header */}
                    <div className="flex items-center gap-4 mb-8">
                        <Button asChild variant="ghost" size="icon" className="rounded-full">
                            <Link href="/produksi">
                                <ArrowLeft className="h-5 w-5" />
                            </Link>
                        </Button>
                        <div>
                            <h2 className="text-2xl font-bold tracking-tight text-zinc-900 dark:text-zinc-50">New Production Entry</h2>
                            <p className="text-sm text-zinc-500 dark:text-zinc-400">Record a new batch of production at the Potong stage.</p>
                        </div>
                    </div>

                    <form onSubmit={handleSubmit} className="space-y-8">
                        {/* Master Data Card */}
                        <div className="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-xl p-6 shadow-sm">
                            <h3 className="text-lg font-semibold mb-6 flex items-center gap-2">
                                <Layers className="h-5 w-5 text-blue-500" />
                                General Information
                            </h3>
                            <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
                                <div className="space-y-2">
                                    <Label htmlFor="date">Date</Label>
                                    <div className="relative">
                                        <CalendarIcon className="absolute left-3 top-2.5 h-4 w-4 text-zinc-400" />
                                        <Input
                                            id="date"
                                            type="date"
                                            className="pl-9"
                                            value={data.date}
                                            onChange={(e) => setData('date', e.target.value)}
                                            onKeyDown={handleKeyDown}
                                            required
                                        />
                                    </div>
                                    {errors.date && <p className="text-sm text-red-500">{errors.date}</p>}
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="worker">Worker (Potong)</Label>
                                    <Select value={data.potong_id} onValueChange={(val) => setData('potong_id', val)}>
                                        <SelectTrigger id="worker" className="pl-3" onKeyDown={handleKeyDown}>
                                            <div className="flex items-center gap-2">
                                                <User className="h-4 w-4 text-zinc-400" />
                                                <SelectValue placeholder="Select worker" />
                                            </div>
                                        </SelectTrigger>
                                        <SelectContent>
                                            {workers.map((w) => (
                                                <SelectItem key={w.id} value={w.id.toString()}>{w.name}</SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    {errors.potong_id && <p className="text-sm text-red-500">{errors.potong_id}</p>}
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="sj_potong">Surat Jalan Potong</Label>
                                    <div className="relative">
                                        <Hash className="absolute left-3 top-2.5 h-4 w-4 text-zinc-400" />
                                        <Input
                                            id="sj_potong"
                                            className="pl-9"
                                            placeholder="Optional"
                                            value={data.surat_jalan_potong}
                                            onChange={(e) => setData('surat_jalan_potong', e.target.value)}
                                            onKeyDown={handleKeyDown}
                                        />
                                    </div>
                                    {errors.surat_jalan_potong && <p className="text-sm text-red-500">{errors.surat_jalan_potong}</p>}
                                </div>
                            </div>
                        </div>

                        {/* Items Table Card */}
                        <div className="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-xl shadow-sm overflow-hidden">
                            <div className="p-6 border-b border-zinc-200 dark:border-zinc-800 flex justify-between items-center">
                                <h3 className="text-lg font-semibold flex items-center gap-2">
                                    <Plus className="h-5 w-5 text-emerald-500" />
                                    Production Items
                                </h3>
                                <Button type="button" variant="outline" size="sm" onClick={addItem} className="gap-2">
                                    <Plus className="h-4 w-4" />
                                    Add Row
                                </Button>
                            </div>

                            <div className="overflow-x-auto">
                                <table className="min-w-full divide-y divide-zinc-200 dark:divide-zinc-800">
                                    <thead className="bg-zinc-50/50 dark:bg-zinc-900/50">
                                        <tr>
                                            <th className="px-4 py-3 text-left text-xs font-semibold text-zinc-500 uppercase tracking-wider">Product Name</th>
                                            <th className="px-4 py-3 text-left text-xs font-semibold text-zinc-500 uppercase tracking-wider w-32">Size</th>
                                            <th className="px-4 py-3 text-left text-xs font-semibold text-zinc-500 uppercase tracking-wider w-24">Qty</th>
                                            <th className="px-4 py-3 text-left text-xs font-semibold text-zinc-500 uppercase tracking-wider">Customer</th>
                                            <th className="px-4 py-3 text-left text-xs font-semibold text-zinc-500 uppercase tracking-wider">Warna</th>
                                            <th className="px-4 py-3 text-center text-xs font-semibold text-zinc-500 uppercase tracking-wider w-16"></th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-zinc-200 dark:divide-zinc-800 bg-white dark:bg-zinc-900">
                                        {data.items.map((item, index) => (
                                            <tr key={index} className="group hover:bg-zinc-50/30 dark:hover:bg-zinc-800/30">
                                                <td className="px-4 py-3">
                                                    <Input
                                                        value={item.name}
                                                        onChange={(e) => updateItem(index, 'name', e.target.value)}
                                                        onKeyDown={handleKeyDown}
                                                        placeholder="Item/Model name"
                                                        required
                                                    />
                                                </td>
                                                <td className="px-4 py-3">
                                                    <Select value={item.size_id} onValueChange={(val) => updateItem(index, 'size_id', val)}>
                                                        <SelectTrigger onKeyDown={handleKeyDown}>
                                                            <SelectValue placeholder="Size" />
                                                        </SelectTrigger>
                                                        <SelectContent>
                                                            {sizes.map((s) => (
                                                                <SelectItem key={s.id} value={s.id.toString()}>{s.name}</SelectItem>
                                                            ))}
                                                        </SelectContent>
                                                    </Select>
                                                </td>
                                                <td className="px-4 py-3">
                                                    <Input
                                                        type="number"
                                                        min="1"
                                                        value={item.qty}
                                                        onChange={(e) => updateItem(index, 'qty', e.target.value)}
                                                        onKeyDown={handleKeyDown}
                                                        required
                                                    />
                                                </td>
                                                <td className="px-4 py-3">
                                                    <div className="relative">
                                                        <UserCircle className="absolute left-2.5 top-2.5 h-3.5 w-3.5 text-zinc-400" />
                                                        <Input
                                                            className="pl-8"
                                                            value={item.customer || ''}
                                                            onChange={(e) => updateItem(index, 'customer', e.target.value)}
                                                            onKeyDown={handleKeyDown}
                                                            placeholder="Customer"
                                                        />
                                                    </div>
                                                </td>
                                                <td className="px-4 py-3">
                                                    <div className="relative">
                                                        <Palette className="absolute left-2.5 top-2.5 h-3.5 w-3.5 text-zinc-400" />
                                                        <Input
                                                            className="pl-8"
                                                            value={item.warna || ''}
                                                            onChange={(e) => updateItem(index, 'warna', e.target.value)}
                                                            onKeyDown={(e) => handleKeyDown(e, index, 'warna')}
                                                            placeholder="Color"
                                                        />
                                                    </div>
                                                </td>
                                                <td className="px-4 py-3 text-center">
                                                    {data.items.length > 1 && (
                                                        <Button
                                                            type="button"
                                                            variant="ghost"
                                                            size="icon"
                                                            onClick={() => removeItem(index)}
                                                            className="h-8 w-8 text-zinc-400 hover:text-red-500"
                                                        >
                                                            <Trash2 className="h-4 w-4" />
                                                        </Button>
                                                    )}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        {/* Submit Actions */}
                        <div className="flex items-center justify-end gap-4">
                            <Button asChild variant="ghost" className="text-zinc-500">
                                <Link href="/produksi">Cancel</Link>
                            </Button>
                            <Button type="submit" disabled={processing} className="bg-blue-600 hover:bg-blue-700 text-white min-w-[150px] gap-2">
                                <Save className="h-4 w-4" />
                                Save Production
                            </Button>
                        </div>
                    </form>
                </div>
            </div>
        </AppLayout>
    );
}
