import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, Save } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
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

export default function Create({ karyawan }: any) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Karyawan', href: '/karyawan' },
        { title: karyawan.nama, href: `/karyawan/${karyawan.id}` },
        { title: 'Tambah Cuti', href: '#' },
    ];

    const { data, setData, post, processing, errors } = useForm({
        tgl_mulai: '',
        tgl_akhir: '',
        tipe: '1',
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        post(`/karyawan/${karyawan.id}/cuti`);
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Tambah Cuti - ${karyawan.nama}`} />

            <div className="flex h-full w-full flex-1 flex-col gap-4 rounded-xl p-4">
                <div className="mb-4 flex items-center gap-4">
                    <Button variant="outline" size="icon" asChild>
                        <Link href={`/karyawan/${karyawan.id}`}>
                            <ArrowLeft className="h-4 w-4" />
                        </Link>
                    </Button>
                    <h1 className="text-2xl font-bold">
                        Tambah Cuti - {karyawan.nama}
                    </h1>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Formulir Cuti Baru</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={submit} className="space-y-6">
                            <div className="space-y-4">
                                <div className="space-y-2">
                                    <Label htmlFor="tipe">Tipe Cuti</Label>
                                    <Select
                                        value={data.tipe}
                                        onValueChange={(val) =>
                                            setData('tipe', val)
                                        }
                                    >
                                        <SelectTrigger>
                                            <SelectValue placeholder="Pilih Tipe Cuti" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="1">
                                                Cuti Tahunan
                                            </SelectItem>
                                            <SelectItem value="2">
                                                Cuti Sakit
                                            </SelectItem>
                                            <SelectItem value="3">
                                                Cuti Mendadak / Izin
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                    {errors.tipe && (
                                        <p className="text-sm text-red-600">
                                            {errors.tipe}
                                        </p>
                                    )}
                                </div>

                                <div className="grid grid-cols-2 gap-4">
                                    <div className="space-y-2">
                                        <Label htmlFor="tgl_mulai">
                                            Tanggal Mulai
                                        </Label>
                                        <Input
                                            id="tgl_mulai"
                                            type="date"
                                            value={data.tgl_mulai}
                                            onChange={(e) =>
                                                setData(
                                                    'tgl_mulai',
                                                    e.target.value,
                                                )
                                            }
                                        />
                                        {errors.tgl_mulai && (
                                            <p className="text-sm text-red-600">
                                                {errors.tgl_mulai}
                                            </p>
                                        )}
                                    </div>
                                    <div className="space-y-2">
                                        <Label htmlFor="tgl_akhir">
                                            Tanggal Akhir
                                        </Label>
                                        <Input
                                            id="tgl_akhir"
                                            type="date"
                                            value={data.tgl_akhir}
                                            onChange={(e) =>
                                                setData(
                                                    'tgl_akhir',
                                                    e.target.value,
                                                )
                                            }
                                        />
                                        {errors.tgl_akhir && (
                                            <p className="text-sm text-red-600">
                                                {errors.tgl_akhir}
                                            </p>
                                        )}
                                    </div>
                                </div>
                            </div>

                            <div className="flex justify-end gap-2 border-t pt-4">
                                <Button type="button" variant="outline" asChild>
                                    <Link href={`/karyawan/${karyawan.id}`}>
                                        Batal
                                    </Link>
                                </Button>
                                <Button type="submit" disabled={processing}>
                                    <Save className="mr-2 h-4 w-4" /> Simpan
                                    Cuti
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
