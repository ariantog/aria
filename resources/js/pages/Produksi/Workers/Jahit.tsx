import React, { useState, FormEvent } from 'react';
import { Head, useForm } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogFooter,
} from '@/components/ui/dialog';
import { Plus, Edit, Trash2 } from 'lucide-react';
import { type BreadcrumbItem } from '@/types';

interface Worker {
    id: number;
    name: string;
    type: string;
}

interface WorkersProps {
    workers: {
        data: Worker[];
        links: any[]; // Pagination links
    };
}

export default function WorkersIndex({ workers }: WorkersProps) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Produksi', href: '/produksi' },
        { title: 'Jahit Workers', href: '/produksi/jahit/list' },
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
    });

    const [isOpen, setIsOpen] = useState(false);
    const [editingId, setEditingId] = useState<number | null>(null);

    const openCreateModal = () => {
        setEditingId(null);
        reset();
        clearErrors();
        setIsOpen(true);
    };

    const openEditModal = (worker: Worker) => {
        setEditingId(worker.id);
        setData({ name: worker.name });
        clearErrors();
        setIsOpen(true);
    };

    const handleSubmit = (e: FormEvent) => {
        e.preventDefault();
        if (editingId) {
            put(`/produksi/jahit/${editingId}`, {
                onSuccess: () => setIsOpen(false),
            });
        } else {
            post('/produksi/jahit/store', {
                onSuccess: () => setIsOpen(false),
            });
        }
    };

    const handleDelete = (id: number) => {
        if (confirm('Are you sure you want to delete this worker?')) {
            destroy(`/produksi/jahit/${id}/delete`);
        }
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Jahit Workers" />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <div className="mb-4 flex items-center justify-between">
                    <div>
                        <h2 className="text-2xl font-bold tracking-tight">
                            Jahit Workers
                        </h2>
                        <p className="text-muted-foreground">
                            Manage your sewing team members.
                        </p>
                    </div>
                    <Button
                        onClick={openCreateModal}
                        className="gap-2 bg-blue-600 text-white hover:bg-blue-700"
                    >
                        <Plus className="h-4 w-4" />
                        Add Worker
                    </Button>
                </div>

                <div className="rounded-md border bg-white dark:bg-zinc-950">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead className="w-[100px]">ID</TableHead>
                                <TableHead>Name</TableHead>
                                <TableHead className="text-right">
                                    Actions
                                </TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {workers.data.length > 0 ? (
                                workers.data.map((worker) => (
                                    <TableRow key={worker.id}>
                                        <TableCell className="font-medium">
                                            {worker.id}
                                        </TableCell>
                                        <TableCell>{worker.name}</TableCell>
                                        <TableCell className="text-right">
                                            <div className="flex justify-end gap-2">
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    onClick={() =>
                                                        openEditModal(worker)
                                                    }
                                                    className="text-blue-600 hover:bg-blue-50 hover:text-blue-700 dark:hover:bg-blue-900/50"
                                                >
                                                    <Edit className="h-4 w-4" />
                                                </Button>
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    onClick={() =>
                                                        handleDelete(worker.id)
                                                    }
                                                    className="text-red-600 hover:bg-red-50 hover:text-red-700 dark:hover:bg-red-900/50"
                                                >
                                                    <Trash2 className="h-4 w-4" />
                                                </Button>
                                            </div>
                                        </TableCell>
                                    </TableRow>
                                ))
                            ) : (
                                <TableRow>
                                    <TableCell
                                        colSpan={3}
                                        className="h-24 text-center text-muted-foreground"
                                    >
                                        No workers found.
                                    </TableCell>
                                </TableRow>
                            )}
                        </TableBody>
                    </Table>
                </div>

                {/* Optional: Add basic pagination if workers.links are available */}
                <div className="mt-4 flex items-center justify-between text-sm text-muted-foreground">
                    {workers.data.length > 0 && (
                        <div>Showing {workers.data.length} workers</div>
                    )}
                </div>
            </div>

            <Dialog open={isOpen} onOpenChange={setIsOpen}>
                <DialogContent className="sm:max-w-[425px]">
                    <DialogHeader>
                        <DialogTitle>
                            {editingId ? 'Edit Worker' : 'Add Worker'}
                        </DialogTitle>
                    </DialogHeader>
                    <form onSubmit={handleSubmit} className="space-y-4">
                        <div className="grid gap-2">
                            <Label htmlFor="name">Name</Label>
                            <Input
                                id="name"
                                value={data.name}
                                onChange={(e) =>
                                    setData('name', e.target.value)
                                }
                                placeholder="Worker's name"
                                autoFocus
                            />
                            {errors.name && (
                                <p className="text-sm text-red-500">
                                    {errors.name}
                                </p>
                            )}
                        </div>
                        <DialogFooter>
                            <Button
                                type="button"
                                variant="ghost"
                                onClick={() => setIsOpen(false)}
                            >
                                Cancel
                            </Button>
                            <Button
                                type="submit"
                                disabled={processing}
                                className="bg-blue-600 text-white hover:bg-blue-700"
                            >
                                {editingId ? 'Save changes' : 'Add worker'}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </AppLayout>
    );
}
