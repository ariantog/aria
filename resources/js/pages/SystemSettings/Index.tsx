import { Head, Link, router } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { BreadcrumbItem } from '@/types';
import { Button } from '@/components/ui/button';
import { FilePen, Trash2, Plus, Settings } from 'lucide-react';
import systemSettings from '@/routes/system-settings';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'System Settings',
        href: systemSettings.index.url(),
    },
];

interface Setting {
    id: number;
    name: string;
    slug: string;
    value: string;
}

interface Props {
    settings: Setting[];
}

export default function SettingsIndex({ settings }: Props) {
    const handleDelete = (id: number) => {
        if (confirm('Are you sure you want to delete this setting?')) {
            router.delete(systemSettings.destroy.url({ system_setting: id }));
        }
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Settings" />

            <div className="p-4 sm:p-6 lg:p-8">
                <div className="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-8 gap-4">
                    <div>
                        <h1 className="text-3xl font-bold tracking-tight text-zinc-900 dark:text-zinc-50 mb-1">
                            System Settings
                        </h1>
                        <p className="text-zinc-500 dark:text-zinc-400">
                            Configure application wide settings and parameters.
                        </p>
                    </div>
                    <div>
                        <Link href={systemSettings.create.url()}>
                            <Button className="bg-blue-600 hover:bg-blue-700 text-white">
                                <Plus className="mr-2 h-4 w-4" /> Add New Setting
                            </Button>
                        </Link>
                    </div>
                </div>

                <div className="rounded-xl border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900/50 overflow-hidden shadow-sm">
                    <div className="overflow-x-auto">
                        <table className="w-full text-sm text-left">
                            <thead className="text-xs text-zinc-500 uppercase bg-zinc-50 dark:bg-zinc-900/50 border-b border-zinc-200 dark:border-zinc-800">
                                <tr>
                                    <th className="px-6 py-4 font-bold tracking-wider">Name</th>
                                    <th className="px-6 py-4 font-bold tracking-wider">Slug</th>
                                    <th className="px-6 py-4 font-bold tracking-wider">Value</th>
                                    <th className="px-6 py-4 font-bold tracking-wider text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-zinc-200 dark:divide-zinc-800/50">
                                {settings.length > 0 ? (
                                    settings.map((setting) => (
                                        <tr key={setting.id} className="group hover:bg-zinc-50 dark:hover:bg-zinc-800/40 transition-colors">
                                            <td className="px-6 py-4">
                                                <div className="font-semibold text-zinc-900 dark:text-zinc-100">{setting.name}</div>
                                            </td>
                                            <td className="px-6 py-4 font-mono text-xs text-zinc-500">
                                                {setting.slug}
                                            </td>
                                            <td className="px-6 py-4 text-zinc-700 dark:text-zinc-300">
                                                {setting.value}
                                            </td>
                                            <td className="px-6 py-4 text-right">
                                                <div className="flex justify-end gap-2">
                                                    <Link
                                                        href={systemSettings.edit.url({ system_setting: setting.id })}
                                                        className="inline-flex items-center justify-center rounded-md p-2 text-zinc-400 hover:text-blue-500 hover:bg-blue-50 dark:hover:bg-blue-900/20"
                                                    >
                                                        <FilePen className="h-4 w-4" />
                                                    </Link>
                                                    <button
                                                        onClick={() => handleDelete(setting.id)}
                                                        className="inline-flex items-center justify-center rounded-md p-2 text-zinc-400 hover:text-red-500 hover:bg-red-50 dark:hover:bg-red-900/20"
                                                    >
                                                        <Trash2 className="h-4 w-4" />
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    ))
                                ) : (
                                    <tr>
                                        <td colSpan={4} className="px-6 py-12 text-center text-zinc-500">
                                            No settings found.
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
