import { Head, useForm, usePage } from '@inertiajs/react';
import { Link } from '@inertiajs/react';
import { Lock, Search, Plus, Zap } from 'lucide-react';
import Pagination from '@/components/Partial/Pagination';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import permissionRoutes from '@/routes/permissions';
import type { BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Permissions',
        href: '/permissions',
    },
];

interface Permission {
    id: number;
    name: string;
    guard_name: string;
    created_at: string;
}

interface Props {
    permissions: {
        data: Permission[];
        links: any[];
        from: number;
        to: number;
        total: number;
    };
}

export default function PermissionsIndex({ permissions }: Props) {
    const { post, processing, reset } = useForm();

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        post(permissionRoutes.generate.url(), {
            onSuccess: () => reset(),
        });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Permissions" />

            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                {/* Header */}
                <div className="mb-8 flex flex-col items-start justify-between gap-4 sm:flex-row sm:items-center">
                    <div>
                        <h2 className="text-2xl font-bold tracking-tight text-zinc-900 dark:text-zinc-50">
                            Permissions
                        </h2>
                        <p className="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                            View and generate system permissions.
                        </p>
                    </div>
                </div>

                {/* Generator Card */}
                <div className="mb-8 rounded-xl border border-zinc-800 bg-zinc-900 p-6 shadow-sm">
                    <div className="flex items-start gap-4">
                        <div className="rounded-lg bg-blue-500/10 p-2">
                            <Zap className="h-6 w-6 text-blue-500" />
                        </div>
                        <div className="flex-1">
                            <h3 className="mb-1 text-lg font-semibold text-white">
                                Permission Generator
                            </h3>
                            <p className="mb-4 text-sm text-zinc-400">
                                Automatically generate permissions defined in
                                your models.
                            </p>

                            <form
                                onSubmit={submit}
                                className="flex flex-col items-end gap-4 sm:flex-row sm:items-start"
                            >
                                <div className="flex gap-2">
                                    <Button
                                        type="submit"
                                        loading={processing}
                                        className="bg-blue-600 whitespace-nowrap text-white shadow-sm hover:bg-blue-700"
                                    >
                                        <Plus className="mr-2 h-4 w-4" />
                                        Generate All Permissions
                                    </Button>
                                </div>
                            </form>
                            <div className="mt-4 flex flex-wrap gap-2 text-zinc-400">
                                <span className="text-xs text-zinc-500">
                                    This will scan models for a
                                    <code>getPermissions()</code> method.
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                {/* Search Bar Placeholder */}
                <div className="relative mb-4 hidden max-w-sm">
                    <Search className="absolute top-2.5 left-2.5 h-4 w-4 text-zinc-500" />
                    <Input
                        placeholder="Search permissions..."
                        className="border-zinc-200 bg-zinc-50 pl-9 dark:border-zinc-800 dark:bg-zinc-900"
                    />
                </div>

                {/* Table Card */}
                <div className="overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                    <div className="overflow-x-auto">
                        <table className="min-w-full divide-y divide-zinc-200 dark:divide-zinc-800">
                            <thead className="bg-zinc-50/50 dark:bg-zinc-900/50">
                                <tr>
                                    <th
                                        scope="col"
                                        className="px-6 py-4 text-left text-xs font-semibold tracking-wider text-zinc-500 uppercase dark:text-zinc-400"
                                    >
                                        ID
                                    </th>
                                    <th
                                        scope="col"
                                        className="px-6 py-4 text-left text-xs font-semibold tracking-wider text-zinc-500 uppercase dark:text-zinc-400"
                                    >
                                        Permission Name
                                    </th>
                                    <th
                                        scope="col"
                                        className="px-6 py-4 text-left text-xs font-semibold tracking-wider text-zinc-500 uppercase dark:text-zinc-400"
                                    >
                                        Guard
                                    </th>
                                    <th
                                        scope="col"
                                        className="px-6 py-4 text-left text-xs font-semibold tracking-wider text-zinc-500 uppercase dark:text-zinc-400"
                                    >
                                        Created At
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-zinc-200 bg-white dark:divide-zinc-800 dark:bg-zinc-900">
                                {permissions.data.map((permission) => (
                                    <tr
                                        key={permission.id}
                                        className="group transition-colors hover:bg-zinc-50/50 dark:hover:bg-zinc-800/50"
                                    >
                                        <td className="px-6 py-4 text-sm whitespace-nowrap text-zinc-500 dark:text-zinc-400">
                                            #{permission.id}
                                        </td>
                                        <td className="px-6 py-4 whitespace-nowrap">
                                            <div className="flex items-center gap-3">
                                                <div className="flex h-8 w-8 items-center justify-center rounded-lg bg-zinc-100 dark:bg-zinc-800">
                                                    <Lock className="h-4 w-4 text-zinc-500 dark:text-zinc-400" />
                                                </div>
                                                <span className="text-sm font-bold text-zinc-900 dark:text-white">
                                                    {permission.name}
                                                </span>
                                            </div>
                                        </td>
                                        <td className="px-6 py-4 whitespace-nowrap">
                                            <Badge
                                                variant="secondary"
                                                className="border-0 bg-zinc-100 font-mono text-xs text-zinc-700 dark:bg-zinc-800 dark:text-zinc-300"
                                            >
                                                {permission.guard_name}
                                            </Badge>
                                        </td>
                                        <td className="px-6 py-4 whitespace-nowrap">
                                            <div className="text-sm text-zinc-500 dark:text-zinc-400">
                                                {new Date(
                                                    permission.created_at,
                                                ).toLocaleDateString()}
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                                {permissions.data.length === 0 && (
                                    <tr>
                                        <td
                                            colSpan={4}
                                            className="px-6 py-12 text-center text-sm text-zinc-500 dark:text-zinc-400"
                                        >
                                            No permissions found. Generate some
                                            using the form above.
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                    {/* Pagination */}
                    <div className="border-t border-zinc-200 bg-zinc-50/30 px-6 py-4 dark:border-zinc-800 dark:bg-zinc-900/30">
                        <Pagination
                            links={permissions.links}
                            from={permissions.from}
                            to={permissions.to}
                            total={permissions.total}
                            label="permissions"
                        />
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
