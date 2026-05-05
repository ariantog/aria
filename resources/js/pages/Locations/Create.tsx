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
    { title: 'New Location', href: '#' },
];

export default function LocationsCreate() {
    const { data, setData, post, processing, errors } = useForm({
        name: '',
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        post(locationRoutes.store.url());
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Create Location" />

            <div className="p-4 sm:p-6 lg:p-8">
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
                            New Location
                        </span>
                    </div>
                    <h2 className="mb-2 text-3xl font-bold tracking-tight text-white">
                        Create New Location
                    </h2>
                    <p className="text-zinc-400">
                        Add a new physical location to the system.
                    </p>
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
                                placeholder="e.g. Main Office"
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
                                {processing ? 'Creating...' : 'Create Location'}
                            </Button>
                        </div>
                    </form>
                </div>
            </div>
        </AppLayout>
    );
}
