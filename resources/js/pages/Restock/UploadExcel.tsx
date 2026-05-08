import { Head, useForm } from '@inertiajs/react';
import { FileUp, Info } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Stuff', href: '#' },
    { title: 'Restock', href: '/restock' },
    { title: 'Upload Excel', href: '/restock/upload' },
];

export default function RestockUpload() {
    const { data, setData, post, processing, errors } = useForm({
        file: null as File | null,
        date: new Date().toISOString().split('T')[0],
        type: 'restocked',
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post('/restock/import');
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Upload Restock Excel" />
            <div className="p-4 sm:p-6 lg:p-8">
                <div className="mb-8 text-left">
                    <h1 className="mb-1 text-3xl font-bold tracking-tight text-zinc-900 dark:text-zinc-50">
                        Upload Restock Data
                    </h1>
                    <p className="text-zinc-500 dark:text-zinc-400">
                        Bulk import restock records from an Excel or CSV file.
                    </p>
                </div>

                <div className="max-w-xl">
                    <div className="rounded-xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-800 dark:bg-zinc-900/50 text-left">
                        <div className="mb-6 flex items-start gap-3 rounded-lg bg-zinc-50 p-4 text-sm text-zinc-600 dark:bg-zinc-800/50 dark:text-zinc-400">
                            <Info className="mt-0.5 h-4 w-4 shrink-0 text-blue-500" />
                            <div>
                                <p className="font-medium text-zinc-900 dark:text-zinc-100">Excel Format Instructions:</p>
                                <ul className="mt-1 list-inside list-disc space-y-1">
                                    <li>Column 1: Item ID or Code</li>
                                    <li>Column 2: Quantity</li>
                                    <li>Make sure the items already exist in the system.</li>
                                </ul>
                            </div>
                        </div>

                        <form onSubmit={handleSubmit} className="space-y-6">
                            <div className="space-y-2">
                                <Label htmlFor="file">Excel / CSV File</Label>
                                <Input
                                    id="file"
                                    type="file"
                                    onChange={(e) => setData('file', e.target.files ? e.target.files[0] : null)}
                                    accept=".xlsx,.csv"
                                    className="mt-1"
                                />
                                {errors.file && <p className="text-xs text-red-500">{errors.file}</p>}
                            </div>

                            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <div className="space-y-2">
                                    <Label htmlFor="type">Import Type</Label>
                                    <Select value={data.type} onValueChange={(val) => setData('type', val)}>
                                        <SelectTrigger id="type" className="mt-1">
                                            <SelectValue placeholder="Select type" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="restocked">Restocked (Initial)</SelectItem>
                                            <SelectItem value="production">Move to Production</SelectItem>
                                            <SelectItem value="shipped">Move to Shipped</SelectItem>
                                            <SelectItem value="missing">Missing</SelectItem>
                                        </SelectContent>
                                    </Select>
                                    {errors.type && <p className="text-xs text-red-500">{errors.type}</p>}
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor="date">Reference Date</Label>
                                    <Input
                                        id="date"
                                        type="date"
                                        value={data.date}
                                        onChange={(e) => setData('date', e.target.value)}
                                        className="mt-1"
                                    />
                                    {errors.date && <p className="text-xs text-red-500">{errors.date}</p>}
                                </div>
                            </div>

                            {errors.import && (
                                <div className="rounded-lg bg-red-50 p-3 text-sm text-red-600 dark:bg-red-900/20 dark:text-red-400 whitespace-pre-wrap">
                                    {errors.import}
                                </div>
                            )}

                            <Button type="submit" className="w-full bg-blue-600 text-white hover:bg-blue-700" disabled={processing || !data.file}>
                                <FileUp className="mr-2 h-4 w-4" /> Start Import
                            </Button>
                        </form>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
