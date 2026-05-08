import { Head, useForm } from '@inertiajs/react';
import { Plus, Edit, Trash2, Users as UsersIcon } from 'lucide-react';
import type { FormEvent } from 'react';
import React, { useState } from 'react';
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
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';

interface Worker {
    id: number;
    name: string;
    type: number | string;
    created_at: string;
}

interface WorkersProps {
    workers: {
        data: Worker[];
        links: any[];
        from: number;
        to: number;
        total: number;
    };
    type: string;
    title: string;
    can: {
        create_worker: boolean;
        edit_worker: boolean;
        delete_worker: boolean;
    };
}

export default function WorkersIndex({
    workers,
    type,
    title,
    can,
}: WorkersProps) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Produksi', href: '/produksi' },
        { title: title, href: `/produksi/${type}/list` },
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
            put(`/produksi/${type}/${editingId}`, {
                onSuccess: () => setIsOpen(false),
            });
        } else {
            post(`/produksi/${type}/store`, {
                onSuccess: () => setIsOpen(false),
            });
        }
    };

    const handleDelete = (id: number) => {
        if (confirm(`Are you sure you want to delete this ${type} worker?`)) {
            destroy(`/produksi/${type}/${id}/delete`);
        }
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={title} />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <div className="mb-4 flex items-center justify-between">
                    <div>
                        <h2 className="text-2xl font-bold tracking-tight">
                            {title}
                        </h2>
                        <p className="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                            Total {workers.total} workers found
                        </p>
                    </div>
                    {can.create_worker && (
                        <Button
                            onClick={openCreateModal}
                            className="flex items-center gap-2 bg-blue-600 text-white shadow-sm hover:bg-blue-700"
                        >
                            <Plus className="h-4 w-4" />
                            Add New Worker
                        </Button>
                    )}
                </div>

                <div className="overflow-hidden rounded-xl border bg-white shadow-sm dark:bg-zinc-900">
                    <Table>
                        <TableHeader className="bg-zinc-50/50 dark:bg-zinc-900/50">
                            <TableRow>
                                <TableHead className="w-[100px]">ID</TableHead>
                                <TableHead>Name</TableHead>
                                <TableHead>Joined At</TableHead>
                                <TableHead className="text-right">
                                    Actions
                                </TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {workers.data.length > 0 ? (
                                workers.data.map((worker) => (
                                    <TableRow
                                        key={worker.id}
                                        className="group transition-colors hover:bg-zinc-50/50 dark:hover:bg-zinc-800/50"
                                    >
                                        <TableCell className="font-medium">
                                            {worker.id}
                                        </TableCell>
                                        <TableCell>
                                            <div className="flex items-center gap-3">
                                                <div className="flex h-8 w-8 items-center justify-center rounded-lg bg-blue-100 dark:bg-blue-900/30">
                                                    <UsersIcon className="h-4 w-4 text-blue-600 dark:text-blue-400" />
                                                </div>
                                                <div className="font-bold text-zinc-900 dark:text-white">
                                                    {worker.name}
                                                </div>
                                            </div>
                                        </TableCell>
                                        <TableCell className="text-muted-foreground">
                                            {new Date(
                                                worker.created_at,
                                            ).toLocaleDateString()}
                                        </TableCell>
                                        <TableCell className="text-right">
                                            <div className="flex justify-end gap-2">
                                                {can.edit_worker && (
                                                    <Button
                                                        variant="outline"
                                                        size="icon"
                                                        className="h-8 w-8 border-blue-100 text-blue-600 hover:bg-blue-50"
                                                        onClick={() =>
                                                            openEditModal(
                                                                worker,
                                                            )
                                                        }
                                                    >
                                                        <Edit className="h-4 w-4" />
                                                    </Button>
                                                )}
                                                {can.delete_worker && (
                                                    <Button
                                                        variant="outline"
                                                        size="icon"
                                                        className="h-8 w-8 border-red-100 text-red-600 hover:bg-red-50"
                                                        onClick={() =>
                                                            handleDelete(
                                                                worker.id,
                                                            )
                                                        }
                                                    >
                                                        <Trash2 className="h-4 w-4" />
                                                    </Button>
                                                )}
                                            </div>
                                        </TableCell>
                                    </TableRow>
                                ))
                            ) : (
                                <TableRow>
                                    <TableCell
                                        colSpan={4}
                                        className="h-24 text-center text-muted-foreground"
                                    >
                                        No workers found.
                                    </TableCell>
                                </TableRow>
                            )}
                        </TableBody>
                    </Table>
                </div>

                {workers.links && workers.links.length > 3 && (
                    <div className="mt-4">
                        <Pagination
                            links={workers.links}
                            from={workers.from}
                            to={workers.to}
                            total={workers.total}
                            label="workers"
                        />
                    </div>
                )}
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
                                required
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
                                variant="outline"
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
