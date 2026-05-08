import { Head, Link, router, usePage } from '@inertiajs/react';
import { FilePen, Trash2, Plus, Phone, User, ExternalLink } from 'lucide-react';
import FilterAddrbook from '@/components/Partial/Filter/FilterAddrbook';
import Pagination from '@/components/Partial/Pagination';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Karyawan', href: '/karyawan' },
];

export default function Index({ karyawans, filters, auth }: any) {
    const isSuperAdmin = auth?.roles?.includes('superadmin');
    const now = new Date();
    const currentMonth = now.getMonth() + 1;
    const currentYear = now.getFullYear();

    const handleDelete = (id: number) => {
        if (confirm('Are you sure you want to delete this Karyawan?')) {
            router.delete(`/karyawan/${id}`);
        }
    };

    const formatCurrency = (value: number) => {
        return new Intl.NumberFormat('id-ID', {
            style: 'currency',
            currency: 'IDR',
            minimumFractionDigits: 0,
        }).format(value);
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Karyawan List" />

            <div className="p-4 sm:p-6 lg:p-8">
                {/* Header Section */}
                <div className="mb-8 flex flex-col items-start justify-between gap-4 sm:flex-row sm:items-center">
                    <div>
                        <h1 className="mb-1 text-3xl font-bold tracking-tight text-zinc-900 dark:text-zinc-50">
                            Karyawan List
                        </h1>
                        <p className="text-zinc-500 dark:text-zinc-400">
                            Manage employee records, salary and leaves.
                        </p>
                    </div>
                    <div className="flex items-center gap-3">
                        <Link href="/karyawan/create">
                            <Button className="bg-blue-600 text-white hover:bg-blue-700">
                                <Plus className="mr-2 h-4 w-4" /> Add Baru
                            </Button>
                        </Link>
                    </div>
                </div>

                {/* Filter Component (Reusing FilterAddrbook structure temporarily or implementing simple search) */}
                <FilterAddrbook baseUrl="/karyawan" filters={filters} />

                {/* Legacy Indicator descriptions */}
                <div className="mt-6 px-4 pb-4">
                    <div className="flex flex-col text-sm md:flex-row md:space-x-3">
                        <div className="flex items-center space-x-2">
                            <div className="h-3 w-3 rounded-full bg-blue-500"></div>
                            <p className="text-zinc-600 dark:text-zinc-400">
                                Cuti Tahunan
                            </p>
                        </div>
                        <div className="mt-2 flex items-center space-x-2 md:mt-0">
                            <div className="h-3 w-3 rounded-full bg-yellow-400"></div>
                            <p className="text-zinc-600 dark:text-zinc-400">
                                Cuti Sakit
                            </p>
                        </div>
                        <div className="mt-2 flex items-center space-x-2 md:mt-0">
                            <div className="h-3 w-3 rounded-full bg-red-500"></div>
                            <p className="text-zinc-600 dark:text-zinc-400">
                                Cuti Mendadak
                            </p>
                        </div>
                    </div>
                </div>

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
                                        Gaji {currentMonth}/{currentYear}
                                    </th>
                                    <th className="px-6 py-4 text-right font-bold tracking-wider text-zinc-900 dark:text-zinc-100">
                                        GPU {currentMonth}/{currentYear}
                                    </th>
                                    <th className="px-6 py-4 text-center font-bold tracking-wider text-zinc-900 dark:text-zinc-100">
                                        Cuti {currentYear}
                                    </th>
                                    <th className="px-6 py-4 text-right font-bold tracking-wider text-zinc-900 italic dark:text-zinc-100">
                                        Actions
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-zinc-200 dark:divide-zinc-800/50">
                                {karyawans?.data?.length > 0 ? (
                                    karyawans.data.map((item: any) => {
                                        const gpu = item.gaji_single
                                            ? Number(item.gaji_single.bulanan) +
                                              Number(item.gaji_single.harian) +
                                              Number(item.gaji_single.premi)
                                            : 0;

                                        return (
                                            <tr
                                                key={item.id}
                                                className="group transition-colors hover:bg-zinc-50 dark:hover:bg-zinc-800/40"
                                            >
                                                <td className="px-6 py-4">
                                                    <div className="flex flex-col">
                                                        <Link
                                                            href={`/karyawan/${item.id}`}
                                                            className="flex items-center gap-1 font-semibold text-blue-600 transition-colors hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300"
                                                        >
                                                            {item.nama}
                                                            <ExternalLink className="h-3 w-3 opacity-50" />
                                                        </Link>
                                                        <div className="mt-1 flex items-center gap-2">
                                                            <div className="text-xs text-zinc-500 opacity-80">
                                                                ID: {item.id}
                                                            </div>
                                                            {item.flag ===
                                                                2 && (
                                                                <Badge
                                                                    variant="outline"
                                                                    className="h-4 border-red-200 bg-red-50 px-1 text-[10px] font-bold text-red-600 uppercase dark:border-red-800 dark:bg-red-900/10 dark:text-red-400"
                                                                >
                                                                    Privasi
                                                                </Badge>
                                                            )}
                                                        </div>
                                                    </div>
                                                </td>
                                                <td className="px-6 py-4">
                                                    <div className="flex flex-col gap-1 text-zinc-500 dark:text-zinc-400">
                                                        <div className="flex items-center gap-2">
                                                            <Phone className="h-3 w-3" />{' '}
                                                            {item.no_telp ||
                                                                '-'}
                                                        </div>
                                                    </div>
                                                </td>
                                                <td className="px-6 py-4 text-right">
                                                    <div className="font-medium text-zinc-900 dark:text-zinc-100">
                                                        {item.gaji_single ? (
                                                            formatCurrency(
                                                                item.gaji_single
                                                                    .total_gaji,
                                                            )
                                                        ) : (
                                                            <span className="text-xs text-zinc-400 italic">
                                                                Belum dibuat
                                                            </span>
                                                        )}
                                                    </div>
                                                </td>
                                                <td className="px-6 py-4 text-right">
                                                    <div className="font-medium text-zinc-900 dark:text-zinc-100">
                                                        {item.gaji_single ? (
                                                            formatCurrency(gpu)
                                                        ) : (
                                                            <span className="text-xs text-zinc-400 italic">
                                                                Belum dibuat
                                                            </span>
                                                        )}
                                                    </div>
                                                </td>
                                                <td className="px-6 py-4 text-center">
                                                    <div className="flex items-center justify-center space-x-1.5">
                                                        <div
                                                            className="flex h-7 w-7 items-center justify-center rounded-full bg-blue-500 text-xs font-medium text-white shadow-sm"
                                                            title="Cuti Tahunan"
                                                        >
                                                            {item.total_cuti_tahunan ||
                                                                0}
                                                        </div>
                                                        <div
                                                            className="flex h-7 w-7 items-center justify-center rounded-full bg-yellow-400 text-xs font-medium text-yellow-950 shadow-sm"
                                                            title="Cuti Sakit"
                                                        >
                                                            {item.total_cuti_sakit ||
                                                                0}
                                                        </div>
                                                        <div
                                                            className="flex h-7 w-7 items-center justify-center rounded-full bg-red-500 text-xs font-medium text-white shadow-sm"
                                                            title="Cuti Mendadak"
                                                        >
                                                            {item.total_cuti_mendadak ||
                                                                0}
                                                        </div>
                                                    </div>
                                                </td>
                                                <td className="px-6 py-4 text-right">
                                                    <div className="flex justify-end gap-2 text-xs">
                                                        <Link
                                                            href={`/karyawan/${item.id}/cuti/create`}
                                                            className="inline-flex items-center justify-center rounded-md px-2 py-1.5 font-medium text-blue-600 transition-colors hover:bg-blue-50 focus:outline-none dark:text-blue-400 dark:hover:bg-blue-900/20"
                                                        >
                                                            + Cuti
                                                        </Link>
                                                        <Link
                                                            href={`/karyawan/${item.id}/gaji/create`}
                                                            className="inline-flex items-center justify-center rounded-md px-2 py-1.5 font-medium text-emerald-600 transition-colors hover:bg-emerald-50 focus:outline-none dark:text-emerald-400 dark:hover:bg-emerald-900/20"
                                                        >
                                                            + Gaji
                                                        </Link>

                                                        {(isSuperAdmin ||
                                                            item.flag !==
                                                                2) && (
                                                            <Link
                                                                href={`/karyawan/${item.id}/edit`}
                                                                className="inline-flex items-center justify-center rounded-md p-2 text-zinc-400 hover:bg-blue-50 hover:text-blue-500 dark:hover:bg-blue-900/20"
                                                            >
                                                                <FilePen className="h-4 w-4" />
                                                            </Link>
                                                        )}

                                                        <button
                                                            onClick={() =>
                                                                handleDelete(
                                                                    item.id,
                                                                )
                                                            }
                                                            className="inline-flex items-center justify-center rounded-md p-2 text-zinc-400 hover:bg-red-50 hover:text-red-500 dark:hover:bg-red-900/20"
                                                        >
                                                            <Trash2 className="h-4 w-4" />
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        );
                                    })
                                ) : (
                                    <tr>
                                        <td
                                            colSpan={6}
                                            className="px-6 py-12 text-center text-zinc-500"
                                        >
                                            Data Karyawan kosong.
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>

                    {/* Pagination */}
                    {karyawans?.links && karyawans.last_page > 1 && (
                        <div className="flex items-center justify-between border-t border-zinc-200 px-6 py-4 dark:border-zinc-800">
                            <Pagination
                                links={karyawans.links}
                                from={karyawans.from}
                                to={karyawans.to}
                                total={karyawans.total}
                                label="Karyawan"
                            />
                        </div>
                    )}
                </div>
            </div>
        </AppLayout>
    );
}
