import { Head, Link, useForm } from '@inertiajs/react';
import axios from 'axios';
import { Save, Search, RefreshCw, ArrowLeft, Loader2 } from 'lucide-react';
import { useState, useEffect } from 'react';
import { toast } from 'sonner';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardHeader,
    CardTitle,
    CardDescription,
    CardFooter,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';

export default function Create({ jahitList, defaultFrom, defaultTo }: any) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Borongan', href: '/borongan' },
        { title: 'Tambah Borongan', href: '/borongan/create' },
    ];

    const { data, setData, post, processing, errors } = useForm({
        from: defaultFrom,
        to: defaultTo,
        jahit_id: '',
        permak: 0,
        tres: 0,
        lain2: 0,
    });

    const [isLoadingItems, setIsLoadingItems] = useState(false);
    const [boronganItems, setBoronganItems] = useState<any[]>([]);

    const fetchBoronganItems = async () => {
        if (!data.from || !data.to || !data.jahit_id) {
            toast.warning('Peringatan', {
                description:
                    'Harap lengkapi Filter (Tgl & Penjahit) terlebih dahulu.',
            });
            return;
        }

        setIsLoadingItems(true);
        setBoronganItems([]);

        try {
            const response = await axios.get('/borongan/ajax', {
                params: {
                    from: data.from,
                    to: data.to,
                    jahit_id: data.jahit_id,
                },
            });

            if (response.data && response.data.length > 0) {
                setBoronganItems(response.data);
                toast.success('Berhasil', {
                    description: `${response.data.length} item Produksi ditemukan.`,
                });
            } else {
                toast.info('Info', {
                    description:
                        'Tidak ada data produksi (Status Gudang) untuk filter tersebut.',
                });
            }
        } catch (error: any) {
            console.error('Error fetching borongan items:', error);
            const msg =
                error?.response?.data?.error ||
                'Gagal memuat data dari server.';
            toast.error('Error', { description: msg });
        } finally {
            setIsLoadingItems(false);
        }
    };

    useEffect(() => {
        if (data.from && data.to && data.jahit_id) {
            fetchBoronganItems();
        }
    }, [data.jahit_id]); // Auto run when worker is selected

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        if (boronganItems.length === 0) {
            toast.error('Gagal', {
                description: 'Tidak ada item yang bisa disimpan.',
            });
            return;
        }

        post('/borongan');
    };

    const formatCurrency = (value: number) => {
        return new Intl.NumberFormat('id-ID', {
            style: 'currency',
            currency: 'IDR',
            minimumFractionDigits: 0,
        }).format(value);
    };

    // Kalkulasi Total Item
    const subTotalItem = boronganItems.reduce(
        (acc, curr) => acc + (parseFloat(curr.total) || 0),
        0,
    );
    const grandTotal =
        subTotalItem +
        (parseFloat(String(data.permak)) || 0) +
        (parseFloat(String(data.tres)) || 0) +
        (parseFloat(String(data.lain2)) || 0);
    const totalQty = boronganItems.reduce(
        (acc, curr) => acc + (parseInt(curr.quantity) || 0),
        0,
    );

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Tambah Borongan" />

            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <div className="flex items-center justify-between">
                    <h1 className="text-2xl font-bold tracking-tight">
                        Tambah Borongan
                    </h1>
                    <Button variant="outline" asChild>
                        <Link href="/borongan">
                            <ArrowLeft className="mr-2 h-4 w-4" /> Kembali
                        </Link>
                    </Button>
                </div>

                <div className="grid grid-cols-1 items-start gap-4 md:grid-cols-4">
                    {/* Filter & Form Kiri */}
                    <div className="space-y-4 md:col-span-1">
                        <Card>
                            <CardHeader>
                                <CardTitle>Pengaturan Filter</CardTitle>
                                <CardDescription>
                                    Pilih rentang tanggal & Penjahit.
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <div className="space-y-2">
                                    <Label htmlFor="from">Dari Tanggal *</Label>
                                    <Input
                                        id="from"
                                        type="date"
                                        value={data.from}
                                        onChange={(e) =>
                                            setData('from', e.target.value)
                                        }
                                    />
                                    {errors.from && (
                                        <p className="text-sm text-destructive">
                                            {errors.from}
                                        </p>
                                    )}
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor="to">Sampai Tanggal *</Label>
                                    <Input
                                        id="to"
                                        type="date"
                                        value={data.to}
                                        onChange={(e) =>
                                            setData('to', e.target.value)
                                        }
                                    />
                                    {errors.to && (
                                        <p className="text-sm text-destructive">
                                            {errors.to}
                                        </p>
                                    )}
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor="jahit_id">Penjahit *</Label>
                                    <Select
                                        value={data.jahit_id.toString()}
                                        onValueChange={(val) =>
                                            setData('jahit_id', val)
                                        }
                                    >
                                        <SelectTrigger>
                                            <SelectValue placeholder="Pilih Penjahit..." />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {jahitList.map((j: any) => (
                                                <SelectItem
                                                    key={j.id}
                                                    value={j.id.toString()}
                                                >
                                                    {j.name}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    {errors.jahit_id && (
                                        <p className="text-sm text-destructive">
                                            {errors.jahit_id}
                                        </p>
                                    )}
                                </div>

                                <Button
                                    className="w-full"
                                    onClick={fetchBoronganItems}
                                    disabled={isLoadingItems}
                                    variant="secondary"
                                >
                                    {isLoadingItems ? (
                                        <>
                                            <Loader2 className="mr-2 h-4 w-4 animate-spin" />{' '}
                                            Memuat Data...
                                        </>
                                    ) : (
                                        <>
                                            <RefreshCw className="mr-2 h-4 w-4" />{' '}
                                            Cari Baris Item
                                        </>
                                    )}
                                </Button>
                            </CardContent>
                        </Card>

                        <Card>
                            <form onSubmit={handleSubmit}>
                                <CardHeader>
                                    <CardTitle>Biaya Tambahan</CardTitle>
                                    <CardDescription>
                                        Potongan/Tambahan Ongkos.
                                    </CardDescription>
                                </CardHeader>
                                <CardContent className="space-y-4">
                                    <div className="space-y-2">
                                        <Label htmlFor="permak">Permak</Label>
                                        <Input
                                            id="permak"
                                            type="number"
                                            min="0"
                                            value={data.permak}
                                            onChange={(e) =>
                                                setData(
                                                    'permak',
                                                    parseFloat(
                                                        e.target.value,
                                                    ) || 0,
                                                )
                                            }
                                        />
                                        {errors.permak && (
                                            <p className="text-sm text-destructive">
                                                {errors.permak}
                                            </p>
                                        )}
                                    </div>
                                    <div className="space-y-2">
                                        <Label htmlFor="tres">Tres</Label>
                                        <Input
                                            id="tres"
                                            type="number"
                                            min="0"
                                            value={data.tres}
                                            onChange={(e) =>
                                                setData(
                                                    'tres',
                                                    parseFloat(
                                                        e.target.value,
                                                    ) || 0,
                                                )
                                            }
                                        />
                                        {errors.tres && (
                                            <p className="text-sm text-destructive">
                                                {errors.tres}
                                            </p>
                                        )}
                                    </div>
                                    <div className="space-y-2">
                                        <Label htmlFor="lain2">Lain-Lain</Label>
                                        <Input
                                            id="lain2"
                                            type="number"
                                            min="0"
                                            value={data.lain2}
                                            onChange={(e) =>
                                                setData(
                                                    'lain2',
                                                    parseFloat(
                                                        e.target.value,
                                                    ) || 0,
                                                )
                                            }
                                        />
                                        {errors.lain2 && (
                                            <p className="text-sm text-destructive">
                                                {errors.lain2}
                                            </p>
                                        )}
                                    </div>
                                </CardContent>
                                <CardFooter className="mt-4 flex-col gap-2 rounded-b-xl border-t bg-muted/30 pt-4 pb-4">
                                    <div className="flex w-full justify-between text-sm font-medium">
                                        <span>Subtotal Item:</span>
                                        <span>
                                            {formatCurrency(subTotalItem)}
                                        </span>
                                    </div>
                                    <div className="mt-2 flex w-full justify-between border-t pt-2 text-lg font-bold">
                                        <span>Grand Total:</span>
                                        <span className="text-primary">
                                            {formatCurrency(grandTotal)}
                                        </span>
                                    </div>

                                    <Button
                                        type="submit"
                                        className="mt-4 w-full"
                                        disabled={
                                            processing ||
                                            boronganItems.length === 0
                                        }
                                    >
                                        {processing
                                            ? 'Menyimpan...'
                                            : 'Simpan Pembayaran'}{' '}
                                        <Save className="ml-2 h-4 w-4" />
                                    </Button>
                                </CardFooter>
                            </form>
                        </Card>
                    </div>

                    {/* Rincian Item Kanan */}
                    <div className="md:col-span-3">
                        <Card className="h-full">
                            <CardHeader className="flex flex-row items-center justify-between border-b pb-2">
                                <CardTitle className="text-base font-semibold">
                                    Tabel Rincian Item (Status Gudang)
                                </CardTitle>
                                <div className="text-sm font-medium text-muted-foreground">
                                    {totalQty} baris ditemukan
                                </div>
                            </CardHeader>
                            <CardContent className="p-0">
                                <div className="relative max-h-[600px] w-full overflow-auto">
                                    <table className="relative w-full caption-bottom text-sm">
                                        <thead className="sticky top-0 z-10 bg-background/95 shadow-sm backdrop-blur [&_tr]:border-b">
                                            <tr className="border-b transition-colors">
                                                <th className="h-10 w-12 px-4 text-left align-middle font-medium text-muted-foreground">
                                                    No
                                                </th>
                                                <th className="h-10 px-4 text-left align-middle font-medium text-muted-foreground">
                                                    Serial Prod
                                                </th>
                                                <th className="h-10 px-4 text-left align-middle font-medium text-muted-foreground">
                                                    Kode Item / Temp
                                                </th>
                                                <th className="h-10 w-20 px-4 text-right align-middle font-medium text-muted-foreground">
                                                    Qty
                                                </th>
                                                <th className="h-10 w-32 px-4 text-right align-middle font-medium text-muted-foreground">
                                                    Ongkos Satuan
                                                </th>
                                                <th className="h-10 w-32 px-4 text-right align-middle font-medium text-muted-foreground">
                                                    Total
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody className="[&_tr:last-child]:border-0">
                                            {isLoadingItems ? (
                                                <tr>
                                                    <td
                                                        colSpan={6}
                                                        className="p-8 text-center text-muted-foreground"
                                                    >
                                                        <Loader2 className="mx-auto h-6 w-6 animate-spin text-muted-foreground/50" />
                                                    </td>
                                                </tr>
                                            ) : boronganItems.length > 0 ? (
                                                boronganItems.map(
                                                    (
                                                        item: any,
                                                        idx: number,
                                                    ) => (
                                                        <tr
                                                            key={
                                                                item.produksi_id
                                                            }
                                                            className="border-b transition-colors hover:bg-muted/50"
                                                        >
                                                            <td className="p-3 align-middle">
                                                                {idx + 1}
                                                            </td>
                                                            <td className="p-3 align-middle">
                                                                <a
                                                                    href={
                                                                        item.edit_link
                                                                    }
                                                                    target="_blank"
                                                                    rel="noopener noreferrer"
                                                                    className="font-mono text-primary hover:underline"
                                                                >
                                                                    {
                                                                        item.serial
                                                                    }
                                                                </a>
                                                            </td>
                                                            <td className="p-3 align-middle font-medium">
                                                                {item.code}{' '}
                                                                {item.item ? (
                                                                    <span className="ml-2 text-xs text-muted-foreground">
                                                                        (
                                                                        {
                                                                            item
                                                                                .item
                                                                                .name
                                                                        }
                                                                        )
                                                                    </span>
                                                                ) : (
                                                                    ''
                                                                )}
                                                            </td>
                                                            <td className="p-3 text-right align-middle">
                                                                {item.quantity}
                                                            </td>
                                                            <td className="p-3 text-right align-middle">
                                                                {formatCurrency(
                                                                    item.ongkos,
                                                                )}
                                                            </td>
                                                            <td className="p-3 text-right align-middle font-semibold">
                                                                {formatCurrency(
                                                                    item.total,
                                                                )}
                                                            </td>
                                                        </tr>
                                                    ),
                                                )
                                            ) : (
                                                <tr>
                                                    <td
                                                        colSpan={6}
                                                        className="p-8 text-center text-muted-foreground"
                                                    >
                                                        <span className="mb-2 block text-xl">
                                                            📥
                                                        </span>
                                                        Pilih Filter Tanggal dan
                                                        Penjahit untuk melihat
                                                        daftar item produksi
                                                        yang sudah disetor
                                                        (Gudang).
                                                    </td>
                                                </tr>
                                            )}
                                        </tbody>
                                    </table>
                                </div>
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
