
import { Head, Link, router } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { BreadcrumbItem } from '@/types';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
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
    Truck
} from 'lucide-react';
import { useState } from 'react';
import addrbookRoutes from '@/routes/addrbook';

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

const TabButton = ({ active, children, onClick }: { active: boolean; children: React.ReactNode; onClick: () => void }) => (
    <button
        onClick={onClick}
        className={`px-6 py-4 text-sm font-medium transition-all relative ${active
            ? 'text-blue-600 dark:text-blue-400 border-b-2 border-blue-600 dark:border-blue-400'
            : 'text-zinc-500 hover:text-zinc-700 dark:hover:text-zinc-300 border-b-2 border-transparent hover:border-zinc-200 dark:hover:border-zinc-800'
            }`}
    >
        {children}
    </button>
);

export default function AddrbookShow({ addrbook, ppn_rate }: Props) {
    const [activeTab, setActiveTab] = useState<'overview' | 'stats' | 'history'>('overview');

    const formatCurrency = (value: string | number) => {
        return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR' }).format(Number(value) || 0);
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`${addrbook.name} - Detail`} />

            <div className="p-4 sm:p-6 lg:p-8">
                {/* Header Card */}
                <div className="mb-8 flex flex-col md:flex-row justify-between items-start md:items-center gap-6">
                    <div className="flex items-start gap-5">
                        <div className="h-16 w-16 rounded-2xl bg-gradient-to-br from-blue-500 to-indigo-600 flex items-center justify-center text-white shadow-lg shadow-blue-500/20">
                            <User className="h-8 w-8" />
                        </div>
                        <div>
                            <div className="flex items-center gap-3 mb-1">
                                <h1 className="text-3xl font-extrabold tracking-tight text-zinc-900 dark:text-zinc-50">
                                    {addrbook.name}
                                </h1>
                                <Badge variant="outline" className="bg-blue-50 text-blue-700 border-blue-200 dark:bg-blue-900/20 dark:text-blue-400 dark:border-blue-800">
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
                                    <span className={`h-2 w-2 rounded-full ${addrbook.is_online ? 'bg-emerald-500' : 'bg-zinc-300'}`}></span>
                                    {addrbook.is_online ? 'Online' : 'Offline'}
                                </span>
                            </div>
                        </div>
                    </div>

                    <div className="flex items-center gap-2">
                        <Button variant="outline" onClick={() => window.history.back()} className="rounded-xl h-11 px-5 border-zinc-200 dark:border-zinc-800">
                            <ArrowLeft className="mr-2 h-4 w-4" /> Back
                        </Button>
                        <Link href={addrbookRoutes.edit.url(addrbook.id)}>
                            <Button className="bg-blue-600 hover:bg-blue-700 text-white rounded-xl h-11 px-5 shadow-lg shadow-blue-600/20">
                                <FilePen className="mr-2 h-4 w-4" /> Edit Details
                            </Button>
                        </Link>
                    </div>
                </div>

                {/* Tabs Navigation */}
                <div className="border-b border-zinc-200 dark:border-zinc-800 mb-8 flex overflow-x-auto scrollbar-hide">
                    <TabButton active={activeTab === 'overview'} onClick={() => setActiveTab('overview')}>
                        <div className="flex items-center gap-2">
                            <Info className="h-4 w-4" /> Overview
                        </div>
                    </TabButton>
                    <TabButton active={activeTab === 'stats'} onClick={() => setActiveTab('stats')}>
                        <div className="flex items-center gap-2">
                            <BarChart3 className="h-4 w-4" /> Statistics
                        </div>
                    </TabButton>
                    <TabButton active={activeTab === 'history'} onClick={() => setActiveTab('history')}>
                        <div className="flex items-center gap-2">
                            <History className="h-4 w-4" /> Financial History
                        </div>
                    </TabButton>
                </div>

                {/* Tab Content */}
                <div className="animate-in fade-in slide-in-from-bottom-2 duration-300">
                    {/* Overview Tab */}
                    {activeTab === 'overview' && (
                        <div className="grid grid-cols-1 lg:grid-cols-3 gap-8">
                            <Card className="lg:col-span-2 rounded-2xl border-zinc-200 dark:border-zinc-800 shadow-sm overflow-hidden">
                                <CardHeader className="bg-zinc-50/50 dark:bg-zinc-900/30 border-b border-zinc-200 dark:border-zinc-800 p-6">
                                    <CardTitle className="text-lg font-bold">General Information</CardTitle>
                                    <CardDescription>Primary contact and basic details</CardDescription>
                                </CardHeader>
                                <CardContent className="p-8 space-y-8">
                                    <div className="grid grid-cols-1 md:grid-cols-2 gap-x-12 gap-y-8">
                                        <div className="space-y-2">
                                            <p className="text-xs font-bold uppercase tracking-wider text-zinc-400">Contact Person</p>
                                            <div className="flex items-center gap-3 text-zinc-900 dark:text-zinc-100 font-medium">
                                                <div className="h-9 w-9 rounded-full bg-zinc-100 dark:bg-zinc-800 flex items-center justify-center text-zinc-500">
                                                    <User className="h-4 w-4" />
                                                </div>
                                                {addrbook.contact_person || 'No data available'}
                                            </div>
                                        </div>
                                        <div className="space-y-2">
                                            <p className="text-xs font-bold uppercase tracking-wider text-zinc-400">Phone Number</p>
                                            <div className="flex items-center gap-3 text-zinc-900 dark:text-zinc-100 font-medium">
                                                <div className="h-9 w-9 rounded-full bg-zinc-100 dark:bg-zinc-800 flex items-center justify-center text-zinc-500">
                                                    <Phone className="h-4 w-4" />
                                                </div>
                                                {addrbook.phone || 'No data available'}
                                            </div>
                                        </div>
                                        <div className="space-y-2">
                                            <p className="text-xs font-bold uppercase tracking-wider text-zinc-400">Email Address</p>
                                            <div className="flex items-center gap-3 text-zinc-900 dark:text-zinc-100 font-medium">
                                                <div className="h-9 w-9 rounded-full bg-zinc-100 dark:bg-zinc-800 flex items-center justify-center text-zinc-500">
                                                    <Mail className="h-4 w-4" />
                                                </div>
                                                {addrbook.email || 'No data available'}
                                            </div>
                                        </div>
                                        <div className="space-y-2">
                                            <p className="text-xs font-bold uppercase tracking-wider text-zinc-400">Tax Status (PPN)</p>
                                            <div className="flex flex-wrap gap-2">
                                                {addrbook.ppn ? (
                                                    <Badge className="bg-emerald-100 text-emerald-700 hover:bg-emerald-100 border-none px-3 py-1 font-medium">
                                                        PPN Active ({ppn_rate}%)
                                                    </Badge>
                                                ) : (
                                                    <Badge variant="outline" className="text-zinc-400 border-zinc-200 dark:border-zinc-800 px-3 py-1">PPN Non-Active</Badge>
                                                )}
                                            </div>
                                        </div>
                                    </div>

                                    <div className="pt-6 border-t border-zinc-100 dark:border-zinc-800 space-y-3">
                                        <p className="text-xs font-bold uppercase tracking-wider text-zinc-400">Full Address</p>
                                        <div className="flex gap-4 items-start bg-zinc-50/50 dark:bg-zinc-900/50 p-6 rounded-2xl border border-zinc-200 dark:border-zinc-800">
                                            <div className="h-10 w-10 rounded-xl bg-white dark:bg-zinc-900 flex items-center justify-center shadow-sm text-zinc-500">
                                                <MapPin className="h-5 w-5" />
                                            </div>
                                            <p className="text-zinc-600 dark:text-zinc-300 leading-relaxed whitespace-pre-line text-sm md:text-base">
                                                {addrbook.address || 'No address provided'}
                                            </p>
                                        </div>
                                    </div>

                                    {addrbook.description && (
                                        <div className="space-y-3">
                                            <p className="text-xs font-bold uppercase tracking-wider text-zinc-400">Description / Internal Notes</p>
                                            <p className="text-sm text-zinc-600 dark:text-zinc-400 italic">
                                                {addrbook.description}
                                            </p>
                                        </div>
                                    )}


                                </CardContent>
                            </Card>

                            <div className="space-y-8">
                                <Card className="rounded-2xl border-none bg-blue-600 text-white shadow-xl shadow-blue-600/20 overflow-hidden relative">
                                    <div className="absolute top-0 right-0 p-8 opacity-10">
                                        <BarChart3 className="h-32 w-32 rotate-12" />
                                    </div>
                                    <CardContent className="p-8">
                                        <p className="text-blue-100 text-sm font-medium mb-1 uppercase tracking-widest">Current Balance</p>
                                        <h3 className="text-4xl font-extrabold truncate">
                                            {formatCurrency(addrbook.stat?.balance || 0)}
                                        </h3>
                                        <p className="text-blue-200 text-xs mt-4">Total outstanding or credit balance</p>
                                    </CardContent>
                                </Card>

                                <Card className="rounded-2xl border-zinc-200 dark:border-zinc-800 shadow-sm overflow-hidden">
                                    <CardHeader className="p-6 pb-2">
                                        <CardTitle className="text-sm font-bold uppercase tracking-wider text-zinc-400">Account Metadata</CardTitle>
                                    </CardHeader>
                                    <CardContent className="p-6 space-y-4">
                                        <div className="flex justify-between items-center text-sm">
                                            <span className="text-zinc-500">Joined on</span>
                                            <span className="font-medium">{new Date(addrbook.created_at).toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' })}</span>
                                        </div>
                                        <div className="flex justify-between items-center text-sm">
                                            <span className="text-zinc-500">Last activity</span>
                                            <span className="font-medium">{addrbook.dailies?.[0]?.date ? new Date(addrbook.dailies[0].date).toLocaleDateString() : 'N/A'}</span>
                                        </div>
                                    </CardContent>
                                </Card>
                            </div>
                        </div>
                    )}

                    {/* Stats Tab */}
                    {activeTab === 'stats' && (
                        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                            <Card className="rounded-2xl border-zinc-200 dark:border-zinc-800 shadow-sm p-6 flex flex-col items-center justify-center text-center">
                                <div className="h-12 w-12 rounded-full bg-emerald-100 dark:bg-emerald-900/30 text-emerald-600 dark:text-emerald-400 flex items-center justify-center mb-4">
                                    <ArrowUpCircle className="h-6 w-6" />
                                </div>
                                <p className="text-zinc-500 text-xs font-bold uppercase tracking-wider">Total Sales</p>
                                <p className="text-2xl font-bold mt-1">{formatCurrency(addrbook.dailies?.reduce((acc, c) => acc + Number(c.sell), 0) || 0)}</p>
                            </Card>
                            <Card className="rounded-2xl border-zinc-200 dark:border-zinc-800 shadow-sm p-6 flex flex-col items-center justify-center text-center">
                                <div className="h-12 w-12 rounded-full bg-red-100 dark:bg-red-900/30 text-red-600 dark:text-red-400 flex items-center justify-center mb-4">
                                    <ShoppingBag className="h-6 w-6" />
                                </div>
                                <p className="text-zinc-500 text-xs font-bold uppercase tracking-wider">Total Purchases</p>
                                <p className="text-2xl font-bold mt-1">{formatCurrency(addrbook.dailies?.reduce((acc, c) => acc + Number(c.buy), 0) || 0)}</p>
                            </Card>
                            <Card className="rounded-2xl border-zinc-200 dark:border-zinc-800 shadow-sm p-6 flex flex-col items-center justify-center text-center">
                                <div className="h-12 w-12 rounded-full bg-blue-100 dark:bg-blue-900/30 text-blue-600 dark:text-blue-400 flex items-center justify-center mb-4">
                                    <ArrowDownCircle className="h-6 w-6" />
                                </div>
                                <p className="text-zinc-500 text-xs font-bold uppercase tracking-wider">Cash In</p>
                                <p className="text-2xl font-bold mt-1">{formatCurrency(addrbook.dailies?.reduce((acc, c) => acc + Number(c.cash_in), 0) || 0)}</p>
                            </Card>
                            <Card className="rounded-2xl border-zinc-200 dark:border-zinc-800 shadow-sm p-6 flex flex-col items-center justify-center text-center">
                                <div className="h-12 w-12 rounded-full bg-orange-100 dark:bg-orange-900/30 text-orange-600 dark:text-orange-400 flex items-center justify-center mb-4">
                                    <Truck className="h-6 w-6" />
                                </div>
                                <p className="text-zinc-500 text-xs font-bold uppercase tracking-wider">Return Items</p>
                                <p className="text-2xl font-bold mt-1">{formatCurrency(addrbook.dailies?.reduce((acc, c) => acc + Number(c.return), 0) || 0)}</p>
                            </Card>

                            <Card className="md:col-span-2 lg:col-span-4 rounded-2xl border-zinc-200 dark:border-zinc-800 shadow-sm p-12 text-center bg-zinc-50/50 dark:bg-zinc-900/30">
                                <BarChart3 className="h-16 w-16 mx-auto mb-4 text-zinc-300" />
                                <h3 className="text-lg font-bold text-zinc-400">Detailed analytics coming soon</h3>
                                <p className="text-zinc-400 text-sm max-w-sm mx-auto">Visual charts and historical trends will be available in the next update.</p>
                            </Card>
                        </div>
                    )}

                    {/* History Tab */}
                    {activeTab === 'history' && (
                        <Card className="rounded-2xl border-zinc-200 dark:border-zinc-800 shadow-sm overflow-hidden">
                            <div className="overflow-x-auto">
                                <table className="w-full text-sm text-left">
                                    <thead className="bg-zinc-50 dark:bg-zinc-900/50 border-b border-zinc-200 dark:border-zinc-800 text-xs text-zinc-500 uppercase font-bold tracking-wider">
                                        <tr>
                                            <th className="px-6 py-4">Date</th>
                                            <th className="px-6 py-4 text-right">Sell</th>
                                            <th className="px-6 py-4 text-right">Buy</th>
                                            <th className="px-6 py-4 text-right">Cash In</th>
                                            <th className="px-6 py-4 text-right">Cash Out</th>
                                            <th className="px-6 py-4 text-right">Return</th>
                                            <th className="px-6 py-4 text-right">Adjustment</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-zinc-200 dark:divide-zinc-800/50">
                                        {addrbook.dailies && addrbook.dailies.length > 0 ? (
                                            addrbook.dailies.map((cls) => (
                                                <tr key={cls.id} className="hover:bg-zinc-50 dark:hover:bg-zinc-800/40 transition-colors">
                                                    <td className="px-6 py-4 whitespace-nowrap font-medium font-mono text-zinc-600 dark:text-zinc-300">
                                                        {new Date(cls.date).toLocaleDateString('id-ID')}
                                                    </td>
                                                    <td className={`px-6 py-4 text-right ${Number(cls.sell) > 0 ? 'text-emerald-600 dark:text-emerald-400 font-medium' : 'text-zinc-400'}`}>
                                                        {formatCurrency(cls.sell)}
                                                    </td>
                                                    <td className={`px-6 py-4 text-right ${Number(cls.buy) > 0 ? 'text-red-500 font-medium' : 'text-zinc-400'}`}>
                                                        {formatCurrency(cls.buy)}
                                                    </td>
                                                    <td className={`px-6 py-4 text-right ${Number(cls.cash_in) > 0 ? 'text-blue-600 dark:text-blue-400 font-medium' : 'text-zinc-400'}`}>
                                                        {formatCurrency(cls.cash_in)}
                                                    </td>
                                                    <td className={`px-6 py-4 text-right ${Number(cls.cash_out) > 0 ? 'text-orange-500 font-medium' : 'text-zinc-400'}`}>
                                                        {formatCurrency(cls.cash_out)}
                                                    </td>
                                                    <td className="px-6 py-4 text-right text-zinc-400">
                                                        {formatCurrency(cls.return)}
                                                    </td>
                                                    <td className="px-6 py-4 text-right text-zinc-400">
                                                        {formatCurrency(cls.adjust)}
                                                    </td>
                                                </tr>
                                            ))
                                        ) : (
                                            <tr>
                                                <td colSpan={7} className="px-6 py-20 text-center text-zinc-400">
                                                    <History className="h-10 w-10 mx-auto mb-3 opacity-20" />
                                                    No financial logs found.
                                                </td>
                                            </tr>
                                        )}
                                    </tbody>
                                </table>
                            </div>
                        </Card>
                    )}
                </div>
            </div>
        </AppLayout>
    );
}

