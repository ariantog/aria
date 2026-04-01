import { Head, Link, useForm } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { BreadcrumbItem } from '@/types';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from "@/components/ui/select"
import { ArrowLeft, Save } from 'lucide-react';

export default function Form({ karyawan, banks }: any) {
    const isEdit = !!karyawan;

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Karyawan', href: '/karyawan' },
        { title: isEdit ? 'Edit Karyawan' : 'Tambah Karyawan', href: '#' },
    ];

    const { data, setData, post, put, processing, errors } = useForm({
        nama: karyawan?.nama || '',
        alamat: karyawan?.alamat || '',
        no_telp: karyawan?.no_telp || '',
        bulanan: karyawan?.bulanan || '',
        harian: karyawan?.harian || '',
        premi: karyawan?.premi || '',
        bank_id: karyawan?.bank_id?.toString() || '',
        flag: karyawan?.flag?.toString() || '1',
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        if (isEdit) {
            put(`/karyawan/${karyawan.id}`);
        } else {
            post('/karyawan');
        }
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={isEdit ? 'Edit Karyawan' : 'Tambah Karyawan'} />

            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4 w-full">
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-4">
                        <Button variant="outline" size="icon" asChild>
                            <Link href="/karyawan">
                                <ArrowLeft className="h-4 w-4" />
                            </Link>
                        </Button>
                        <h1 className="text-2xl font-bold">{isEdit ? 'Edit Karyawan' : 'Tambah Karyawan'}</h1>
                    </div>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Informasi Karyawan</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={submit} className="space-y-6">
                            <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div className="space-y-2">
                                    <Label htmlFor="nama">Nama Lengkap</Label>
                                    <Input
                                        id="nama"
                                        value={data.nama}
                                        onChange={(e) => setData('nama', e.target.value)}
                                        placeholder="Nama Karyawan"
                                    />
                                    {errors.nama && <p className="text-sm text-red-600">{errors.nama}</p>}
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="no_telp">No. Telepon</Label>
                                    <Input
                                        id="no_telp"
                                        value={data.no_telp}
                                        onChange={(e) => setData('no_telp', e.target.value)}
                                        placeholder="08123456789"
                                    />
                                    {errors.no_telp && <p className="text-sm text-red-600">{errors.no_telp}</p>}
                                </div>

                                <div className="space-y-2 md:col-span-2">
                                    <Label htmlFor="alamat">Alamat Lengkap</Label>
                                    <Textarea
                                        id="alamat"
                                        value={data.alamat}
                                        onChange={(e) => setData('alamat', e.target.value)}
                                        placeholder="Alamat domisili"
                                        rows={3}
                                    />
                                    {errors.alamat && <p className="text-sm text-red-600">{errors.alamat}</p>}
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="bulanan">Gaji Bulanan (Rp)</Label>
                                    <Input
                                        id="bulanan"
                                        type="number"
                                        value={data.bulanan}
                                        onChange={(e) => setData('bulanan', e.target.value)}
                                        placeholder="Misal: 3000000"
                                    />
                                    {errors.bulanan && <p className="text-sm text-red-600">{errors.bulanan}</p>}
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="harian">Gaji Harian (Rp)</Label>
                                    <Input
                                        id="harian"
                                        type="number"
                                        value={data.harian}
                                        onChange={(e) => setData('harian', e.target.value)}
                                        placeholder="Misal: 100000"
                                    />
                                    {errors.harian && <p className="text-sm text-red-600">{errors.harian}</p>}
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="premi">Premi (Rp)</Label>
                                    <Input
                                        id="premi"
                                        type="number"
                                        value={data.premi}
                                        onChange={(e) => setData('premi', e.target.value)}
                                        placeholder="Misal: 500000"
                                    />
                                    {errors.premi && <p className="text-sm text-red-600">{errors.premi}</p>}
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="bank_id">Rekening Bank</Label>
                                    <Select
                                        value={data.bank_id}
                                        onValueChange={(val) => setData('bank_id', val)}
                                    >
                                        <SelectTrigger>
                                            <SelectValue placeholder="Pilih Akun Bank" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {banks.map((bank: any) => (
                                                <SelectItem key={bank.id} value={bank.id.toString()}>
                                                    {bank.name}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    {errors.bank_id && <p className="text-sm text-red-600">{errors.bank_id}</p>}
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="flag">Status Publikasi / Privasi</Label>
                                    <Select
                                        value={data.flag}
                                        onValueChange={(val) => setData('flag', val)}
                                    >
                                        <SelectTrigger>
                                            <SelectValue placeholder="Pilih status privasi" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="1">Publik (Bisa Dilihat Kasir)</SelectItem>
                                            <SelectItem value="2">Private (Hanya Admin)</SelectItem>
                                        </SelectContent>
                                    </Select>
                                    {errors.flag && <p className="text-sm text-red-600">{errors.flag}</p>}
                                </div>
                            </div>

                            <div className="flex justify-end gap-2 pt-4 border-t">
                                <Button type="button" variant="outline" asChild>
                                    <Link href="/karyawan">Batal</Link>
                                </Button>
                                <Button type="submit" disabled={processing}>
                                    <Save className="mr-2 h-4 w-4" />
                                    {processing ? 'Menyimpan...' : 'Simpan Karyawan'}
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
