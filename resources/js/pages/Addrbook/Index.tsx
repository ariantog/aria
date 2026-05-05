import { Head, Link, router, usePage } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { BreadcrumbItem } from '@/types';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import {
    FilePen,
    Trash2,
    Plus,
    Search,
    MapPin,
    Phone,
    Mail,
    User,
    X,
} from 'lucide-react';
import { Input } from '@/components/ui/input';
import Pagination from '@/components/Partial/Pagination';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Address Book',
        href: '/addrbook',
    },
];

interface Addrbook {
    id: number;
    name: string;
    address: string;
    phone: string;
    email: string;
    contact_person: string;
    is_online: boolean;
    ppn: boolean;
    stat?: {
        balance: number;
    };
    type: number;
    type_name: string;
    type_slug: string;
    created_at: string;
    deleted_at?: string;
}

interface Filter {
    search?: string;
    trashed?: string;
}

interface Props {
    addrbooks: {
        data: Addrbook[];
        links: any[];
        from: number;
        to: number;
        total: number;
    };
    filters: Filter;
    can: {
        create_addrbook: boolean;
    };
    current_type?: string;
    ppn_rate: number;
}

import addrbookRoutes from '@/routes/addrbook';
import FilterAddrbook from '@/components/Partial/Filter/FilterAddrbook';

export default function AddrbookIndex({
    addrbooks,
    can,
    current_type,
    ppn_rate,
    filters,
}: Props) {
    const { props } = usePage();

    const handleDelete = (id: number) => {
        if (confirm('Are you sure you want to delete this entry?')) {
            router.delete(addrbookRoutes.destroy.url(id));
        }
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Address Book" />

            <div className="p-4 sm:p-6 lg:p-8">
                {/* Header Section */}
                <div className="mb-8 flex flex-col items-start justify-between gap-4 sm:flex-row sm:items-center">
                    <div>
                        <h1 className="mb-1 text-3xl font-bold tracking-tight text-zinc-900 dark:text-zinc-50">
                            Address Book
                        </h1>
                        <p className="text-zinc-500 dark:text-zinc-400">
                            Manage your customers and contacts efficiently.
                        </p>
                    </div>
                    <div className="flex items-center gap-3">
                        {can.create_addrbook && (
                            <Link
                                href={
                                    current_type
                                        ? `/${current_type}/create`
                                        : addrbookRoutes.create.url()
                                }
                            >
                                <Button className="bg-blue-600 text-white hover:bg-blue-700">
                                    <Plus className="mr-2 h-4 w-4" /> Add New
                                </Button>
                            </Link>
                        )}
                    </div>
                </div>

                <FilterAddrbook
                    baseUrl={current_type ? `/${current_type}` : '/addrbook'}
                    filters={filters}
                />

                {/* Table Section */}
                <div className="overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-800 dark:bg-zinc-900/50">
                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-sm">
                            <thead className="border-b border-zinc-200 bg-zinc-50 text-xs text-zinc-500 uppercase dark:border-zinc-800 dark:bg-zinc-900/50">
                                <tr>
                                    <th className="px-6 py-4 font-bold tracking-wider">
                                        Name / ID
                                    </th>
                                    <th className="px-6 py-4 font-bold tracking-wider">
                                        Contact Info
                                    </th>
                                    <th className="px-6 py-4 text-right font-bold tracking-wider text-zinc-900 dark:text-zinc-100">
                                        Connectivity
                                    </th>
                                    <th className="px-6 py-4 text-right font-bold tracking-wider text-zinc-900 italic dark:text-zinc-100">
                                        Balance
                                    </th>
                                    <th className="px-6 py-4 text-right font-bold tracking-wider text-zinc-900 italic dark:text-zinc-100">
                                        Actions
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-zinc-200 dark:divide-zinc-800/50">
                                {addrbooks.data.length > 0 ? (
                                    addrbooks.data.map((addrbook) => (
                                        <tr
                                            key={addrbook.id}
                                            className="group transition-colors hover:bg-zinc-50 dark:hover:bg-zinc-800/40"
                                        >
                                            <td className="px-6 py-4">
                                                <div className="flex flex-col">
                                                    <Link
                                                        href={`/${addrbook.type_slug}/${addrbook.id}`}
                                                        className="font-semibold text-blue-600 transition-colors hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300"
                                                    >
                                                        <div>
                                                            {addrbook.name}
                                                        </div>
                                                        <div className="flex items-center gap-2">
                                                            <div className="text-xs opacity-80">
                                                                ID:{' '}
                                                                {addrbook.id}
                                                            </div>
                                                            {addrbook.deleted_at && (
                                                                <Badge
                                                                    variant="destructive"
                                                                    className="h-4 px-1 text-[10px] font-bold uppercase"
                                                                >
                                                                    Deleted
                                                                </Badge>
                                                            )}
                                                        </div>
                                                    </Link>
                                                    {addrbook.contact_person && (
                                                        <div className="mt-1 flex items-center gap-1 text-xs text-zinc-500">
                                                            <User className="h-3 w-3" />{' '}
                                                            {
                                                                addrbook.contact_person
                                                            }
                                                        </div>
                                                    )}
                                                </div>
                                            </td>
                                            <td className="px-6 py-4">
                                                <div className="flex flex-col gap-1 text-zinc-500 dark:text-zinc-400">
                                                    {addrbook.phone && (
                                                        <div className="flex items-center gap-2">
                                                            <Phone className="h-3 w-3" />{' '}
                                                            {addrbook.phone}
                                                        </div>
                                                    )}
                                                    {addrbook.email && (
                                                        <div className="flex items-center gap-2">
                                                            <Mail className="h-3 w-3" />{' '}
                                                            {addrbook.email}
                                                        </div>
                                                    )}
                                                    {addrbook.address && (
                                                        <div
                                                            className="flex max-w-xs items-center gap-2 truncate"
                                                            title={
                                                                addrbook.address
                                                            }
                                                        >
                                                            <MapPin className="h-3 w-3 flex-shrink-0" />{' '}
                                                            <span className="truncate">
                                                                {
                                                                    addrbook.address
                                                                }
                                                            </span>
                                                        </div>
                                                    )}
                                                </div>
                                            </td>
                                            <td className="px-6 py-4 text-right">
                                                <div className="flex flex-col items-end gap-2">
                                                    <div className="flex items-center gap-2">
                                                        <span
                                                            className={`h-2 w-2 rounded-full ${addrbook.is_online ? 'bg-emerald-500' : 'bg-zinc-300 dark:bg-zinc-700'}`}
                                                        ></span>
                                                        <span className="font-medium text-zinc-700 dark:text-zinc-300">
                                                            {addrbook.is_online
                                                                ? 'Online'
                                                                : 'Offline'}
                                                        </span>
                                                    </div>
                                                    {addrbook.ppn && (
                                                        <Badge
                                                            variant="secondary"
                                                            className="bg-purple-100 text-purple-700 dark:bg-purple-500/10 dark:text-purple-400"
                                                        >
                                                            PPN {ppn_rate}%
                                                        </Badge>
                                                    )}
                                                </div>
                                            </td>
                                            <td className="px-6 py-4 text-right font-medium text-zinc-900 dark:text-zinc-100">
                                                {new Intl.NumberFormat(
                                                    'id-ID',
                                                    {
                                                        style: 'currency',
                                                        currency: 'IDR',
                                                    },
                                                ).format(
                                                    addrbook.stat?.balance || 0,
                                                )}
                                            </td>
                                            <td className="px-6 py-4 text-right">
                                                <div className="flex justify-end gap-2">
                                                    <Link
                                                        href={`/${addrbook.type_slug}/${addrbook.id}/edit`}
                                                        className="inline-flex items-center justify-center rounded-md p-2 text-zinc-400 hover:bg-blue-50 hover:text-blue-500 dark:hover:bg-blue-900/20"
                                                    >
                                                        <FilePen className="h-4 w-4" />
                                                    </Link>
                                                    <button
                                                        onClick={() =>
                                                            handleDelete(
                                                                addrbook.id,
                                                            )
                                                        }
                                                        className="inline-flex items-center justify-center rounded-md p-2 text-zinc-400 hover:bg-red-50 hover:text-red-500 dark:hover:bg-red-900/20"
                                                    >
                                                        <Trash2 className="h-4 w-4" />
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    ))
                                ) : (
                                    <tr>
                                        <td
                                            colSpan={5}
                                            className="px-6 py-12 text-center text-zinc-500"
                                        >
                                            No address book entries found.
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                    {/* Pagination */}
                    <div className="border-t border-zinc-200 px-6 py-4 dark:border-zinc-800">
                        <Pagination
                            links={addrbooks.links}
                            from={addrbooks.from}
                            to={addrbooks.to}
                            total={addrbooks.total}
                            label="entries"
                        />
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
