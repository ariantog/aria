import { Head, Link, router } from '@inertiajs/react';
import {
    FilePen,
    ArrowLeft,
    MapPin,
    Phone,
    Mail,
    User,
    Info,
    History,
    BarChart3,
    ArrowUpCircle,
    ArrowDownCircle,
    ShoppingBag,
    Truck,
    Package,
} from 'lucide-react';
import { useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardHeader,
    CardTitle,
    CardDescription,
} from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import addrbookRoutes from '@/routes/addrbook';
import type { BreadcrumbItem } from '@/types';

interface AddrbookStat {
    balance: string | number;
}

interface AddrbookDaily {
    id: number;
    date: string;
    cash_in: string | number;
    cash_out: string | number;
    sell: string | number;
    buy: string | number;
    return: string | number;
    return_supplier: string | number;
    use: string | number;
    move: string | number;
    transfer: string | number;
    adjust: string | number;
    depreciation: string | number;
}

interface AdditionalFee {
    id: number;
    name: string;
    value: string | number;
    type: 'percent' | 'nominal';
}

interface AddrbookItem {
    id: number;
    name: string;
    code: string;
    type: number;
    calculated_cost: number;
    total_calculated_cost: number;
    pivot: {
        quantity: string | number;
    };
}

interface Addrbook {
    id: number;
    name: string;
    address: string | null;
    phone: string | null;
    email: string | null;
    contact_person: string | null;
    is_online: boolean;
    ppn: boolean;
    member_id: string | null;
    type: number;
    type_name: string;
    description: string | null;
    created_at: string;
    stat?: AddrbookStat;
    dailies?: AddrbookDaily[];
    additional_fees?: AdditionalFee[];
    items?: AddrbookItem[];
}

interface Props {
    addrbook: Addrbook;
    ppn_rate: number;
}

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Address Book',
        href: '/addrbook',
    },
    {
        title: 'Detail',
        href: '#',
    },
];

const TabButton = ({
    active,
    children,
    onClick,
}: {
    active: boolean;
    children: React.ReactNode;
    onClick: () => void;
}) => (
    <button
        onClick={onClick}
        className={`relative px-6 py-4 text-sm font-medium transition-all ${
            active
                ? 'border-b-2 border-blue-600 text-blue-600 dark:border-blue-400 dark:text-blue-400'
                : 'border-b-2 border-transparent text-zinc-500 hover:border-zinc-200 hover:text-zinc-700 dark:hover:border-zinc-800 dark:hover:text-zinc-300'
        }`}
    >
        {children}
    </button>
);

export default function AddrbookShow({ addrbook, ppn_rate }: Props) {
    const [activeTab, setActiveTab] = useState<
        'overview' | 'stats' | 'history' | 'inventory'
    >('overview');

    const formatCurrency = (value: string | number) => {
        return new Intl.NumberFormat('id-ID', {
            style: 'currency',
            currency: 'IDR',
            minimumFractionDigits: 0,
        }).format(Number(value) || 0);
    };

    const formatNumber = (value: string | number) => {
        return new Intl.NumberFormat('id-ID').format(Number(value) || 0);
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`${addrbook.name} - Detail`} />

            <div className="p-4 sm:p-6 lg:p-8">
                {/* Header Card */}
                <div className="mb-8 flex flex-col items-start justify-between gap-6 md:flex-row md:items-center">
                    <div className="flex items-start gap-5">
                        <div className="flex h-16 w-16 items-center justify-center rounded-2xl bg-gradient-to-br from-blue-500 to-indigo-600 text-white shadow-lg shadow-blue-500/20">
                            <User className="h-8 w-8" />
                        </div>
                        <div>
                            <div className="mb-1 flex items-center gap-3">
                                <h1 className="text-3xl font-extrabold tracking-tight text-zinc-900 dark:text-zinc-50">
                                    {addrbook.name}
                                </h1>
                                <Badge
                                    variant="outline"
                                    className="border-blue-200 bg-blue-50 text-blue-700 dark:border-blue-800 dark:bg-blue-900/20 dark:text-blue-400"
                                >
                                    {addrbook.type_name}
                                </Badge>
                            </div>
                            <div className="flex flex-wrap items-center gap-x-4 gap-y-1 text-sm text-zinc-500 dark:text-zinc-400">
                                <span className="flex items-center gap-1.5 font-mono">
                                    ID: #{addrbook.id}
                                </span>
                                {addrbook.member_id && (
                                    <span className="flex items-center gap-1.5 font-mono">
                                        Member ID: {addrbook.member_id}
                                    </span>
                                )}
                                <span className="flex items-center gap-1.5">
                                    <span
                                        className={`h-2 w-2 rounded-full ${addrbook.is_online ? 'bg-emerald-500' : 'bg-zinc-300'}`}
                                    ></span>
                                    {addrbook.is_online ? 'Online' : 'Offline'}
                                </span>
                            </div>
                        </div>
                    </div>

                    <div className="flex items-center gap-2">
                        <Button
                            variant="outline"
                            onClick={() => window.history.back()}
                            className="h-11 rounded-xl border-zinc-200 px-5 dark:border-zinc-800"
                        >
                            <ArrowLeft className="mr-2 h-4 w-4" /> Back
                        </Button>
                        <Link href={addrbookRoutes.edit.url(addrbook.id)}>
                            <Button className="h-11 rounded-xl bg-blue-600 px-5 text-white shadow-lg shadow-blue-600/20 hover:bg-blue-700">
                                <FilePen className="mr-2 h-4 w-4" /> Edit
                                Details
                            </Button>
                        </Link>
                    </div>
                </div>

                {/* Tabs Navigation */}
                <div className="scrollbar-hide mb-8 flex overflow-x-auto border-b border-zinc-200 dark:border-zinc-800">
                    <Link
                        href={`/${addrbook.type_slug}/${addrbook.id}`}
                        className="border-b-2 border-blue-600 px-6 py-4 text-sm font-medium whitespace-nowrap text-blue-600 transition-all dark:border-blue-400 dark:text-blue-400"
                    >
                        Detail
                    </Link>
                    <Link
                        href={`/${addrbook.type_slug}/${addrbook.id}/transactions`}
                        className="border-b-2 border-transparent px-6 py-4 text-sm font-medium whitespace-nowrap text-zinc-500 transition-all hover:border-zinc-200 hover:text-zinc-700 dark:hover:border-zinc-800 dark:hover:text-zinc-300"
                    >
                        Transaction
                    </Link>
                    {addrbook.type === 2 && ( // TYPE_WAREHOUSE
                        <>
                            <Link
                                href={`/${addrbook.type_slug}/${addrbook.id}/items`}
                                className="border-b-2 border-transparent px-6 py-4 text-sm font-medium whitespace-nowrap text-zinc-500 transition-all hover:border-zinc-200 hover:text-zinc-700 dark:hover:border-zinc-800 dark:hover:text-zinc-300"
                            >
                                Items
                            </Link>
                            <Link
                                href={`/${addrbook.type_slug}/${addrbook.id}/stats`}
                                className="border-b-2 border-transparent px-6 py-4 text-sm font-medium whitespace-nowrap text-zinc-500 transition-all hover:border-zinc-200 hover:text-zinc-700 dark:hover:border-zinc-800 dark:hover:text-zinc-300"
                            >
                                Stats
                            </Link>
                        </>
                    )}
                    <Link
                        href={`/${addrbook.type_slug}/${addrbook.id}/item-sales`}
                        className="border-b-2 border-transparent px-6 py-4 text-sm font-medium whitespace-nowrap text-zinc-500 transition-all hover:border-zinc-200 hover:text-zinc-700 dark:hover:border-zinc-800 dark:hover:text-zinc-300"
                    >
                        Item Sale
                    </Link>
                </div>

                {/* Tab Content */}
                <div className="animate-in duration-300 fade-in slide-in-from-bottom-2">
                    <div className="grid grid-cols-1 gap-8 lg:grid-cols-3">
                        <Card className="overflow-hidden rounded-2xl border-zinc-200 shadow-sm lg:col-span-2 dark:border-zinc-800">
                            <CardHeader className="border-b border-zinc-200 bg-zinc-50/50 p-6 dark:border-zinc-800 dark:bg-zinc-900/30">
                                <CardTitle className="text-lg font-bold">
                                    General Information
                                </CardTitle>
                                <CardDescription>
                                    Primary contact and basic details
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-8 p-8">
                                <div className="grid grid-cols-1 gap-x-12 gap-y-8 md:grid-cols-2">
                                    <div className="space-y-2">
                                        <p className="text-xs font-bold tracking-wider text-zinc-400 uppercase">
                                            Contact Person
                                        </p>
                                        <div className="flex items-center gap-3 font-medium text-zinc-900 dark:text-zinc-100">
                                            <div className="flex h-9 w-9 items-center justify-center rounded-full bg-zinc-100 text-zinc-500 dark:bg-zinc-800">
                                                <User className="h-4 w-4" />
                                            </div>
                                            {addrbook.contact_person ||
                                                'No data available'}
                                        </div>
                                    </div>
                                    <div className="space-y-2">
                                        <p className="text-xs font-bold tracking-wider text-zinc-400 uppercase">
                                            Phone Number
                                        </p>
                                        <div className="flex items-center gap-3 font-medium text-zinc-900 dark:text-zinc-100">
                                            <div className="flex h-9 w-9 items-center justify-center rounded-full bg-zinc-100 text-zinc-500 dark:bg-zinc-800">
                                                <Phone className="h-4 w-4" />
                                            </div>
                                            {addrbook.phone ||
                                                'No data available'}
                                        </div>
                                    </div>
                                    <div className="space-y-2">
                                        <p className="text-xs font-bold tracking-wider text-zinc-400 uppercase">
                                            Email Address
                                        </p>
                                        <div className="flex items-center gap-3 font-medium text-zinc-900 dark:text-zinc-100">
                                            <div className="flex h-9 w-9 items-center justify-center rounded-full bg-zinc-100 text-zinc-500 dark:bg-zinc-800">
                                                <Mail className="h-4 w-4" />
                                            </div>
                                            {addrbook.email ||
                                                'No data available'}
                                        </div>
                                    </div>
                                    <div className="space-y-2">
                                        <p className="text-xs font-bold tracking-wider text-zinc-400 uppercase">
                                            Tax Status (PPN)
                                        </p>
                                        <div className="flex flex-wrap gap-2">
                                            {addrbook.ppn ? (
                                                <Badge className="border-none bg-emerald-100 px-3 py-1 font-medium text-emerald-700 hover:bg-emerald-100">
                                                    PPN Active ({ppn_rate}%)
                                                </Badge>
                                            ) : (
                                                <Badge
                                                    variant="outline"
                                                    className="border-zinc-200 px-3 py-1 text-zinc-400 dark:border-zinc-800"
                                                >
                                                    PPN Non-Active
                                                </Badge>
                                            )}
                                        </div>
                                    </div>
                                </div>

                                <div className="space-y-3 border-t border-zinc-100 pt-6 dark:border-zinc-800">
                                    <p className="text-xs font-bold tracking-wider text-zinc-400 uppercase">
                                        Full Address
                                    </p>
                                    <div className="flex items-start gap-4 rounded-2xl border border-zinc-200 bg-zinc-50/50 p-6 dark:border-zinc-800 dark:bg-zinc-900/50">
                                        <div className="flex h-10 w-10 items-center justify-center rounded-xl bg-white text-zinc-500 shadow-sm dark:bg-zinc-900">
                                            <MapPin className="h-5 w-5" />
                                        </div>
                                        <p className="text-sm leading-relaxed whitespace-pre-line text-zinc-600 md:text-base dark:text-zinc-300">
                                            {addrbook.address ||
                                                'No address provided'}
                                        </p>
                                    </div>
                                </div>

                                {addrbook.description && (
                                    <div className="space-y-3">
                                        <p className="text-xs font-bold tracking-wider text-zinc-400 uppercase">
                                            Description / Internal Notes
                                        </p>
                                        <p className="text-sm text-zinc-600 italic dark:text-zinc-400">
                                            {addrbook.description}
                                        </p>
                                    </div>
                                )}
                            </CardContent>
                        </Card>

                        <div className="space-y-8">
                            <Card className="relative overflow-hidden rounded-2xl border-none bg-blue-600 text-white shadow-xl shadow-blue-600/20">
                                <div className="absolute top-0 right-0 p-8 opacity-10">
                                    <BarChart3 className="h-32 w-32 rotate-12" />
                                </div>
                                <CardContent className="p-8">
                                    <p className="mb-1 text-sm font-medium tracking-widest text-blue-100 uppercase">
                                        Current Balance
                                    </p>
                                    <h3 className="truncate text-4xl font-extrabold">
                                        {formatCurrency(
                                            addrbook.stat?.balance || 0,
                                        )}
                                    </h3>
                                    <p className="mt-4 text-xs text-blue-200">
                                        Total outstanding or credit balance
                                    </p>
                                </CardContent>
                            </Card>

                            <Card className="overflow-hidden rounded-2xl border-zinc-200 shadow-sm dark:border-zinc-800">
                                <CardHeader className="p-6 pb-2">
                                    <CardTitle className="text-sm font-bold tracking-wider text-zinc-400 uppercase">
                                        Account Metadata
                                    </CardTitle>
                                </CardHeader>
                                <CardContent className="space-y-4 p-6">
                                    <div className="flex items-center justify-between text-sm">
                                        <span className="text-zinc-500">
                                            Joined on
                                        </span>
                                        <span className="font-medium">
                                            {new Date(
                                                addrbook.created_at,
                                            ).toLocaleDateString('id-ID', {
                                                day: 'numeric',
                                                month: 'long',
                                                year: 'numeric',
                                            })}
                                        </span>
                                    </div>
                                    <div className="flex items-center justify-between text-sm">
                                        <span className="text-zinc-500">
                                            Last activity
                                        </span>
                                        <span className="font-medium">
                                            {addrbook.dailies?.[0]?.date
                                                ? new Date(
                                                      addrbook.dailies[0].date,
                                                  ).toLocaleDateString()
                                                : 'N/A'}
                                        </span>
                                    </div>
                                </CardContent>
                            </Card>
                        </div>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
