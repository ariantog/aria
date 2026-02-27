
import { Head, useForm, Link } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { Button } from '@/components/ui/button';
import { BreadcrumbItem } from '@/types';
import locationRoutes from '@/routes/locations';
import { MapPin, Navigation } from 'lucide-react';
import FormInput from '@/components/Partial/Form/FormInput';
import FormTextarea from '@/components/Partial/Form/FormTextarea';

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

            <div className="p-4 sm:p-6 lg:p-8">
                {/* Header */}
                <div className="mb-8">
                    <div className="flex items-center gap-2 text-sm text-zinc-500 mb-1">
                        <Link href="/dashboard" className="hover:text-zinc-300 transition-colors">Dashboard</Link>
                        <span>›</span>
                        <Link href="/locations" className="hover:text-zinc-300 transition-colors">Locations</Link>
                        <span>›</span>
                        <span className="text-zinc-100 font-medium">Edit Location</span>
                    </div>
                    <h2 className="text-3xl font-bold tracking-tight text-white mb-2">Edit Location: <span className="text-blue-500">{location.name}</span></h2>
                    <p className="text-zinc-400">Update location details.</p>
                </div>

                <div className="bg-zinc-900 border border-zinc-800 rounded-xl overflow-hidden shadow-sm p-8">
                    <form onSubmit={submit} className="space-y-8 max-w-4xl">
                        <div className="space-y-8">
                            <FormInput
                                id="name"
                                label="Location Name"
                                value={data.name}
                                onChange={e => setData('name', e.target.value)}
                                error={errors.name}
                                icon={MapPin}
                                required
                            />


                        </div>

                        <div className="flex justify-end pt-4 border-t border-zinc-800 gap-4">
                            <Link href={locationRoutes.index.url()}>
                                <Button variant="ghost" type="button" className="text-zinc-400 hover:text-zinc-100 hover:bg-zinc-800">Cancel</Button>
                            </Link>
                            <Button type="submit" loading={processing} className="bg-blue-600 hover:bg-blue-700 text-white min-w-[150px]">
                                {processing ? 'Updating...' : 'Update Location'}
                            </Button>
                        </div>
                    </form>
                </div>
            </div>
        </AppLayout>
    );
}
