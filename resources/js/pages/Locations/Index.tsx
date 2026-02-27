
import { Head, Link, router } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { BreadcrumbItem } from '@/types';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { FilePen, Trash2, Plus, SlidersHorizontal, Search, MapPin } from 'lucide-react';
import { Input } from '@/components/ui/input';
import locationRoutes from '@/routes/locations';
import Pagination from '@/components/Partial/Pagination';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Locations',
        href: '/locations',
    },
];

interface Location {
    id: number;
    name: string;
    created_at: string;
}

interface Props {
    locations: {
        data: Location[];
        links: any[];
        from: number;
        to: number;
        total: number;
    };
}

export default function LocationsIndex({ locations }: Props) {
    const handleDelete = (id: number) => {
        if (confirm('Are you sure you want to delete this location?')) {
            router.delete(locationRoutes.destroy.url({ location: id }));
        }
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Locations" />

            <div className="p-4 sm:p-6 lg:p-8">
                {/* Header */}
                <div className="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-8 gap-4">
                    <div>
                        <h2 className="text-2xl font-bold tracking-tight text-zinc-900 dark:text-zinc-50">Locations</h2>
                        <p className="mt-1 text-sm text-zinc-500 dark:text-zinc-400">Manage your physical locations and addresses.</p>
                    </div>
                    <div className="flex items-center gap-2 w-full sm:w-auto">
                        <Button variant="outline" size="sm" className="hidden sm:flex gap-2">
                            <SlidersHorizontal className="h-4 w-4" />
                            Filters
                        </Button>
                        <Link href={locationRoutes.create.url()} className="w-full sm:w-auto">
                            <Button className="w-full sm:w-auto bg-blue-600 hover:bg-blue-700 text-white shadow-sm gap-2">
                                <Plus className="h-4 w-4" />
                                Add Location
                            </Button>
                        </Link>
                    </div>
                </div>

                {/* Search Bar Placeholder */}
                <div className="mb-4 relative max-w-sm hidden">
                    <Search className="absolute left-2.5 top-2.5 h-4 w-4 text-zinc-500" />
                    <Input placeholder="Search locations..." className="pl-9 bg-zinc-50 dark:bg-zinc-900 border-zinc-200 dark:border-zinc-800" />
                </div>

                {/* Table Card */}
                <div className="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-xl shadow-sm overflow-hidden">
                    <div className="overflow-x-auto">
                        <table className="min-w-full divide-y divide-zinc-200 dark:divide-zinc-800">
                            <thead className="bg-zinc-50/50 dark:bg-zinc-900/50">
                                <tr>
                                    <th scope="col" className="px-6 py-4 text-left text-xs font-semibold text-zinc-500 dark:text-zinc-400 uppercase tracking-wider">Name</th>
                                    <th scope="col" className="px-6 py-4 text-left text-xs font-semibold text-zinc-500 dark:text-zinc-400 uppercase tracking-wider">Created At</th>
                                    <th scope="col" className="px-6 py-4 text-right text-xs font-semibold text-zinc-500 dark:text-zinc-400 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-zinc-200 dark:divide-zinc-800 bg-white dark:bg-zinc-900">
                                {locations.data.map((location) => (
                                    <tr key={location.id} className="group hover:bg-zinc-50/50 dark:hover:bg-zinc-800/50 transition-colors">
                                        <td className="px-6 py-4 whitespace-nowrap">
                                            <div className="flex items-center gap-3">
                                                <div className="h-8 w-8 rounded-lg bg-zinc-100 dark:bg-zinc-800 flex items-center justify-center">
                                                    <MapPin className="h-4 w-4 text-zinc-500 dark:text-zinc-400" />
                                                </div>
                                                <div className="text-sm font-bold text-zinc-900 dark:text-white">{location.name}</div>
                                            </div>
                                        </td>

                                        <td className="px-6 py-4 whitespace-nowrap">
                                            <div className="text-sm text-zinc-500 dark:text-zinc-400">
                                                {new Date(location.created_at).toLocaleDateString()}
                                            </div>
                                        </td>
                                        <td className="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                            <div className="flex justify-end gap-2">
                                                <Link href={locationRoutes.edit.url({ location: location.id })}>
                                                    <Button variant="ghost" size="icon" className="h-8 w-8 text-zinc-400 hover:text-zinc-900 dark:hover:text-zinc-100 hover:bg-zinc-100 dark:hover:bg-zinc-800">
                                                        <FilePen className="h-4 w-4" />
                                                        <span className="sr-only">Edit</span>
                                                    </Button>
                                                </Link>
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    onClick={() => handleDelete(location.id)}
                                                    className="h-8 w-8 text-zinc-400 hover:text-red-600 dark:hover:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20"
                                                >
                                                    <Trash2 className="h-4 w-4" />
                                                    <span className="sr-only">Delete</span>
                                                </Button>
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                                {locations.data.length === 0 && (
                                    <tr>
                                        <td colSpan={3} className="px-6 py-12 text-center text-sm text-zinc-500 dark:text-zinc-400">
                                            No locations found. Get started by adding a new location.
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                    {/* Pagination */}
                    <div className="bg-zinc-50/30 dark:bg-zinc-900/30 px-6 py-4 border-t border-zinc-200 dark:border-zinc-800">
                        <Pagination
                            links={locations.links}
                            from={locations.from}
                            to={locations.to}
                            total={locations.total}
                            label="locations"
                        />
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
