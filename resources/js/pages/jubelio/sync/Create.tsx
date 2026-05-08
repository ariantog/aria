import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, Loader2 } from 'lucide-react';
import { useState } from 'react';
import { AsyncCombobox } from '@/components/AsyncCombobox';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
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

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Jubelio Sync Mapping',
        href: '/jubelio-sync',
    },
    {
        title: 'Create Mapping',
        href: '/jubelio-sync/create',
    },
];

interface Location {
    location_id: number;
    location_name: string;
}

interface Props {
    locations: Location[];
    addrbookTypes: {
        warehouse: number;
        customer: number;
    };
}

export default function SyncCreate({ locations, addrbookTypes }: Props) {
    const { data, setData, post, processing, errors } = useForm({
        location_id: '',
        location_name: '',
        warehouse_id: '',
        customer_id: '',
    });

    const [selectedLocation, setSelectedLocation] = useState<string>('');

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post('/jubelio-sync');
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Create Jubelio Sync Mapping" />

            <div className="flex flex-col gap-6 p-4">
                <div className="flex items-center gap-4">
                    <Button variant="ghost" size="icon" asChild>
                        <Link href="/jubelio-sync">
                            <ArrowLeft className="h-5 w-5" />
                        </Link>
                    </Button>
                    <h1 className="text-2xl font-bold">Create Sync Mapping</h1>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Mapping Configuration</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={handleSubmit} className="space-y-6">
                            <div className="space-y-2">
                                <Label htmlFor="location">
                                    Jubelio Location
                                </Label>
                                <Select
                                    onValueChange={(val) => {
                                        const loc = locations.find(
                                            (l) =>
                                                l.location_id.toString() ===
                                                val,
                                        );
                                        setData({
                                            ...data,
                                            location_id: val,
                                            location_name:
                                                loc?.location_name || '',
                                        });
                                    }}
                                >
                                    <SelectTrigger>
                                        <SelectValue placeholder="Choose a Jubelio location" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {locations.map((loc) => (
                                            <SelectItem
                                                key={loc.location_id}
                                                value={loc.location_id.toString()}
                                            >
                                                {loc.location_name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                {errors.location_id && (
                                    <p className="text-sm text-red-500">
                                        {errors.location_id}
                                    </p>
                                )}
                            </div>

                            <div className="space-y-2">
                                <Label>Target Warehouse (Internal)</Label>
                                <AsyncCombobox
                                    route={`/transactions/sell/lookup/sender?addrbook_type=${addrbookTypes.warehouse}`}
                                    placeholder="Search warehouse..."
                                    value={data.warehouse_id}
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
                                    Create Mapping
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
