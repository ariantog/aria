import { Head, useForm, Link } from '@inertiajs/react';
import { MapPin, Navigation } from 'lucide-react';
import FormInput from '@/components/Partial/Form/FormInput';
import FormTextarea from '@/components/Partial/Form/FormTextarea';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import locationRoutes from '@/routes/locations';
import type { BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Locations', href: '/locations' },
    { title: 'Edit Location', href: '#' },
];

interface Location {
    id: number;
    name: string;
}

interface Props {
    location: Location;
}

export default function LocationsEdit({ location }: Props) {
    const { data, setData, put, processing, errors } = useForm({
        name: location.name,
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        put(locationRoutes.update.url({ location: location.id }));
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Edit Location: ${location.name}`} />

            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                {/* Header */}
                <div className="mb-8">
                    <div className="mb-1 flex items-center gap-2 text-sm text-zinc-500">
                        <Link
                            href="/dashboard"
                            className="transition-colors hover:text-zinc-300"
                        >
                            Dashboard
                        </Link>
                        <span>›</span>
                        <Link
                            href="/locations"
                            className="transition-colors hover:text-zinc-300"
                        >
                            Locations
                        </Link>
                        <span>›</span>
                        <span className="font-medium text-zinc-100">
                            Edit Location
                        </span>
                    </div>
                    <h2 className="mb-2 text-3xl font-bold tracking-tight text-white">
                        Edit Location:{' '}
                        <span className="text-blue-500">{location.name}</span>
                    </h2>
                    <p className="text-zinc-400">Update location details.</p>
                </div>

                <div className="overflow-hidden rounded-xl border border-zinc-800 bg-zinc-900 p-8 shadow-sm">
                    <form onSubmit={submit} className="max-w-4xl space-y-8">
                        <div className="space-y-8">
                            <FormInput
                                id="name"
                                label="Location Name"
                                value={data.name}
                                onChange={(e) =>
                                    setData('name', e.target.value)
                                }
                                error={errors.name}
                                icon={MapPin}
                                required
                            />
                        </div>

                        <div className="flex justify-end gap-4 border-t border-zinc-800 pt-4">
                            <Link href={locationRoutes.index.url()}>
                                <Button
                                    variant="ghost"
                                    type="button"
                                    className="text-zinc-400 hover:bg-zinc-800 hover:text-zinc-100"
                                >
                                    Cancel
                                </Button>
                            </Link>
                            <Button
                                type="submit"
                                loading={processing}
                                className="min-w-[150px] bg-blue-600 text-white hover:bg-blue-700"
                            >
                                {processing ? 'Updating...' : 'Update Location'}
                            </Button>
                        </div>
                    </form>
                </div>
            </div>
        </AppLayout>
    );
}
