
import { Head, Link, router } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { BreadcrumbItem } from '@/types';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import userRoutes from '@/routes/users';
import { FilePen, Trash2, Plus, SlidersHorizontal, Search } from 'lucide-react';
import { Input } from '@/components/ui/input';
import Pagination from '@/components/Partial/Pagination';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Users',
        href: '/users',
    },
];

interface Role {
    id: number;
    name: string;
}

interface User {
    id: number;
    name: string;
    username: string;
    email: string;
    roles: Role[];
    is_active: boolean;
    created_at: string;
}

interface Props {
    users: {
        data: User[];
        links: any[];
        from: number;
        to: number;
        total: number;
    };
    can: {
        create_user: boolean;
    };
}

export default function UsersIndex({ users, can }: Props) {


    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Users" />

            <div className=" p-4 sm:p-6 lg:p-8">
                {/* Header */}
                <div className="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-8 gap-4">
                    <div>
                        <h2 className="text-2xl font-bold tracking-tight text-zinc-900 dark:text-zinc-50">Users</h2>
                        <p className="mt-1 text-sm text-zinc-500 dark:text-zinc-400">Manage your team members and their account permissions.</p>
                    </div>
                    <div className="flex items-center gap-2 w-full sm:w-auto">
                        <Button variant="outline" size="sm" className="hidden sm:flex gap-2">
                            <SlidersHorizontal className="h-4 w-4" />
                            Filters
                        </Button>
                        {can.create_user && (
                            <Link href={userRoutes.create.url()} className="w-full sm:w-auto">
                                <Button className="w-full sm:w-auto bg-blue-600 hover:bg-blue-700 text-white shadow-sm gap-2">
                                    <Plus className="h-4 w-4" />
                                    Add User
                                </Button>
                            </Link>
                        )}
                    </div>
                </div>

                {/* Search Bar (Optional, for now just visual placeholder or functional if user wants later) */}
                <div className="mb-4 relative max-w-sm hidden">
                    <Search className="absolute left-2.5 top-2.5 h-4 w-4 text-zinc-500" />
                    <Input placeholder="Search by name, username, or role..." className="pl-9 bg-zinc-50 dark:bg-zinc-900 border-zinc-200 dark:border-zinc-800" />
                </div>

                {/* Table Card */}
                <div className="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-xl shadow-sm overflow-hidden">
                    <div className="overflow-x-auto">
                        <table className="min-w-full divide-y divide-zinc-200 dark:divide-zinc-800">
                            <thead className="bg-zinc-50/50 dark:bg-zinc-900/50">
                                <tr>
                                    <th scope="col" className="px-6 py-4 text-left text-xs font-semibold text-zinc-500 dark:text-zinc-400 uppercase tracking-wider">Name</th>
                                    <th scope="col" className="px-6 py-4 text-left text-xs font-semibold text-zinc-500 dark:text-zinc-400 uppercase tracking-wider">Username</th>
                                    <th scope="col" className="px-6 py-4 text-left text-xs font-semibold text-zinc-500 dark:text-zinc-400 uppercase tracking-wider">Email</th>
                                    <th scope="col" className="px-6 py-4 text-left text-xs font-semibold text-zinc-500 dark:text-zinc-400 uppercase tracking-wider">Role</th>
                                    <th scope="col" className="px-6 py-4 text-left text-xs font-semibold text-zinc-500 dark:text-zinc-400 uppercase tracking-wider">Status</th>
                                    <th scope="col" className="px-6 py-4 text-left text-xs font-semibold text-zinc-500 dark:text-zinc-400 uppercase tracking-wider">Joined</th>
                                    <th scope="col" className="px-6 py-4 text-right text-xs font-semibold text-zinc-500 dark:text-zinc-400 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-zinc-200 dark:divide-zinc-800 bg-white dark:bg-zinc-900">
                                {users.data.map((user) => (
                                    <tr key={user.id} className="group hover:bg-zinc-50/50 dark:hover:bg-zinc-800/50 transition-colors">
                                        <td className="px-6 py-4 whitespace-nowrap">
                                            <div className="text-sm font-bold text-zinc-900 dark:text-white">{user.name}</div>
                                        </td>
                                        <td className="px-6 py-4 whitespace-nowrap">
                                            <div className="text-sm text-zinc-500 dark:text-zinc-400">@{user.username}</div>
                                        </td>
                                        <td className="px-6 py-4 whitespace-nowrap">
                                            <div className="text-sm text-zinc-500 dark:text-zinc-400">{user.email}</div>
                                        </td>
                                        <td className="px-6 py-4 whitespace-nowrap">
                                            <div className="flex flex-wrap gap-1">
                                                {user.roles.map(role => (
                                                    <Badge key={role.id} variant="secondary" className="bg-indigo-100 text-indigo-700 dark:bg-indigo-500/10 dark:text-indigo-400 hover:bg-indigo-200 dark:hover:bg-indigo-500/20 border-0">
                                                        {role.name}
                                                    </Badge>
                                                ))}
                                                {user.roles.length === 0 && <span className="text-xs text-zinc-400 italic">No roles</span>}
                                            </div>
                                        </td>
                                        <td className="px-6 py-4 whitespace-nowrap">
                                            <div className="flex items-center gap-2">
                                                <span className={`h-2 w-2 rounded-full ${user.is_active ? 'bg-emerald-500' : 'bg-red-500'}`}></span>
                                                <span className="text-sm text-zinc-600 dark:text-zinc-300">
                                                    {user.is_active ? 'Active' : 'Banned'}
                                                </span>
                                            </div>
                                        </td>
                                        <td className="px-6 py-4 whitespace-nowrap">
                                            {/* Formatted date placeholder, realistically use date-fns or similar in real app */}
                                            <div className="text-sm text-zinc-500 dark:text-zinc-400">
                                                {new Date(user.created_at).toLocaleDateString()}
                                            </div>
                                        </td>
                                        <td className="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                            <div className="flex justify-end gap-2">
                                                <Link href={userRoutes.edit.url({ user: user.id })}>
                                                    <Button variant="ghost" size="icon" className="h-8 w-8 text-zinc-400 hover:text-zinc-900 dark:hover:text-zinc-100 hover:bg-zinc-100 dark:hover:bg-zinc-800">
                                                        <FilePen className="h-4 w-4" />
                                                        <span className="sr-only">Edit</span>
                                                    </Button>
                                                </Link>
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                                {users.data.length === 0 && (
                                    <tr>
                                        <td colSpan={7} className="px-6 py-12 text-center text-sm text-zinc-500 dark:text-zinc-400">
                                            No users found. Get started by adding a new user.
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                    {/* Pagination */}
                    <div className="bg-zinc-50/30 dark:bg-zinc-900/30 px-6 py-4 border-t border-zinc-200 dark:border-zinc-800">
                        <Pagination
                            links={users.links}
                            from={users.from}
                            to={users.to}
                            total={users.total}
                            label="users"
                        />
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
