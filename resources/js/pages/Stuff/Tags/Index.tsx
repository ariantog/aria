import { Head, Link, router, useForm } from '@inertiajs/react';
import {
    FilePen,
    Trash2,
    Plus,
    Search,
    Tag as TagIcon,
    ArrowUpDown,
} from 'lucide-react';
import type { FormEvent } from 'react';
import { useState } from 'react';
import Pagination from '@/components/Partial/Pagination';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
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
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';

interface Tag {
    id: number;
    name: string;
    code: string | null;
    type: number;
    item_type: number;
}

interface Props {
    tags: {
        data: Tag[];
        links: any[];
        from: number;
        to: number;
        total: number;
    };
    types: Record<number, string>;
    itemTypes: Record<string, number>;
}

export default function TagsIndex({ tags, types, itemTypes }: Props) {
    const [search, setSearch] = useState('');
    const [isOpen, setIsOpen] = useState(false);
    const [editingId, setEditingId] = useState<number | null>(null);

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Stuff', href: '#' },
        { title: 'Tags', href: '/tags' },
    ];

    const {
        data,
        setData,
        post,
        put,
        delete: destroy,
        processing,
        errors,
        reset,
        clearErrors,
    } = useForm({
        name: '',
        code: '',
        type: '',
        item_type: '0',
    });

    const handleSearch = (e: React.FormEvent) => {
        e.preventDefault();
        router.get('/tags', { search }, { preserveState: true });
    };

    const handleOpenCreate = () => {
        setEditingId(null);
        reset();
        clearErrors();
        setIsOpen(true);
    };

    const handleOpenEdit = (tag: Tag) => {
        setEditingId(tag.id);
        setData({
            name: tag.name,
            code: tag.code || '',
            type: tag.type.toString(),
            item_type: tag.item_type.toString(),
        });
        clearErrors();
        setIsOpen(true);
    };

    const handleSubmit = (e: FormEvent) => {
        e.preventDefault();
        if (editingId) {
            put(`/tags/${editingId}`, {
                onSuccess: () => setIsOpen(false),
            });
        } else {
            post('/tags', {
                onSuccess: () => setIsOpen(false),
            });
        }
    };

    const handleDelete = (id: number) => {
        if (confirm('Are you sure you want to delete this tag?')) {
            destroy(`/tags/${id}`);
        }
    };

    // Helper to get item type label
    const getItemTypeLabel = (itemTypeValue: number) => {
        const found = Object.entries(itemTypes).find(
            ([key, val]) => val === itemTypeValue,
        );
        return found ? found[0] : 'Unknown';
    };

    const getTypeColor = (typeId: number) => {
        switch (typeId) {
            case 0:
                return 'bg-zinc-100 text-zinc-800 dark:bg-zinc-800 dark:text-zinc-300'; // Normal
            case 2:
                return 'bg-purple-100 text-purple-800 dark:bg-purple-900/30 dark:text-purple-300'; // Jahit
            case 3:
                return 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300'; // Type
            case 7:
                return 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-300'; // Size
            case 8:
                return 'bg-orange-100 text-orange-800 dark:bg-orange-900/30 dark:text-orange-300'; // Komponen
            case 9:
                return 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300'; // Material
            case 10:
                return 'bg-pink-100 text-pink-800 dark:bg-pink-900/30 dark:text-pink-300'; // Variasi
            case 20:
                return 'bg-rose-100 text-rose-800 dark:bg-rose-900/30 dark:text-rose-300'; // Warna
            default:
                return 'bg-zinc-100 text-zinc-800 dark:bg-zinc-800 dark:text-zinc-300';
        }
    };

    const getItemTypeColor = (itemTypeId: number) => {
        switch (itemTypeId) {
            case 0:
                return 'bg-zinc-100 text-zinc-800 dark:bg-zinc-800 dark:text-zinc-300'; // All
            case 1:
                return 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300'; // Item
            case 2:
                return 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-300'; // Asset Lancar
            case 3:
                return 'bg-indigo-100 text-indigo-800 dark:bg-indigo-900/30 dark:text-indigo-300'; // Asset Tetap
            case 5:
                return 'bg-violet-100 text-violet-800 dark:bg-violet-900/30 dark:text-violet-300'; // Service
            default:
                return 'bg-zinc-100 text-zinc-800 dark:bg-zinc-800 dark:text-zinc-300';
        }
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Tags" />

            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                {/* Stat Cards - Dashboard Style */}
                <div className="grid auto-rows-min gap-4 md:grid-cols-3">
                    <div className="rounded-xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-800 dark:bg-zinc-900/50">
                        <div className="flex items-center gap-3">
                            <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-indigo-100 dark:bg-indigo-900/30">
                                <TagIcon className="h-5 w-5 text-indigo-600 dark:text-indigo-400" />
                            </div>
                            <div>
                                <p className="text-sm font-medium text-zinc-500 dark:text-zinc-400">Total Tags</p>
                                <p className="text-2xl font-bold text-zinc-900 dark:text-zinc-50">{tags.total}</p>
                            </div>
                        </div>
                    </div>
                    <div className="rounded-xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-800 dark:bg-zinc-900/50">
                        <div className="flex items-center gap-3">
                            <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-blue-100 dark:bg-blue-900/30">
                                <Search className="h-5 w-5 text-blue-600 dark:text-blue-400" />
                            </div>
                            <div>
                                <p className="text-sm font-medium text-zinc-500 dark:text-zinc-400">Types</p>
                                <p className="text-2xl font-bold text-zinc-900 dark:text-zinc-50">{Object.keys(types).length}</p>
                            </div>
                        </div>
                    </div>
                    <div className="rounded-xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-800 dark:bg-zinc-900/50">
                        <div className="flex items-center justify-between">
                            <div className="flex items-center gap-3">
                                <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-emerald-100 dark:bg-emerald-900/30">
                                    <Plus className="h-5 w-5 text-emerald-600 dark:text-emerald-400" />
                                </div>
                                <div>
                                    <p className="text-sm font-medium text-zinc-500 dark:text-zinc-400">Quick Action</p>
                                    <p className="text-xs text-zinc-400">Create a new tag</p>
                                </div>
                            </div>
                            <Button
                                onClick={handleOpenCreate}
                                size="sm"
                                className="bg-blue-600 text-white hover:bg-blue-700"
                            >
                                Add Tag
                            </Button>
                        </div>
                    </div>
                </div>

                {/* Main Content Area */}
                <div className="flex flex-1 flex-col gap-4 rounded-xl border border-zinc-200 bg-white p-6 dark:border-zinc-800 dark:bg-zinc-900/50">
                    <div className="flex flex-col items-start justify-between gap-4 sm:flex-row sm:items-center">
                        <div>
                            <h2 className="text-xl font-bold tracking-tight text-zinc-900 dark:text-zinc-50">
                                Tags Registry
                            </h2>
                            <p className="text-sm text-zinc-500 dark:text-zinc-400">
                                Detailed list of all categorized tags.
                            </p>
                        </div>
                        <div className="relative w-full max-w-sm">
                            <form onSubmit={handleSearch}>
                                <Search className="absolute top-2.5 left-2.5 h-4 w-4 text-zinc-500" />
                                <Input
                                    value={search}
                                    onChange={(e) => setSearch(e.target.value)}
                                    placeholder="Search tags..."
                                    className="bg-zinc-50 pl-9 dark:bg-zinc-900"
                                />
                            </form>
                        </div>
                    </div>

                    <div className="mt-4 overflow-hidden rounded-lg border border-zinc-200 dark:border-zinc-800">
                        <div className="overflow-x-auto">
                            <table className="min-w-full divide-y divide-zinc-200 dark:divide-zinc-800">
                                <thead className="bg-zinc-50/50 dark:bg-zinc-900/50">
                                    <tr>
                                        <th className="px-6 py-3 text-left text-xs font-semibold tracking-wider text-zinc-500 uppercase dark:text-zinc-400">
                                            Tag Name
                                        </th>
                                        <th className="px-6 py-3 text-left text-xs font-semibold tracking-wider text-zinc-500 uppercase dark:text-zinc-400">
                                            Code
                                        </th>
                                        <th className="px-6 py-3 text-left text-xs font-semibold tracking-wider text-zinc-500 uppercase dark:text-zinc-400">
                                            Type
                                        </th>
                                        <th className="px-6 py-3 text-left text-xs font-semibold tracking-wider text-zinc-500 uppercase dark:text-zinc-400">
                                            Item Type
                                        </th>
                                        <th className="px-6 py-3 text-right text-xs font-semibold tracking-wider text-zinc-500 uppercase dark:text-zinc-400">
                                            Actions
                                        </th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-zinc-200 bg-transparent dark:divide-zinc-800">
                                    {tags.data.map((tag) => (
                                        <tr
                                            key={tag.id}
                                            className="group transition-colors hover:bg-zinc-50/50 dark:hover:bg-zinc-800/50"
                                        >
                                            <td className="px-6 py-4 whitespace-nowrap">
                                                <div className="flex items-center gap-3">
                                                    <div className="text-sm font-bold text-zinc-900 dark:text-white">
                                                        {tag.name}
                                                    </div>
                                                </div>
                                            </td>
                                            <td className="px-6 py-4 whitespace-nowrap">
                                                <span className="rounded bg-zinc-100 px-2 py-1 font-mono text-xs text-zinc-700 dark:bg-zinc-800 dark:text-zinc-300">
                                                    {tag.code || '-'}
                                                </span>
                                            </td>
                                            <td className="px-6 py-4 whitespace-nowrap">
                                                <span
                                                    className={`inline-flex items-center rounded-full px-2.5 py-0.5 text-[10px] font-bold uppercase ${getTypeColor(tag.type)}`}
                                                >
                                                    {types[tag.type] || tag.type}
                                                </span>
                                            </td>
                                            <td className="px-6 py-4 whitespace-nowrap">
                                                <span
                                                    className={`inline-flex items-center rounded-full px-2.5 py-0.5 text-[10px] font-bold uppercase ${getItemTypeColor(tag.item_type)}`}
                                                >
                                                    {getItemTypeLabel(tag.item_type)}
                                                </span>
                                            </td>
                                            <td className="px-6 py-4 text-right text-sm font-medium whitespace-nowrap">
                                                <div className="flex justify-end gap-1">
                                                    <Button
                                                        onClick={() => handleOpenEdit(tag)}
                                                        variant="ghost"
                                                        size="icon"
                                                        className="h-8 w-8 text-zinc-400 hover:text-zinc-900 dark:hover:text-zinc-100"
                                                    >
                                                        <FilePen className="h-4 w-4" />
                                                    </Button>
                                                    <Button
                                                        onClick={() => handleDelete(tag.id)}
                                                        variant="ghost"
                                                        size="icon"
                                                        className="h-8 w-8 text-zinc-400 hover:text-red-600 dark:hover:text-red-400"
                                                    >
                                                        <Trash2 className="h-4 w-4" />
                                                    </Button>
                                                </div>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                        {tags.links && tags.links.length > 3 && (
                            <div className="border-t border-zinc-200 bg-zinc-50/30 px-6 py-4 dark:border-zinc-800 dark:bg-zinc-900/30">
                                <Pagination
                                    links={tags.links}
                                    from={tags.from}
                                    to={tags.to}
                                    total={tags.total}
                                    label="tags"
                                />
                            </div>
                        )}
                    </div>
                </div>
            </div>

            {/* Create/Edit Dialog */}
            <Dialog open={isOpen} onOpenChange={setIsOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>
                            {editingId ? 'Edit Tag' : 'Add Tag'}
                        </DialogTitle>
                    </DialogHeader>
                    <form onSubmit={handleSubmit} className="space-y-4">
                        <div className="space-y-2">
                            <Label htmlFor="name">Name</Label>
                            <Input
                                id="name"
                                value={data.name}
                                onChange={(e) =>
                                    setData('name', e.target.value)
                                }
                                required
                            />
                            {errors.name && (
                                <p className="text-sm text-red-500">
                                    {errors.name}
                                </p>
                            )}
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="code">Code</Label>
                            <Input
                                id="code"
                                value={data.code}
                                onChange={(e) =>
                                    setData('code', e.target.value)
                                }
                            />
                            {errors.code && (
                                <p className="text-sm text-red-500">
                                    {errors.code}
                                </p>
                            )}
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="type">Type</Label>
                            <Select
                                value={data.type}
                                onValueChange={(val) => setData('type', val)}
                            >
                                <SelectTrigger>
                                    <SelectValue placeholder="Select a type" />
                                </SelectTrigger>
                                <SelectContent>
                                    {Object.entries(types).map(
                                        ([val, label]) => (
                                            <SelectItem key={val} value={val}>
                                                {label}
                                            </SelectItem>
                                        ),
                                    )}
                                </SelectContent>
                            </Select>
                            {errors.type && (
                                <p className="text-sm text-red-500">
                                    {errors.type}
                                </p>
                            )}
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="item_type">Item Type</Label>
                            <Select
                                value={data.item_type}
                                onValueChange={(val) =>
                                    setData('item_type', val)
                                }
                            >
                                <SelectTrigger>
                                    <SelectValue placeholder="Select an item type (optional)" />
                                </SelectTrigger>
                                <SelectContent>
                                    {Object.entries(itemTypes).map(
                                        ([label, val]) => (
                                            <SelectItem
                                                key={val}
                                                value={val.toString()}
                                            >
                                                {label}
                                            </SelectItem>
                                        ),
                                    )}
                                </SelectContent>
                            </Select>
                            {errors.item_type && (
                                <p className="text-sm text-red-500">
                                    {errors.item_type}
                                </p>
                            )}
                        </div>

                        <DialogFooter>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setIsOpen(false)}
                            >
                                Cancel
                            </Button>
                            <Button type="submit" disabled={processing}>
                                {editingId ? 'Save Changes' : 'Create'}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </AppLayout>
    );
}
