import { Head, Link, router, useForm } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { BreadcrumbItem } from '@/types';
import { Button } from '@/components/ui/button';
import { FilePen, Trash2, Plus, Search, Book, Eye } from 'lucide-react';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter } from '@/components/ui/dialog';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import Pagination from '@/components/Partial/Pagination';
import { useState, FormEvent } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Journals', href: '/journals/operations' },
    { title: 'Account List', href: '/journals/account-list' },
];

interface Account {
    id: number;
    name: string;
    description: string | null;
    operation_id: number;
    operation?: { id: number; name: string };
    created_at: string;
}

interface Operation {
    id: number;
    name: string;
}

interface Props {
    accounts: {
        data: Account[];
        links: any[];
        from: number;
        to: number;
        total: number;
    };
    operations: Operation[];
}

export default function AccountListIndex({ accounts, operations }: Props) {
    const [search, setSearch] = useState('');
    const [filterOp, setFilterOp] = useState<string>('all');
    const [isOpen, setIsOpen] = useState(false);
    const [editingId, setEditingId] = useState<number | null>(null);

    const { data, setData, post, put, delete: destroy, processing, errors, reset, clearErrors } = useForm({
        name: '',
        description: '',
        operation_id: '',
    });

    const handleSearch = (e: React.FormEvent) => {
        e.preventDefault();
        router.get('/journals/account-list', {
            search,
            operation_id: filterOp === 'all' ? '' : filterOp
        }, { preserveState: true });
    };

    const handleFilterChange = (value: string) => {
        setFilterOp(value);
        router.get('/journals/account-list', {
            search,
            operation_id: value === 'all' ? '' : value
        }, { preserveState: true });
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
            operation_id: acc.operation_id?.toString() || '',
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
            <Head title="Account List (Buku Besar)" />

            <div className="p-4 sm:p-6 lg:p-8">
                {/* Header */}
                <div className="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-8 gap-4">
                    <div>
                        <h2 className="text-2xl font-bold tracking-tight text-zinc-900 dark:text-zinc-50">Account List</h2>
                        <p className="mt-1 text-sm text-zinc-500 dark:text-zinc-400">Manage ledger accounts and view their journals.</p>
                    </div>
                    <div className="flex items-center gap-2 w-full sm:w-auto">
                        <Button onClick={handleOpenCreate} className="w-full sm:w-auto bg-blue-600 hover:bg-blue-700 text-white shadow-sm gap-2">
                            <Plus className="h-4 w-4" />
                            Add Account
                        </Button>
                    </div>
                </div>

                <div className="mb-4 flex flex-col sm:flex-row gap-4 items-center max-w-2xl">
                    <form onSubmit={handleSearch} className="relative w-full sm:max-w-sm">
                        <Search className="absolute left-2.5 top-2.5 h-4 w-4 text-zinc-500" />
                        <Input
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            placeholder="Search accounts..."
                            className="pl-9 bg-zinc-50 dark:bg-zinc-900"
                        />
                    </form>

                    <div className="w-full sm:w-64">
                        <Select value={filterOp} onValueChange={handleFilterChange}>
                            <SelectTrigger className="bg-white dark:bg-zinc-900">
                                <SelectValue placeholder="Filter by Operation" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">All Operations</SelectItem>
                                {operations.map(op => (
                                    <SelectItem key={op.id} value={op.id.toString()}>{op.name}</SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>
                </div>

                {/* Table Card */}
                <div className="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-xl shadow-sm overflow-hidden">
                    <div className="overflow-x-auto">
                        <table className="min-w-full divide-y divide-zinc-200 dark:divide-zinc-800">
                            <thead className="bg-zinc-50/50 dark:bg-zinc-900/50">
                                <tr>
                                    <th className="px-6 py-4 text-left text-xs font-semibold text-zinc-500 dark:text-zinc-400 uppercase tracking-wider">Account Name</th>
                                    <th className="px-6 py-4 text-left text-xs font-semibold text-zinc-500 dark:text-zinc-400 uppercase tracking-wider">Operation</th>
                                    <th className="px-6 py-4 text-right text-xs font-semibold text-zinc-500 dark:text-zinc-400 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-zinc-200 dark:divide-zinc-800 bg-white dark:bg-zinc-900">
                                {accounts.data.map((acc) => (
                                    <tr
                                        key={acc.id}
                                        onClick={() => router.get(`/journals/account-list/${acc.id}/ledger`)}
                                        className="group hover:bg-zinc-50/50 dark:hover:bg-zinc-800/50 transition-colors cursor-pointer"
                                    >
                                        <td className="px-6 py-4 whitespace-nowrap">
                                            <div className="flex items-center gap-3">
                                                <div className="h-8 w-8 rounded-lg bg-green-100 dark:bg-green-900/30 flex items-center justify-center">
                                                    <Book className="h-4 w-4 text-green-600 dark:text-green-400" />
                                                </div>
                                                <div>
                                                    <div className="text-sm font-bold text-zinc-900 group-hover:text-blue-600 transition-colors dark:text-white dark:group-hover:text-blue-400">
                                                        {acc.name}
                                                    </div>
                                                    <div className="text-xs text-zinc-500">{acc.description || 'No description'}</div>
                                                </div>
                                            </div>
                                        </td>
                                        <td className="px-6 py-4 whitespace-nowrap">
                                            <div className="text-sm text-zinc-600 dark:text-zinc-400 font-medium">
                                                {acc.operation?.name || <span className="text-red-400">Uncategorized</span>}
                                            </div>
                                        </td>
                                        <td className="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                            <div className="flex justify-end gap-2">
                                                <Link href={`/journals/account-list/${acc.id}/ledger`} onClick={(e) => e.stopPropagation()}>
                                                    <Button variant="outline" size="sm" className="h-8 gap-2 mr-2">
                                                        <Eye className="h-4 w-4" />
                                                        Ledger
                                                    </Button>
                                                </Link>
                                                <Button onClick={(e) => { e.stopPropagation(); handleOpenEdit(acc); }} variant="ghost" size="icon" className="h-8 w-8 text-zinc-400 hover:text-zinc-900 dark:hover:text-zinc-100">
                                                    <FilePen className="h-4 w-4" />
                                                </Button>
                                                <Button onClick={(e) => { e.stopPropagation(); handleDelete(acc.id); }} variant="ghost" size="icon" className="h-8 w-8 text-zinc-400 hover:text-red-600 dark:hover:text-red-400">
                                                    <Trash2 className="h-4 w-4" />
                                                </Button>
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                                {accounts.data.length === 0 && (
                                    <tr>
                                        <td colSpan={3} className="px-6 py-12 text-center text-sm text-zinc-500 dark:text-zinc-400">
                                            No accounts found. Create one to start recording journals.
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                    {/* Pagination */}
                    {accounts.links && accounts.links.length > 3 && (
                        <div className="bg-zinc-50/30 dark:bg-zinc-900/30 px-6 py-4 border-t border-zinc-200 dark:border-zinc-800">
                            <Pagination links={accounts.links} from={accounts.from} to={accounts.to} total={accounts.total} label="accounts" />
                        </div>
                    )}
                </div>
            </div>

            {/* Create/Edit Dialog */}
            <Dialog open={isOpen} onOpenChange={setIsOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>{editingId ? 'Edit Account' : 'Add Account'}</DialogTitle>
                    </DialogHeader>
                    <form onSubmit={handleSubmit} className="space-y-4">
                        <div className="space-y-2">
                            <Label htmlFor="operation_id">Operation Category</Label>
                            <Select
                                value={data.operation_id}
                                onValueChange={(val) => setData('operation_id', val)}
                                required
                            >
                                <SelectTrigger>
                                    <SelectValue placeholder="Select Operation..." />
                                </SelectTrigger>
                                <SelectContent>
                                    {operations.map(op => (
                                        <SelectItem key={op.id} value={op.id.toString()}>{op.name}</SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            {errors.operation_id && <p className="text-sm text-red-500">{errors.operation_id}</p>}
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="name">Account Name</Label>
                            <Input id="name" value={data.name} onChange={(e) => setData('name', e.target.value)} required />
                            {errors.name && <p className="text-sm text-red-500">{errors.name}</p>}
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="description">Description</Label>
                            <Input id="description" value={data.description} onChange={(e) => setData('description', e.target.value)} />
                            {errors.description && <p className="text-sm text-red-500">{errors.description}</p>}
                        </div>
                        <DialogFooter>
                            <Button type="button" variant="outline" onClick={() => setIsOpen(false)}>Cancel</Button>
                            <Button type="submit" disabled={processing}>{editingId ? 'Save Changes' : 'Create'}</Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </AppLayout>
    );
}
