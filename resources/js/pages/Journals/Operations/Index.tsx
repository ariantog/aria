import { Head, Link, router, useForm } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { BreadcrumbItem } from '@/types';
import { Button } from '@/components/ui/button';
import { FilePen, Trash2, Plus, Search, Layers } from 'lucide-react';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
    DialogFooter,
} from '@/components/ui/dialog';
import Pagination from '@/components/Partial/Pagination';
import { useState, FormEvent, useEffect } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Journals', href: '/journals/operations' },
    { title: 'Operations', href: '/journals/operations' },
];

interface Operation {
    id: number;
    name: string;
    description: string | null;
    created_at: string;
}

interface Props {
    operations: {
        data: Operation[];
        links: any[];
        from: number;
        to: number;
        total: number;
    };
}

export default function OperationsIndex({ operations }: Props) {
    const [search, setSearch] = useState('');
    const [isOpen, setIsOpen] = useState(false);
    const [editingId, setEditingId] = useState<number | null>(null);

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
        description: '',
    });

    const handleSearch = (e: React.FormEvent) => {
        e.preventDefault();
        router.get('/journals/operations', { search }, { preserveState: true });
    };

    const handleOpenCreate = () => {
        setEditingId(null);
        reset();
        clearErrors();
        setIsOpen(true);
    };

    const handleOpenEdit = (op: Operation) => {
        setEditingId(op.id);
        setData({ name: op.name, description: op.description || '' });
        clearErrors();
        setIsOpen(true);
    };

    const handleSubmit = (e: FormEvent) => {
        e.preventDefault();
        if (editingId) {
            put(`/journals/operations/${editingId}`, {
                onSuccess: () => setIsOpen(false),
            });
        } else {
            post('/journals/operations', {
                onSuccess: () => setIsOpen(false),
            });
        }
    };

    const handleDelete = (id: number) => {
        if (confirm('Are you sure you want to delete this operation?')) {
            destroy(`/journals/operations/${id}`);
        }
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Operations (Journal Categories)" />

            <div className="p-4 sm:p-6 lg:p-8">
                {/* Header */}
                <div className="mb-8 flex flex-col items-start justify-between gap-4 sm:flex-row sm:items-center">
                    <div>
                        <h2 className="text-2xl font-bold tracking-tight text-zinc-900 dark:text-zinc-50">
                            Operations
                        </h2>
                        <p className="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                            Manage journal operation categories.
                        </p>
                    </div>
                    <div className="flex w-full items-center gap-2 sm:w-auto">
                        <Button
                            onClick={handleOpenCreate}
                            className="w-full gap-2 bg-blue-600 text-white shadow-sm hover:bg-blue-700 sm:w-auto"
                        >
                            <Plus className="h-4 w-4" />
                            Add Operation
                        </Button>
                    </div>
                </div>

                <div className="relative mb-4 max-w-sm">
                    <form onSubmit={handleSearch}>
                        <Search className="absolute top-2.5 left-2.5 h-4 w-4 text-zinc-500" />
                        <Input
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            placeholder="Search operations..."
                            className="bg-zinc-50 pl-9 dark:bg-zinc-900"
                        />
                    </form>
                </div>

                {/* Table Card */}
                <div className="overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                    <div className="overflow-x-auto">
                        <table className="min-w-full divide-y divide-zinc-200 dark:divide-zinc-800">
                            <thead className="bg-zinc-50/50 dark:bg-zinc-900/50">
                                <tr>
                                    <th className="px-6 py-4 text-left text-xs font-semibold tracking-wider text-zinc-500 uppercase dark:text-zinc-400">
                                        Operation Name
                                    </th>
                                    <th className="px-6 py-4 text-center text-left text-xs font-semibold tracking-wider text-zinc-500 uppercase dark:text-zinc-400">
                                        Actions
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-zinc-200 bg-white dark:divide-zinc-800 dark:bg-zinc-900">
                                {operations.data.map((op) => (
                                    <tr
                                        key={op.id}
                                        className="group transition-colors hover:bg-zinc-50/50 dark:hover:bg-zinc-800/50"
                                    >
                                        <td className="px-6 py-4 whitespace-nowrap">
                                            <div className="flex items-center gap-3">
                                                <div className="flex h-8 w-8 items-center justify-center rounded-lg bg-blue-100 dark:bg-blue-900/30">
                                                    <Layers className="h-4 w-4 text-blue-600 dark:text-blue-400" />
                                                </div>
                                                <div>
                                                    <Link
                                                        href={`/journals/operations/${op.id}`}
                                                        className="text-sm font-bold text-blue-600 hover:text-blue-800 hover:underline dark:text-blue-400 dark:hover:text-blue-300"
                                                    >
                                                        {op.name}
                                                    </Link>
                                                    <div className="text-xs text-zinc-500">
                                                        {op.description ||
                                                            'No description'}
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                        <td className="px-6 py-4 text-right text-sm font-medium whitespace-nowrap">
                                            <div className="flex justify-center gap-2">
                                                <Button
                                                    onClick={() =>
                                                        handleOpenEdit(op)
                                                    }
                                                    variant="ghost"
                                                    size="icon"
                                                    className="h-8 w-8 text-zinc-400 hover:text-zinc-900 dark:hover:text-zinc-100"
                                                >
                                                    <FilePen className="h-4 w-4" />
                                                </Button>
                                                <Button
                                                    onClick={() =>
                                                        handleDelete(op.id)
                                                    }
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
                                {operations.data.length === 0 && (
                                    <tr>
                                        <td
                                            colSpan={2}
                                            className="px-6 py-12 text-center text-sm text-zinc-500 dark:text-zinc-400"
                                        >
                                            No operations found. Get started by
                                            adding a new operation.
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                    {/* Pagination */}
                    {operations.links && operations.links.length > 3 && (
                        <div className="border-t border-zinc-200 bg-zinc-50/30 px-6 py-4 dark:border-zinc-800 dark:bg-zinc-900/30">
                            <Pagination
                                links={operations.links}
                                from={operations.from}
                                to={operations.to}
                                total={operations.total}
                                label="operations"
                            />
                        </div>
                    )}
                </div>
            </div>

            {/* Create/Edit Dialog */}
            <Dialog open={isOpen} onOpenChange={setIsOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>
                            {editingId ? 'Edit Operation' : 'Add Operation'}
                        </DialogTitle>
                    </DialogHeader>
                    <form onSubmit={handleSubmit} className="space-y-4">
                        <div className="space-y-2">
                            <Label htmlFor="name">Operation Name</Label>
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
                            <Label htmlFor="description">Description</Label>
                            <Input
                                id="description"
                                value={data.description}
                                onChange={(e) =>
                                    setData('description', e.target.value)
                                }
                            />
                            {errors.description && (
                                <p className="text-sm text-red-500">
                                    {errors.description}
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
