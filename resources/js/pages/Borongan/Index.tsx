import { Head, Link, router } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { BreadcrumbItem } from '@/types';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from "@/components/ui/select"
import { format } from 'date-fns';
import { id } from 'date-fns/locale';
import { Eye, X, Plus, Search } from 'lucide-react';
import { useState } from 'react';
import BoronganFilter from '@/components/Partial/Filter/BoronganFilter';
import {
    Pagination,
    PaginationContent,
    PaginationItem,
    PaginationLink,
} from '@/components/ui/pagination';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Borongan',
        href: '/borongan',
    },
];

export default function Index({ borongans, filters, can }: any) {
    const formatCurrency = (value: number) => {
        return new Intl.NumberFormat('id-ID', {
            style: 'decimal',
            minimumFractionDigits: 0,
        }).format(value);
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Borongan List" />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <div className="flex items-center justify-between">
                    <h1 className="text-2xl font-bold tracking-tight">Borongan List</h1>
                    <div className="flex items-center gap-2">
                        {can.create_borongan && (
                            <Button asChild>
                                <Link href="/borongan/create">
                                    <Plus className="mr-2 h-4 w-4" /> Tambah Borongan
                                </Link>
                            </Button>
                        )}
                    </div>
                </div>

                <BoronganFilter filters={filters} />

                <Card className="flex-1">
                    <CardContent className="p-0">
                        <div className="relative w-full overflow-auto">
                            <table className="w-full caption-bottom text-sm">
                                <thead className="bg-gray-100 dark:bg-gray-800">
                                    <tr className="border-b transition-colors hover:bg-muted/50">
                                        <th className="h-12 px-4 text-left align-middle font-medium text-muted-foreground uppercase text-xs">Date</th>
                                        <th className="h-12 px-4 text-left align-middle font-medium text-muted-foreground uppercase text-xs">Jahit</th>
                                        <th className="h-12 px-4 text-left align-middle font-medium text-muted-foreground uppercase text-xs">Items</th>
                                        <th className="h-12 px-4 text-left align-middle font-medium text-muted-foreground uppercase text-xs">Tres</th>
                                        <th className="h-12 px-4 text-left align-middle font-medium text-muted-foreground uppercase text-xs">Permak</th>
                                        <th className="h-12 px-4 text-left align-middle font-medium text-muted-foreground uppercase text-xs">Lain2</th>
                                        <th className="h-12 px-4 text-left align-middle font-medium text-muted-foreground uppercase text-xs">Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {borongans?.data?.length > 0 ? (
                                        borongans.data.map((item: any) => (
                                            <tr key={item.id} className="border-b transition-colors hover:bg-muted/50">
                                                <td className="p-4 align-middle">
                                                    {can.view_borongan ? (
                                                        <Link href={`/borongan/${item.id}`} className="font-medium text-blue-600 hover:underline">
                                                            {item.date ? format(new Date(item.date), 'yyyy-MM-dd') : '-'}
                                                        </Link>
                                                    ) : (
                                                        <span className="font-medium">
                                                            {item.date ? format(new Date(item.date), 'yyyy-MM-dd') : '-'}
                                                        </span>
                                                    )}
                                                </td>
                                                <td className="p-4 align-middle">{item.jahit?.name || '-'}</td>
                                                <td className="p-4 align-middle">{item.total_items}</td>
                                                <td className="p-4 align-middle">{item.tres || 0}</td>
                                                <td className="p-4 align-middle">{item.permak || 0}</td>
                                                <td className="p-4 align-middle">{item.lain2 || 0}</td>
                                                <td className="p-4 align-middle font-semibold">{formatCurrency(item.total)}</td>
                                            </tr>
                                        ))
                                    ) : (
                                        <tr>
                                            <td colSpan={7} className="p-4 text-center text-muted-foreground">
                                                Data Empty
                                            </td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </div>

                        {/* Pagination Component */}
                        {borongans?.links && borongans.last_page > 1 && (
                            <div className="py-4 flex justify-center px-4 border-t">
                                <Pagination>
                                    <PaginationContent>
                                        {borongans.links.map((link: any, index: number) => {
                                            if (link.url === null) {
                                                return (
                                                    <PaginationItem key={index}>
                                                        <span className="px-3 py-2 opacity-50 cursor-not-allowed text-sm" dangerouslySetInnerHTML={{ __html: link.label }} />
                                                    </PaginationItem>
                                                );
                                            }

                                            return (
                                                <PaginationItem key={index}>
                                                    <PaginationLink
                                                        href={link.url}
                                                        isActive={link.active}
                                                        size="sm"
                                                    >
                                                        <span dangerouslySetInnerHTML={{ __html: link.label }} />
                                                    </PaginationLink>
                                                </PaginationItem>
                                            );
                                        })}
                                    </PaginationContent>
                                </Pagination>
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
