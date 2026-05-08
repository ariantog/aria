import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, Loader2, Store, MapPin } from 'lucide-react';
import { AsyncCombobox } from '@/components/AsyncCombobox';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';

interface SyncMapping {
    id: number;
    jubelio_store_name: string;
    jubelio_location_name: string;
    warehouse_id: number;
    customer_id: number;
    warehouse?: {
        id: number;
        name: string;
    };
    customer?: {
        id: number;
        name: string;
    };
}

interface Props {
    sync: SyncMapping;
    addrbookTypes: {
        warehouse: number;
        customer: number;
    };
}

export default function SyncEdit({ sync, addrbookTypes }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        {
            title: 'Jubelio Sync Mapping',
            href: '/jubelio-sync',
        },
        {
            title: 'Edit Mapping',
            href: `/jubelio-sync/${sync.id}/edit`,
        },
    ];

    const { data, setData, patch, processing, errors } = useForm({
        warehouse_id: sync.warehouse_id.toString(),
        customer_id: sync.customer_id?.toString() || '',
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        patch(`/jubelio-sync/${sync.id}`);
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Edit Jubelio Sync Mapping" />

            <div className="flex flex-col gap-6 p-4">
                <div className="flex items-center gap-4">
                    <Button variant="ghost" size="icon" asChild>
                        <Link href="/jubelio-sync">
                            <ArrowLeft className="h-5 w-5" />
                        </Link>
                    </Button>
                    <h1 className="text-2xl font-bold">Edit Sync Mapping</h1>
                </div>

                <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <Card className="border-dashed bg-sidebar-accent/10">
                        <CardContent className="flex items-center gap-3 p-4">
                            <Store className="h-5 w-5 text-blue-500" />
                            <div>
                                <p className="text-[10px] font-bold text-sidebar-foreground/50 uppercase">
                                    Jubelio Store
                                </p>
                                <p className="text-sm font-medium">
                                    {sync.jubelio_store_name}
                                </p>
                            </div>
                        </CardContent>
                    </Card>
                    <Card className="border-dashed bg-sidebar-accent/10">
                        <CardContent className="flex items-center gap-3 p-4">
                            <MapPin className="h-5 w-5 text-red-500" />
                            <div>
                                <p className="text-[10px] font-bold text-sidebar-foreground/50 uppercase">
                                    Jubelio Location
                                </p>
                                <p className="text-sm font-medium">
                                    {sync.jubelio_location_name}
                                </p>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Internal Mapping</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={handleSubmit} className="space-y-6">
                            <div className="space-y-2">
                                <Label>Target Warehouse (Internal)</Label>
                                <AsyncCombobox
                                    route={`/transactions/sell/lookup/sender?addrbook_type=${addrbookTypes.warehouse}`}
                                    placeholder="Search warehouse..."
                                    value={data.warehouse_id}
                                    defaultValue={sync.warehouse?.name}
                                    onValueChange={(val) =>
                                        setData('warehouse_id', val)
                                    }
                                />
                                {errors.warehouse_id && (
                                    <p className="text-sm text-red-500">
                                        {errors.warehouse_id}
                                    </p>
                                )}
                            </div>

                            <div className="space-y-2">
                                <Label>Target Customer (Optional)</Label>
                                <AsyncCombobox
                                    route={`/transactions/sell/lookup/receiver?addrbook_type=${addrbookTypes.customer}`}
                                    placeholder="Search customer..."
                                    value={data.customer_id}
                                    defaultValue={sync.customer?.name}
                                    onValueChange={(val) =>
                                        setData('customer_id', val)
                                    }
                                />
                                {errors.customer_id && (
                                    <p className="text-sm text-red-500">
                                        {errors.customer_id}
                                    </p>
                                )}
                            </div>

                            <div className="flex justify-end gap-3 pt-4">
                                <Button variant="outline" asChild>
                                    <Link href="/jubelio-sync">Cancel</Link>
                                </Button>
                                <Button type="submit" disabled={processing}>
                                    {processing && (
                                        <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                                    )}
                                    Save Changes
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
