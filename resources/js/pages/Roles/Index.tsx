import { Head, Link, router } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { BreadcrumbItem } from '@/types';
import roleRoutes from '@/routes/roles';
import {
    FilePen,
    Trash2,
    Plus,
    SlidersHorizontal,
    Search,
    Shield,
} from 'lucide-react';
import { Input } from '@/components/ui/input';
import Pagination from '@/components/Partial/Pagination';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Roles',
        href: '/roles',
    },
];

interface Role {
    id: number;
    name: string;
    permissions: { id: number; name: string }[];
    created_at: string;
}

interface Props {
    roles: {
        data: Role[];
        links: any[];
        from: number;
        to: number;
        total: number;
    };
}

export default function RolesIndex({ roles }: Props) {
    const handleDelete = (id: number) => {
        if (confirm('Are you sure you want to delete this role?')) {
            router.delete(roleRoutes.destroy.url({ role: id }));
        }
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Roles" />

            <div className="p-4 sm:p-6 lg:p-8">
                {/* Header */}
                <div className="mb-8 flex flex-col items-start justify-between gap-4 sm:flex-row sm:items-center">
                    <div>
                        <h2 className="text-2xl font-bold tracking-tight text-zinc-900 dark:text-zinc-50">
                            Roles
                        </h2>
                        <p className="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                            Manage user roles and their associated permissions.
                        </p>
                    </div>
                    <div className="flex w-full items-center gap-2 sm:w-auto">
                        <Button
                            variant="outline"
                            size="sm"
                            className="hidden gap-2 sm:flex"
                        >
                            <SlidersHorizontal className="h-4 w-4" />
                            Filters
                        </Button>
                        <Link
                            href={roleRoutes.create.url()}
                            className="w-full sm:w-auto"
                        >
                            <Button className="w-full gap-2 bg-blue-600 text-white shadow-sm hover:bg-blue-700 sm:w-auto">
                                <Plus className="h-4 w-4" />
                                Create Role
                            </Button>
                        </Link>
                    </div>
                </div>

                {/* Search Bar Placeholder */}
                <div className="relative mb-4 hidden max-w-sm">
                    <Search className="absolute top-2.5 left-2.5 h-4 w-4 text-zinc-500" />
                    <Input
                        placeholder="Search roles..."
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
                                        Role Name
                                    </th>
                                    <th
                                        scope="col"
                                        className="px-6 py-4 text-left text-xs font-semibold tracking-wider text-zinc-500 uppercase dark:text-zinc-400"
                                    >
                                        Permissions Count
                                    </th>
                                    <th
                                        scope="col"
                                        className="px-6 py-4 text-left text-xs font-semibold tracking-wider text-zinc-500 uppercase dark:text-zinc-400"
                                    >
                                        Created At
                                    </th>
                                    <th
                                        scope="col"
                                        className="px-6 py-4 text-right text-xs font-semibold tracking-wider text-zinc-500 uppercase dark:text-zinc-400"
                                    >
                                        Actions
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-zinc-200 bg-white dark:divide-zinc-800 dark:bg-zinc-900">
                                {roles.data.map((role) => (
                                    <tr
                                        key={role.id}
                                        className="group transition-colors hover:bg-zinc-50/50 dark:hover:bg-zinc-800/50"
                                    >
                                        <td className="px-6 py-4 whitespace-nowrap">
                                            <div className="flex items-center gap-3">
                                                <div className="flex h-8 w-8 items-center justify-center rounded-lg bg-zinc-100 dark:bg-zinc-800">
                                                    <Shield className="h-4 w-4 text-zinc-500 dark:text-zinc-400" />
                                                </div>
                                                <span className="text-sm font-bold text-zinc-900 dark:text-white">
                                                    {role.name}
                                                </span>
                                            </div>
                                        </td>
                                        <td className="px-6 py-4 whitespace-nowrap">
                                            <Badge
                                                variant="secondary"
                                                className="border-0 bg-indigo-100 text-indigo-700 dark:bg-indigo-500/10 dark:text-indigo-400"
                                            >
                                                {role.permissions.length}{' '}
                                                permissions
                                            </Badge>
                                        </td>
                                        <td className="px-6 py-4 whitespace-nowrap">
                                            <div className="text-sm text-zinc-500 dark:text-zinc-400">
                                                {new Date(
                                                    role.created_at,
                                                ).toLocaleDateString()}
                                            </div>
                                        </td>
                                        <td className="px-6 py-4 text-right text-sm font-medium whitespace-nowrap">
                                            <div className="flex justify-end gap-2">
                                                <Link
                                                    href={roleRoutes.edit.url({
                                                        role: role.id,
                                                    })}
                                                >
                                                    <Button
                                                        variant="ghost"
                                                        size="icon"
                                                        className="h-8 w-8 text-zinc-400 hover:bg-zinc-100 hover:text-zinc-900 dark:hover:bg-zinc-800 dark:hover:text-zinc-100"
                                                    >
                                                        <FilePen className="h-4 w-4" />
                                                        <span className="sr-only">
                                                            Edit
                                                        </span>
                                                    </Button>
                                                </Link>
                                                {role.name !== 'superadmin' && (
                                                    <Button
                                                        variant="ghost"
                                                        size="icon"
                                                        onClick={() =>
                                                            handleDelete(
                                                                role.id,
                                                            )
                                                        }
                                                        className="h-8 w-8 text-zinc-400 hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-900/20 dark:hover:text-red-400"
                                                    >
                                                        <Trash2 className="h-4 w-4" />
                                                        <span className="sr-only">
                                                            Delete
                                                        </span>
                                                    </Button>
                                                )}
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                                {roles.data.length === 0 && (
                                    <tr>
                                        <td
                                            colSpan={4}
                                            className="px-6 py-12 text-center text-sm text-zinc-500 dark:text-zinc-400"
                                        >
                                            No roles found. Get started by
                                            creating a new role.
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                    {/* Pagination */}
                    <div className="border-t border-zinc-200 bg-zinc-50/30 px-6 py-4 dark:border-zinc-800 dark:bg-zinc-900/30">
                        <Pagination
                            links={roles.links}
                            from={roles.from}
                            to={roles.to}
                            total={roles.total}
                            label="roles"
                        />
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
