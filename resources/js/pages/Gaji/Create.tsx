import { Head, Link, useForm } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { BreadcrumbItem } from '@/types';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { ArrowLeft, Save, AlertTriangle } from 'lucide-react';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';

export default function Create({
    karyawan,
    now,
    gajiData,
    cutiBulanIni,
    dendaCutiTahunan,
    dendaCutiSakit,
    potongPremi,
    grandTotalDendaCuti,
    grandTotalDendaCutiRupiah,
}: any) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Karyawan', href: '/karyawan' },
        { title: karyawan.nama, href: `/karyawan/${karyawan.id}` },
        { title: 'Bikin Gaji', href: '#' },
    ];

    const { data, setData, post, processing, errors } = useForm({
        bulan: now.month.toString(),
        tahun: now.year.toString(),
        total_cuti_tahunan: cutiBulanIni.tahunan,
        total_cuti_sakit: cutiBulanIni.sakit,
        total_cuti_mendadak: cutiBulanIni.mendadak,
        potong_bulanan: grandTotalDendaCutiRupiah,
        potong_premi: potongPremi,
        bonus: 0,
        sanksi: 0,
        privasi: '1',
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        post(`/karyawan/${karyawan.id}/gaji`);
    };

    const formatCurrency = (value: number) => {
        return new Intl.NumberFormat('id-ID', {
            style: 'decimal',
            minimumFractionDigits: 0,
        }).format(value || 0);
    };

    const rupiahHarian = karyawan.harian * 26;
    const totalGajiHk =
        rupiahHarian +
        Number(karyawan.bulanan) +
        Number(karyawan.premi) +
        Number(data.bonus);
    const totalPotongan =
        Number(data.potong_bulanan) +
        Number(data.potong_premi) +
        Number(data.sanksi);
    const gajiAkhir = totalGajiHk - totalPotongan;

    const monthNames = [
        'Januari',
        'Februari',
        'Maret',
        'April',
        'Mei',
        'Juni',
        'Juli',
        'Agustus',
        'September',
        'Oktober',
        'November',
        'Desember',
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Bikin Gaji - ${karyawan.nama}`} />

            <div className="flex h-full w-full flex-1 flex-col gap-6 rounded-xl p-4">
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-4">
                        <Button variant="outline" size="icon" asChild>
                            <Link href={`/karyawan/${karyawan.id}`}>
                                <ArrowLeft className="h-4 w-4" />
                            </Link>
                        </Button>
                        <div>
                            <h1 className="text-2xl font-bold">
                                Bikin Gaji: {karyawan.nama}
                            </h1>
                        </div>
                    </div>
                </div>

                {gajiData ? (
                    <div className="space-y-6">
                        <div className="flex gap-3 rounded-lg border border-amber-200 bg-amber-50 p-4 dark:border-amber-900 dark:bg-amber-950/20">
                            <AlertTriangle className="h-5 w-5 shrink-0 text-amber-600 dark:text-amber-500" />
                            <div>
                                <p className="leading-tight font-bold text-amber-900 dark:text-amber-400">
                                    Perhatian: Data Gaji Ganda
                                </p>
                                <p className="mt-1 text-sm text-amber-800 dark:text-amber-500">
                                    Gaji untuk periode Bulan {gajiData.bulan},{' '}
                                    {gajiData.tahun} sudah pernah dibuat.
                                    Hubungi Admin jika ingin melakukan
                                    penyesuaian.
                                </p>
                            </div>
                        </div>

                        <Card className="max-w-2xl border-emerald-200 bg-emerald-50/30 dark:border-emerald-900 dark:bg-emerald-950/10">
                            <CardHeader>
                                <CardTitle className="text-emerald-900 dark:text-emerald-400">
                                    Rincian Gaji Tersimpan
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-6">
                                <div className="grid grid-cols-2 gap-4 text-sm">
                                    <div className="space-y-1">
                                        <p className="text-muted-foreground">
                                            Gaji Pokok & Harian
                                        </p>
                                        <p className="font-semibold">
                                            {formatCurrency(
                                                Number(gajiData.bulanan) +
                                                    Number(gajiData.harian),
                                            )}
                                        </p>
                                    </div>
                                    <div className="space-y-1">
                                        <p className="text-muted-foreground">
                                            Premi / Tunjangan
                                        </p>
                                        <p className="font-semibold">
                                            {formatCurrency(gajiData.premi)}
                                        </p>
                                    </div>
                                    <div className="space-y-1">
                                        <p className="text-muted-foreground">
                                            Bonus / Insentif
                                        </p>
                                        <p className="font-semibold text-emerald-600">
                                            +{formatCurrency(gajiData.bonus)}
                                        </p>
                                    </div>
                                    <div className="space-y-1">
                                        <p className="text-muted-foreground">
                                            Total Potongan (Cuti/Premi/Sanksi)
                                        </p>
                                        <p className="font-semibold text-red-600">
                                            -
                                            {formatCurrency(
                                                Number(
                                                    gajiData.total_potongan,
                                                ) + Number(gajiData.sanksi),
                                            )}
                                        </p>
                                    </div>
                                </div>

                                <div className="flex items-center justify-between border-t pt-4">
                                    <span className="text-lg font-bold">
                                        Total Gaji Akhir
                                    </span>
                                    <span className="text-2xl font-black text-emerald-700 dark:text-emerald-400">
                                        Rp {formatCurrency(gajiData.total_gaji)}
                                    </span>
                                </div>

                                <div className="border-t pt-4">
                                    <Button
                                        variant="outline"
                                        asChild
                                        className="w-full"
                                    >
                                        <Link href={`/karyawan/${karyawan.id}`}>
                                            Kembali ke Profil
                                        </Link>
                                    </Button>
                                </div>
                            </CardContent>
                        </Card>
                    </div>
                ) : (
                    <div className="grid grid-cols-1 gap-6 md:grid-cols-3">
                        {/* Ringkasan Gaji & Cuti */}
                        <Card className="border-blue-200 bg-blue-50/50 md:col-span-1 dark:border-blue-900 dark:bg-blue-950/20">
                            <CardHeader className="pb-4">
                                <CardTitle className="text-lg text-zinc-900 dark:text-zinc-100">
                                    Kalkulasi Sistem
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-4 text-sm">
                                <div>
                                    <h4 className="mb-2 font-medium text-zinc-900 dark:text-zinc-100">
                                        Rincian Gaji
                                    </h4>
                                    <div className="space-y-1">
                                        <div className="flex justify-between">
                                            <span className="text-muted-foreground">
                                                Bulanan
                                            </span>
                                            <span className="font-semibold text-zinc-800 dark:text-zinc-200">
                                                {formatCurrency(
                                                    karyawan.bulanan,
                                                )}
                                            </span>
                                        </div>
                                        <div className="flex justify-between">
                                            <span className="text-muted-foreground">
                                                Harian x 26 Hari
                                            </span>
                                            <span className="font-semibold text-zinc-800 dark:text-zinc-200">
                                                {formatCurrency(rupiahHarian)}
                                            </span>
                                        </div>
                                        <div className="flex justify-between">
                                            <span className="text-muted-foreground">
                                                Premi
                                            </span>
                                            <span className="font-semibold text-zinc-800 dark:text-zinc-200">
                                                {formatCurrency(karyawan.premi)}
                                            </span>
                                        </div>
                                    </div>
                                </div>

                                <div className="mt-2 border-t border-blue-200 pt-4 dark:border-blue-900/50">
                                    <h4 className="mb-2 font-medium text-zinc-900 dark:text-zinc-100">
                                        Rincian Cuti (Bulan Ini)
                                    </h4>
                                    <div className="space-y-1">
                                        <div className="flex justify-between">
                                            <span className="text-muted-foreground">
                                                Tahunan
                                            </span>
                                            <span className="text-zinc-800 dark:text-zinc-200">
                                                {cutiBulanIni.tahunan} Hari
                                            </span>
                                        </div>
                                        <div className="flex justify-between">
                                            <span className="text-muted-foreground">
                                                Sakit
                                            </span>
                                            <span className="text-zinc-800 dark:text-zinc-200">
                                                {cutiBulanIni.sakit} Hari
                                            </span>
                                        </div>
                                        <div className="flex justify-between">
                                            <span className="text-muted-foreground">
                                                Mendadak
                                            </span>
                                            <span className="text-zinc-800 dark:text-zinc-200">
                                                {cutiBulanIni.mendadak} Hari
                                            </span>
                                        </div>
                                    </div>
                                </div>

                                <div className="mt-2 border-t border-red-100 pt-4 dark:border-red-900/20">
                                    <h4 className="mb-2 font-medium text-red-600 dark:text-red-400">
                                        Cuti Melewati Batas Tahunan
                                    </h4>
                                    <div className="space-y-1">
                                        <div className="flex justify-between">
                                            <span className="text-muted-foreground">
                                                Tahunan
                                            </span>
                                            <span className="font-medium text-red-600 dark:text-red-400">
                                                {dendaCutiTahunan} Hari (Denda)
                                            </span>
                                        </div>
                                        <div className="flex justify-between">
                                            <span className="text-muted-foreground">
                                                Sakit
                                            </span>
                                            <span className="font-medium text-red-600 dark:text-red-400">
                                                {dendaCutiSakit} Hari (Denda)
                                            </span>
                                        </div>
                                        <div className="flex justify-between">
                                            <span className="text-muted-foreground">
                                                Mendadak
                                            </span>
                                            <span className="font-medium text-red-600 dark:text-red-400">
                                                {cutiBulanIni.mendadak} Hari
                                                (Potong)
                                            </span>
                                        </div>
                                    </div>
                                </div>

                                <div className="mt-2 border-t border-blue-200 pt-4 dark:border-blue-900/50">
                                    <div className="flex items-center justify-between rounded bg-blue-100 p-2 dark:bg-blue-900/40">
                                        <span className="font-bold text-blue-900 dark:text-blue-100">
                                            Total Take Home Pay
                                        </span>
                                        <span className="text-lg font-bold text-blue-700 dark:text-blue-300">
                                            {formatCurrency(gajiAkhir)}
                                        </span>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>

                        {/* Form Input */}
                        <Card className="md:col-span-2">
                            <CardHeader>
                                <CardTitle>Data Penggajian</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <form onSubmit={submit} className="space-y-6">
                                    <div className="rounded-lg border border-border/50 bg-muted/30 p-4">
                                        <h3 className="mb-1 text-sm font-medium text-muted-foreground">
                                            Periode Penggajian
                                        </h3>
                                        <p className="text-xl font-bold tracking-tight">
                                            {monthNames[Number(data.bulan) - 1]}{' '}
                                            {data.tahun}
                                        </p>
                                    </div>

                                    <div className="grid grid-cols-1 gap-6 border-t pt-4 sm:grid-cols-2">
                                        <div className="space-y-4">
                                            <h4 className="font-medium">
                                                Tambahan
                                            </h4>
                                            <div className="space-y-2">
                                                <Label htmlFor="bonus">
                                                    Bonus / Insentif (Rp)
                                                </Label>
                                                <Input
                                                    id="bonus"
                                                    type="number"
                                                    value={data.bonus}
                                                    onChange={(e) =>
                                                        setData(
                                                            'bonus',
                                                            Number(
                                                                e.target.value,
                                                            ),
                                                        )
                                                    }
                                                />
                                                {errors.bonus && (
                                                    <p className="text-sm text-red-600">
                                                        {errors.bonus}
                                                    </p>
                                                )}
                                            </div>
                                        </div>

                                        <div className="space-y-4">
                                            <h4 className="font-medium text-red-600">
                                                Potongan & Sanksi
                                            </h4>
                                            <div className="space-y-2">
                                                <Label htmlFor="sanksi">
                                                    Sanksi Lainnya (Rp)
                                                </Label>
                                                <Input
                                                    id="sanksi"
                                                    type="number"
                                                    value={data.sanksi}
                                                    onChange={(e) =>
                                                        setData(
                                                            'sanksi',
                                                            Number(
                                                                e.target.value,
                                                            ),
                                                        )
                                                    }
                                                />
                                            </div>
                                        </div>
                                    </div>

                                    <div className="space-y-2 border-t pt-4">
                                        <Label htmlFor="privasi">
                                            Status Publikasi
                                        </Label>
                                        <Select
                                            value={data.privasi}
                                            onValueChange={(val) =>
                                                setData('privasi', val)
                                            }
                                        >
                                            <SelectTrigger>
                                                <SelectValue placeholder="Pilih status privasi" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="1">
                                                    Publik
                                                </SelectItem>
                                                <SelectItem value="2">
                                                    Private (Rahasia)
                                                </SelectItem>
                                            </SelectContent>
                                        </Select>
                                        {errors.privasi && (
                                            <p className="text-sm text-red-600">
                                                {errors.privasi}
                                            </p>
                                        )}
                                    </div>

                                    <div className="flex justify-end gap-2 border-t pt-4">
                                        <Button
                                            type="button"
                                            variant="outline"
                                            asChild
                                        >
                                            <Link
                                                href={`/karyawan/${karyawan.id}`}
                                            >
                                                Batal
                                            </Link>
                                        </Button>
                                        <Button
                                            type="submit"
                                            disabled={processing}
                                        >
                                            <Save className="mr-2 h-4 w-4" />
                                            {processing
                                                ? 'Menyimpan...'
                                                : 'Simpan Data Gaji'}
                                        </Button>
                                    </div>
                                </form>
                            </CardContent>
                        </Card>
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
