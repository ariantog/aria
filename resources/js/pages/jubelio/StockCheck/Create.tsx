import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, Loader2 } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Pengecekan Stok',
        href: '/jubelio-stock-checks',
    },
    {
        title: 'Buat Pengecekan',
        href: '/jubelio-stock-checks/create',
    },
];

interface Props {
    activeJob: any;
}

export default function Create({ activeJob }: Props) {
    const { data, setData, post, processing, errors } = useForm({
        page_tracking: 1,
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post('/jubelio-stock-checks');
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Buat Pengecekan Stok Jubelio" />

            <div className="flex flex-col gap-6 p-4">
                <div className="flex items-center gap-4">
                    <Button variant="ghost" size="icon" asChild>
                        <Link href="/jubelio-stock-checks">
                            <ArrowLeft className="h-5 w-5" />
                        </Link>
                    </Button>
                    <h1 className="text-2xl font-bold">Buat Pengecekan Stok Baru</h1>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Konfigurasi Pengecekan</CardTitle>
                    </CardHeader>
                    <CardContent>
                        {activeJob ? (
                            <div className="rounded-lg bg-yellow-50 p-4 text-yellow-800 dark:bg-yellow-900/20 dark:text-yellow-400">
                                <p className="font-medium">Pengecekan Sedang Berjalan</p>
                                <p className="mt-1 text-sm">
                                    Terdapat pengecekan (ID: {activeJob.id}) yang sedang berstatus "{activeJob.status}". 
                                    Harap tunggu hingga pengecekan tersebut selesai sebelum membuat pengecekan baru.
                                </p>
                                <div className="mt-4">
                                    <Button asChild variant="outline">
                                        <Link href="/jubelio-stock-checks">Kembali ke Daftar</Link>
                                    </Button>
                                </div>
                            </div>
                        ) : (
                            <form onSubmit={handleSubmit} className="space-y-6">
                                <div className="space-y-2">
                                    <Label htmlFor="page_tracking">Mulai dari Halaman</Label>
                                    <Input
                                        id="page_tracking"
                                        type="number"
                                        min="1"
                                        value={data.page_tracking}
                                        onChange={(e) => setData('page_tracking', parseInt(e.target.value))}
                                        placeholder="Contoh: 1"
                                    />
                                    <p className="text-xs text-muted-foreground">
                                        Pengecekan akan dimulai dari halaman Jubelio ini (200 item per halaman).
                                    </p>
                                    {errors.page_tracking && (
                                        <p className="text-sm text-red-500">{errors.page_tracking}</p>
                                    )}
                                    {errors.active_job && (
                                        <p className="text-sm text-red-500">{errors.active_job}</p>
                                    )}
                                </div>

                                <div className="flex justify-end gap-3 pt-4">
                                    <Button variant="outline" asChild>
                                        <Link href="/jubelio-stock-checks">Batal</Link>
                                    </Button>
                                    <Button type="submit" disabled={processing}>
                                        {processing && <Loader2 className="mr-2 h-4 w-4 animate-spin" />}
                                        Mulai Pengecekan
                                    </Button>
                                </div>
                            </form>
                        )}
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
