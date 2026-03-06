import { Head, useForm } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { BreadcrumbItem } from '@/types';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { Calendar, UserCircle, Scissors, Layers, Info, CheckCircle2, RefreshCcw } from 'lucide-react';
import { AsyncCombobox } from '@/components/AsyncCombobox';
import { toast } from 'sonner';
import { useState, useEffect } from 'react';
import * as React from 'react';

interface Produksi {
    id: number;
    serial: string;
    temp_name: string;
    quantity: number;
    customer: string | null;
    warna: string | null;
    potong_date: string;
    surat_jalan_potong: string | null;
    jahit_date: string | null;
    potong: { id: number; name: string } | null;
    jahit: { id: number; name: string } | null;
    qc: { id: number; name: string } | null;
    size: { id: number; name: string } | null;
}

interface Props {
    produksi: Produksi;
    jahitList: { id: number; name: string }[];
}

export default function Edit({ produksi, jahitList }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Produksi', href: '/produksi' },
        { title: 'Edit', href: '#' },
        { title: produksi.serial, href: '#' },
    ];

    // Form 1: Basic Info
    const basicForm = useForm({
        warna: produksi.warna || '',
        customer: produksi.customer || '',
        surat_jalan_potong: produksi.surat_jalan_potong || '',
    });

    // Form 2: Split (Pisah Jahit)
    const splitForm = useForm({
        split_q: '',
    });

    // Form 3: Worker
    const workerForm = useForm({
        jahit_id: produksi.jahit?.id || '',
    });

    // Form 4: QC Worker
    const qcForm = useForm({
        qc_id: produksi.qc?.id || '',
    });

    const [selectedJahitWorker, setSelectedJahitWorker] = useState(produksi.jahit);
    const [selectedQcWorker, setSelectedQcWorker] = useState(produksi.qc);

    useEffect(() => {
        setSelectedJahitWorker(produksi.jahit);
        setSelectedQcWorker(produksi.qc);
    }, [produksi.jahit, produksi.qc]);

    const submitBasic = (e: React.FormEvent) => {
        e.preventDefault();
        basicForm.patch(`/produksi/${produksi.id}`, {
            preserveScroll: true,
            onSuccess: () => toast.success('Basic info updated'),
        });
    };

    const submitSplit = (e: React.FormEvent) => {
        e.preventDefault();
        splitForm.post(`/produksi/${produksi.id}/split`, {
            preserveScroll: true,
            onSuccess: () => {
                toast.success('Production entry split successfully');
                splitForm.reset();
            },
        });
    };

    const submitWorker = (e: React.FormEvent) => {
        e.preventDefault();
        workerForm.patch(`/produksi/${produksi.id}/worker`, {
            preserveScroll: true,
            onSuccess: () => toast.success('Jahit worker updated'),
        });
    };

    const submitQcWorker = (e: React.FormEvent) => {
        e.preventDefault();
        qcForm.patch(`/produksi/${produksi.id}/qc`, {
            preserveScroll: true,
            onSuccess: () => toast.success('QC worker updated'),
        });
    };


    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Edit Production ${produksi.serial}`} />

            <div className="p-4 sm:p-6 lg:p-8 space-y-8">
                {/* Header Info */}
                <div className="flex justify-between items-center">
                    <div>
                        <h2 className="text-3xl font-extrabold tracking-tight text-zinc-900 dark:text-zinc-50">Edit Production</h2>
                        <div className="flex items-center gap-2 mt-2 text-zinc-500">
                            <span className="font-mono text-lg font-bold text-blue-600 dark:text-blue-400">{produksi.serial}</span>
                            <span>&bull;</span>
                            <span className="font-semibold">{produksi.temp_name}</span>
                        </div>
                    </div>
                </div>

                <div className="grid grid-cols-1 lg:grid-cols-3 gap-8">
                    {/* Left Column: Read-only Info */}
                    <Card className="lg:col-span-1 border-zinc-200 dark:border-zinc-800 shadow-sm">
                        <CardHeader>
                            <CardTitle className="text-lg flex items-center gap-2">
                                <Info className="h-5 w-5 text-blue-500" />
                                Details
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-6">
                            <div className="space-y-1">
                                <Label className="text-xs text-zinc-500 uppercase">Quantity</Label>
                                <p className="text-lg font-bold">{produksi.quantity}</p>
                            </div>
                            <div className="space-y-1">
                                <Label className="text-xs text-zinc-500 uppercase">Size</Label>
                                <p className="text-md font-semibold">{produksi.size?.name || '-'}</p>
                            </div>
                            <div className="space-y-3 pt-4 border-t border-zinc-100 dark:border-zinc-800">
                                <div className="space-y-1">
                                    <Label className="text-xs text-zinc-500 uppercase">Cutting Stage</Label>
                                    <div className="flex items-center gap-2 text-sm">
                                        <Calendar className="h-4 w-4 text-zinc-400" />
                                        <span>{produksi.potong_date}</span>
                                    </div>
                                    <div className="flex items-center gap-2 text-sm font-medium mt-1">
                                        <UserCircle className="h-4 w-4 text-zinc-400" />
                                        <span>{produksi.potong?.name || 'Unknown'}</span>
                                    </div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    {/* Right Column: Edit Forms */}
                    <div className="lg:col-span-2 space-y-8">
                        {/* Basic Info Form */}
                        <Card className="border-zinc-200 dark:border-zinc-800 shadow-sm">
                            <CardHeader>
                                <CardTitle className="text-lg">Basic Information</CardTitle>
                                <CardDescription>Update color, customer, and surat jalan details.</CardDescription>
                            </CardHeader>
                            <CardContent>
                                <form onSubmit={submitBasic} className="space-y-4">
                                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                        <div className="space-y-2">
                                            <Label htmlFor="warna">Warna</Label>
                                            <Input
                                                id="warna"
                                                value={basicForm.data.warna}
                                                onChange={(e) => basicForm.setData('warna', e.target.value)}
                                                placeholder="e.g. NAVY"
                                            />
                                        </div>
                                        <div className="space-y-2">
                                            <Label htmlFor="customer">Customer</Label>
                                            <Input
                                                id="customer"
                                                value={basicForm.data.customer}
                                                onChange={(e) => basicForm.setData('customer', e.target.value)}
                                                placeholder="e.g. CORE NATION"
                                            />
                                        </div>
                                    </div>
                                    <div className="space-y-2">
                                        <Label htmlFor="surat_jalan_potong">Surat Jalan Potong (SJP)</Label>
                                        <Input
                                            id="surat_jalan_potong"
                                            value={basicForm.data.surat_jalan_potong}
                                            onChange={(e) => basicForm.setData('surat_jalan_potong', e.target.value)}
                                            placeholder="SJP Number"
                                        />
                                    </div>
                                    <div className="pt-2">
                                        <Button type="submit" disabled={basicForm.processing} className="bg-blue-600 hover:bg-blue-700 font-bold">
                                            Save Changes
                                        </Button>
                                    </div>
                                </form>
                            </CardContent>
                        </Card>

                        {/* Split Production Form */}
                        <Card className="border-zinc-200 dark:border-zinc-800 shadow-md border-l-4 border-l-orange-500">
                            <CardHeader>
                                <CardTitle className="text-lg flex items-center gap-2">
                                    <Layers className="h-5 w-5 text-orange-500" />
                                    Pisah Jahit (Split)
                                </CardTitle>
                                <CardDescription>Split this record into two separate entries by specifying the split quantity.</CardDescription>
                            </CardHeader>
                            <CardContent>
                                <form onSubmit={submitSplit} className="flex flex-col sm:flex-row items-end gap-4">
                                    <div className="flex-1 space-y-2">
                                        <Label htmlFor="split_q">Quantity to Split</Label>
                                        <Input
                                            id="split_q"
                                            type="number"
                                            min="1"
                                            max={produksi.quantity - 1}
                                            value={splitForm.data.split_q}
                                            onChange={(e) => splitForm.setData('split_q', e.target.value)}
                                            placeholder={`Max split value: ${produksi.quantity - 1}`}
                                        />
                                        {splitForm.errors.split_q && <p className="text-xs text-red-500">{splitForm.errors.split_q}</p>}
                                    </div>
                                    <Button type="submit" variant="outline" disabled={splitForm.processing} className="border-orange-200 text-orange-700 hover:bg-orange-50 font-bold">
                                        Execute Split
                                    </Button>
                                </form>
                            </CardContent>
                        </Card>

                        {/* Edit Jahit Form */}
                        <Card className="border-zinc-200 dark:border-zinc-800 shadow-sm border-l-4 border-l-emerald-500">
                            <CardHeader>
                                <CardTitle className="text-lg flex items-center gap-2">
                                    <Scissors className="h-5 w-5 text-emerald-500" />
                                    Reassign Jahit
                                </CardTitle>
                                <CardDescription>Change the worker assigned to the sewing stage.</CardDescription>
                            </CardHeader>
                            <CardContent>
                                <form onSubmit={submitWorker} className="flex flex-col sm:flex-row items-end gap-4">
                                    <div className="flex-1 space-y-2">
                                        <Label>Select Jahit Worker</Label>
                                        <AsyncCombobox
                                            endpoint="/produksi/workers/lookup"
                                            additionalParams={{ type: 'jahit' }}
                                            placeholder="Search worker..."
                                            value={selectedJahitWorker}
                                            onChange={(worker) => {
                                                setSelectedJahitWorker(worker);
                                                workerForm.setData('jahit_id', worker?.id || '');
                                            }}
                                        />
                                    </div>
                                    <Button type="submit" disabled={workerForm.processing} className="bg-emerald-600 hover:bg-emerald-700 font-bold">
                                        Update Jahit
                                    </Button>
                                </form>
                            </CardContent>
                        </Card>

                        {/* Edit QC Form */}
                        <Card className="border-zinc-200 dark:border-zinc-800 shadow-sm border-l-4 border-l-blue-500">
                            <CardHeader>
                                <CardTitle className="text-lg flex items-center gap-2">
                                    <CheckCircle2 className="h-5 w-5 text-blue-500" />
                                    Reassign QC
                                </CardTitle>
                                <CardDescription>Change the worker assigned to the QC stage.</CardDescription>
                            </CardHeader>
                            <CardContent>
                                <form onSubmit={submitQcWorker} className="flex flex-col sm:flex-row items-end gap-4">
                                    <div className="flex-1 space-y-2">
                                        <Label>Select QC Worker</Label>
                                        <AsyncCombobox
                                            endpoint="/produksi/workers/lookup"
                                            additionalParams={{ type: 'qc' }}
                                            placeholder="Search worker..."
                                            value={selectedQcWorker}
                                            onChange={(worker) => {
                                                setSelectedQcWorker(worker);
                                                qcForm.setData('qc_id', worker?.id || '');
                                            }}
                                        />
                                    </div>
                                    <Button type="submit" disabled={qcForm.processing} className="bg-blue-600 hover:bg-blue-700 font-bold">
                                        Update QC
                                    </Button>
                                </form>
                            </CardContent>
                        </Card>


                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
