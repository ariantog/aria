import { Head, Link, router } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { BreadcrumbItem } from '@/types';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { ArrowLeft, Edit, Plus, Trash2, Calendar, DollarSign } from 'lucide-react';
import { format } from 'date-fns';
import { id } from 'date-fns/locale';

export default function Show({ karyawan, auth }: any) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Karyawan', href: '/karyawan' },
        { title: karyawan.nama, href: `/karyawan/${karyawan.id}` },
    ];

    const isSuperAdmin = auth?.roles?.includes('superadmin');

    const formatCurrency = (value: number) => {
        return new Intl.NumberFormat('id-ID', {
            style: 'decimal',
            minimumFractionDigits: 0,
        }).format(value || 0);
    };

    const handleDeleteCuti = (cutiId: number) => {
        if (confirm('Yakin ingin menghapus data cuti ini?')) {
            router.delete(`/cuti/${cutiId}`);
        }
    };

    const getTipeCuti = (tipe: number) => {
        switch (tipe) {
            case 1: return <span className="text-blue-600">Tahunan</span>;
            case 2: return <span className="text-orange-600">Sakit</span>;
            case 3: return <span className="text-red-600">Mendadak/Izin</span>;
            default: return 'Lainnya';
        }
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Detail Karyawan - ${karyawan.nama}`} />

            <div className="flex h-full flex-1 flex-col gap-6 rounded-xl p-4 w-full">
                {/* Header Section */}
                <div className="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
                    <div className="flex items-center gap-4">
                        <Button variant="outline" size="icon" asChild>
                            <Link href="/karyawan">
                                <ArrowLeft className="h-4 w-4" />
                            </Link>
                        </Button>
                        <div>
                            <h1 className="text-2xl font-bold">{karyawan.nama}</h1>
                            <p className="text-muted-foreground">{karyawan.no_telp}</p>
                        </div>
                    </div>
                    <div className="flex items-center gap-2">
                        {/* Actions mapping based on permission */}
                        {(isSuperAdmin || karyawan.flag !== 2) && (
                            <>
                                <Button variant="outline" asChild>
                                    <Link href={`/karyawan/${karyawan.id}/edit`}>
                                        <Edit className="mr-2 h-4 w-4" /> Edit
                                    </Link>
                                </Button>
                                <Button asChild>
                                    <Link href={`/karyawan/${karyawan.id}/gaji/create`}>
                                        <DollarSign className="mr-2 h-4 w-4" /> Bikin Gaji
                                    </Link>
                                </Button>
                                <Button variant="secondary" asChild>
                                    <Link href={`/karyawan/${karyawan.id}/cuti/create`}>
                                        <Calendar className="mr-2 h-4 w-4" /> Tambah Cuti
                                    </Link>
                                </Button>
                            </>
                        )}
                    </div>
                </div>

                <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
                    {/* Detail Profile */}
                    <Card className="md:col-span-1">
                        <CardHeader>
                            <CardTitle>Profil Karyawan</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div>
                                <h4 className="text-sm font-medium text-muted-foreground">Alamat</h4>
                                <p className="mt-1 leading-relaxed">{karyawan.alamat || '-'}</p>
                            </div>
                            <div className="grid grid-cols-2 gap-4 pt-4 border-t">
                                <div>
                                    <h4 className="text-sm font-medium text-muted-foreground">Bank</h4>
                                    <p className="mt-1 font-medium">{karyawan.bank?.name || 'Kas Tunai'}</p>
                                </div>
                                <div>
                                    <h4 className="text-sm font-medium text-muted-foreground">Privasi</h4>
                                    <p className="mt-1 font-medium">{karyawan.flag == 1 ? 'Publik' : 'Private'}</p>
                                </div>
                            </div>
                            <div className="space-y-2 pt-4 border-t">
                                <div className="flex justify-between">
                                    <span className="text-sm text-muted-foreground">Gaji Bulanan</span>
                                    <span className="font-medium">{formatCurrency(karyawan.bulanan)}</span>
                                </div>
                                <div className="flex justify-between">
                                    <span className="text-sm text-muted-foreground">Gaji Harian</span>
                                    <span className="font-medium">{formatCurrency(karyawan.harian)}</span>
                                </div>
                                <div className="flex justify-between">
                                    <span className="text-sm text-muted-foreground">Premi / Tunjangan</span>
                                    <span className="font-medium">{formatCurrency(karyawan.premi)}</span>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    {/* History Section */}
                    <div className="md:col-span-2 space-y-6">
                        {/* Gaji History */}
                        <Card>
                            <CardHeader>
                                <CardTitle>Riwayat Gaji</CardTitle>
                                <CardDescription>Gaji bulan-bulan terakhir</CardDescription>
                            </CardHeader>
                            <CardContent className="p-0">
                                <div className="overflow-auto max-h-[300px]">
                                    <table className="w-full caption-bottom text-sm relative">
                                        <thead className="bg-gray-100 dark:bg-gray-800 sticky top-0 z-10">
                                            <tr className="border-b transition-colors">
                                                <th className="h-10 px-4 text-left font-medium text-muted-foreground">Periode</th>
                                                <th className="h-10 px-4 text-right font-medium text-muted-foreground">Total Gaji</th>
                                                <th className="h-10 px-4 text-center font-medium text-muted-foreground">Cuti (T/S/M)</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {karyawan.gaji?.length > 0 ? (
                                                karyawan.gaji.map((gaji: any) => (
                                                    <tr key={gaji.id} className="border-b transition-colors hover:bg-muted/50">
                                                        <td className="p-4 align-middle font-medium">
                                                            Bulan {gaji.bulan} / {gaji.tahun}
                                                        </td>
                                                        <td className="p-4 align-middle text-right font-bold text-green-700">
                                                            {formatCurrency(gaji.total_gaji)}
                                                        </td>
                                                        <td className="p-4 align-middle text-center text-muted-foreground">
                                                            {gaji.cuti_tahunan || 0} / {gaji.cuti_sakit || 0} / {gaji.cuti_mendadak || 0}
                                                        </td>
                                                    </tr>
                                                ))
                                            ) : (
                                                <tr>
                                                    <td colSpan={3} className="p-4 text-center text-muted-foreground">
                                                        Belum ada histori gaji.
                                                    </td>
                                                </tr>
                                            )}
                                        </tbody>
                                    </table>
                                </div>
                            </CardContent>
                        </Card>

                        {/* Cuti History */}
                        <Card>
                            <CardHeader>
                                <CardTitle>Riwayat Cuti</CardTitle>
                                <CardDescription>Cuti yang pernah diambil</CardDescription>
                            </CardHeader>
                            <CardContent className="p-0">
                                <div className="overflow-auto max-h-[300px]">
                                    <table className="w-full caption-bottom text-sm relative">
                                        <thead className="bg-gray-100 dark:bg-gray-800 sticky top-0 z-10">
                                            <tr className="border-b transition-colors">
                                                <th className="h-10 px-4 text-left font-medium text-muted-foreground">Tanggal</th>
                                                <th className="h-10 px-4 text-left font-medium text-muted-foreground">Tipe</th>
                                                <th className="h-10 px-4 text-center font-medium text-muted-foreground">Lama</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {karyawan.cuti?.length > 0 ? (
                                                karyawan.cuti.map((cuti: any) => (
                                                    <tr key={cuti.id} className="border-b transition-colors hover:bg-muted/50">
                                                        <td className="p-4 align-middle">
                                                            {format(new Date(cuti.tgl_mulai), 'dd MMM yyyy', { locale: id })}
                                                            {cuti.tgl_mulai !== cuti.tgl_akhir && ` - ${format(new Date(cuti.tgl_akhir), 'dd MMM yyyy', { locale: id })}`}
                                                        </td>
                                                        <td className="p-4 align-middle font-medium">
                                                            {getTipeCuti(cuti.tipe)}
                                                        </td>
                                                        <td className="p-4 align-middle text-center">
                                                            {(cuti.tahunan || 0) + (cuti.sakit || 0) + (cuti.mendadak || 0)} Hari
                                                        </td>
                                                    </tr>
                                                ))
                                            ) : (
                                                <tr>
                                                    <td colSpan={3} className="p-4 text-center text-muted-foreground">
                                                        Belum history pernah cuti.
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
