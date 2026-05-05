import { Head, Link, router, useForm } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { BreadcrumbItem } from '@/types';
import { Button } from '@/components/ui/button';
import {
    FilePen,
    Trash2,
    Plus,
    Search,
    Book,
    Eye,
    ArrowLeft,
} from 'lucide-react';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogFooter,
} from '@/components/ui/dialog';
import Pagination from '@/components/Partial/Pagination';
import { useState, FormEvent } from 'react';

interface Account {
    id: number;
    name: string;
    description: string | null;
    operation_id: number;
    created_at: string;
}

interface Operation {
    id: number;
    name: string;
    description: string | null;
}

interface Props {
    operation: Operation;
    accounts: {
        data: Account[];
        links: any[];
        from: number;
        to: number;
        total: number;
    };
}

export default function OperationShow({ operation, accounts }: Props) {
    const [search, setSearch] = useState('');
    const [isOpen, setIsOpen] = useState(false);
    const [editingId, setEditingId] = useState<number | null>(null);

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Journals', href: '/journals/operations' },
        { title: 'Operations', href: '/journals/operations' },
        { title: operation.name, href: `/journals/operations/${operation.id}` },
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
        description: '',
        operation_id: operation.id,
    });

    const handleSearch = (e: React.FormEvent) => {
        e.preventDefault();
        router.get(
            `/journals/operations/${operation.id}`,
            { search },
            { preserveState: true },
        );
    };

    const handleOpenCreate = () => {
        setEditingId(null);
        reset();
        clearErrors();
        setIsOpen(true);
    };

    const handleOpenEdit = (acc: Account) => {
        setEditingId(acc.id);
        setData({
            name: acc.name,
            description: acc.description || '',
            operation_id: operation.id,
        });
        clearErrors();
        setIsOpen(true);
    };

    const handleSubmit = (e: FormEvent) => {
        e.preventDefault();
        if (editingId) {
            put(`/journals/account-list/${editingId}`, {
                onSuccess: () => setIsOpen(false),
            });
        } else {
            post('/journals/account-list', {
                onSuccess: () => setIsOpen(false),
            });
        }
    };

    const handleDelete = (id: number) => {
        if (confirm('Are you sure you want to delete this account?')) {
            destroy(`/journals/account-list/${id}`);
        }
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`${operation.name} - Accounts`} />

            <div className="p-4 sm:p-6 lg:p-8">
                {/* Header */}
                <div className="mb-8 flex flex-col items-start justify-between gap-4 sm:flex-row sm:items-center">
                    <div className="flex items-center gap-4">
                        <Link href="/journals/operations">
                            <Button
                                variant="outline"
                                size="icon"
                                className="h-10 w-10"
                            >
                                <ArrowLeft className="h-5 w-5" />
                            </Button>
                        </Link>
                        <div>
                            <h2 className="text-2xl font-bold tracking-tight text-zinc-900 dark:text-zinc-50">
                                Operation: {operation.name}
                            </h2>
                            <p className="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                                Account list bound to this operation.
                            </p>
                        </div>
                    </div>
                    <div className="flex w-full items-center gap-2 sm:w-auto">
                        <Button
                            onClick={handleOpenCreate}
                            className="w-full gap-2 bg-blue-600 text-white shadow-sm hover:bg-blue-700 sm:w-auto"
                        >
                            <Plus className="h-4 w-4" />
                            Add Account
                        </Button>
                    </div>
                </div>

                <div className="relative mb-4 max-w-sm">
                    <form onSubmit={handleSearch}>
                        <Search className="absolute top-2.5 left-2.5 h-4 w-4 text-zinc-500" />
                        <Input
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            placeholder="Search accounts in operation..."
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
                                        Account Name
                                    </th>
                                    <th className="px-6 py-4 text-right text-xs font-semibold tracking-wider text-zinc-500 uppercase dark:text-zinc-400">
                                        Actions
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-zinc-200 bg-white dark:divide-zinc-800 dark:bg-zinc-900">
                                {accounts.data.map((acc) => (
                                    <tr
                                        key={acc.id}
                                        onClick={() =>
                                            router.get(
                                                `/journals/account-list/${acc.id}/ledger`,
                                            )
                                        }
                                        className="group cursor-pointer transition-colors hover:bg-zinc-50/50 dark:hover:bg-zinc-800/50"
                                    >
                                        <td className="px-6 py-4 whitespace-nowrap">
                                            <div className="flex items-center gap-3">
                                                <div className="flex h-8 w-8 items-center justify-center rounded-lg bg-green-100 dark:bg-green-900/30">
                                                    <Book className="h-4 w-4 text-green-600 dark:text-green-400" />
                                                </div>
                                                <div>
                                                    <div className="text-sm font-bold text-zinc-900 transition-colors group-hover:text-blue-600 dark:text-white dark:group-hover:text-blue-400">
                                                        {acc.name}
                                                    </div>
                                                    <div className="text-xs text-zinc-500">
                                                        {acc.description ||
                                                            'No description'}
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                        <td className="px-6 py-4 text-right text-sm font-medium whitespace-nowrap">
                                            <div className="flex justify-end gap-2">
                                                <Link
                                                    href={`/journals/account-list/${acc.id}/ledger`}
                                                    onClick={(e) =>
                                                        e.stopPropagation()
                                                    }
                                                >
                                                    <Button
                                                        variant="outline"
                                                        size="sm"
                                                        className="mr-2 h-8 gap-2"
                                                    >
                                                        <Eye className="h-4 w-4" />
                                                        Ledger
                                                    </Button>
                                                </Link>
                                                <Button
                                                    onClick={(e) => {
                                                        e.stopPropagation();
                                                        handleOpenEdit(acc);
                                                    }}
                                                    variant="ghost"
                                                    size="icon"
                                                    className="h-8 w-8 text-zinc-400 hover:text-zinc-900 dark:hover:text-zinc-100"
                                                >
                                                    <FilePen className="h-4 w-4" />
                                                </Button>
                                                <Button
                                                    onClick={(e) => {
                                                        e.stopPropagation();
                                                        handleDelete(acc.id);
                                                    }}
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
                                {accounts.data.length === 0 && (
                                    <tr>
                                        <td
                                            colSpan={2}
                                            className="px-6 py-12 text-center text-sm text-zinc-500 dark:text-zinc-400"
                                        >
                                            No accounts found in this operation
                                            yet.
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                    {/* Pagination */}
                    {accounts.links && accounts.links.length > 3 && (
                        <div className="border-t border-zinc-200 bg-zinc-50/30 px-6 py-4 dark:border-zinc-800 dark:bg-zinc-900/30">
                            <Pagination
                                links={accounts.links}
                                from={accounts.from}
                                to={accounts.to}
                                total={accounts.total}
                                label="accounts"
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
                            {editingId ? 'Edit Account' : 'Add Account'}
                        </DialogTitle>
                    </DialogHeader>
                    <form onSubmit={handleSubmit} className="space-y-4">
                        <div className="space-y-2">
                            <Label htmlFor="name">Account Name</Label>
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
