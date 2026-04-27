import { Head, Link, router } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { BreadcrumbItem } from '@/types';
import { Button } from '@/components/ui/button';
import { FilePen, Trash2, Plus, Settings, ChevronRight } from 'lucide-react';
import systemSettings from '@/routes/system-settings';
import { useState } from 'react';
import { cn } from "@/lib/utils";

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'System Settings',
        href: systemSettings.index.url(),
    },
];

interface Setting {
    id: number;
    group: string;
    name: string;
    slug: string;
    value: string;
}

interface Props {
    settings: Setting[];
    groups: string[];
}

export default function SettingsIndex({ settings, groups }: Props) {
    const [activeGroup, setActiveGroup] = useState(groups[0] || 'General');

    const handleDelete = (id: number) => {
        if (confirm('Are you sure you want to delete this setting?')) {
            router.delete(systemSettings.destroy.url({ system_setting: id }));
        }
    };

    const filteredSettings = settings.filter(s => (s.group || 'General') === activeGroup);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="System Settings" />

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
                            <Button className="bg-blue-600 hover:bg-blue-700 text-white shadow-lg shadow-blue-500/20">
                                <Plus className="mr-2 h-4 w-4" /> Add New Setting
                            </Button>
                        </Link>
                    </div>
                </div>

                <div className="flex flex-col lg:flex-row gap-8">
                    {/* Sidebar Navigation */}
                    <div className="w-full lg:w-64 flex-shrink-0">
                        <nav className="space-y-1">
                            {groups.map((group) => (
                                <button
                                    key={group}
                                    onClick={() => setActiveGroup(group)}
                                    className={cn(
                                        "w-full flex items-center justify-between px-4 py-3 text-sm font-medium rounded-xl transition-all",
                                        activeGroup === group
                                            ? "bg-blue-600 text-white shadow-md shadow-blue-600/20"
                                            : "text-zinc-600 dark:text-zinc-400 hover:bg-zinc-100 dark:hover:bg-zinc-800"
                                    )}
                                >
                                    <span className="flex items-center gap-3">
                                        <Settings className={cn("h-4 w-4", activeGroup === group ? "text-white" : "text-zinc-400")} />
                                        {group}
                                    </span>
                                    <ChevronRight className={cn("h-4 w-4 opacity-0 transition-all", activeGroup === group && "opacity-100 translate-x-1")} />
                                </button>
                            ))}
                            {groups.length === 0 && (
                                <div className="px-4 py-3 text-sm text-zinc-500 italic border border-dashed border-zinc-200 dark:border-zinc-800 rounded-xl">
                                    No groups found
                                </div>
                            )}
                        </nav>
                    </div>

                    {/* Content Area */}
                    <div className="flex-1 min-w-0">
                        <div className="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-2xl overflow-hidden shadow-sm">
                            <div className="p-6 border-b border-zinc-100 dark:border-zinc-800 bg-zinc-50/50 dark:bg-zinc-900/50 flex items-center justify-between">
                                <h2 className="text-lg font-bold text-zinc-900 dark:text-zinc-50 flex items-center gap-2">
                                    <span className="w-1.5 h-6 bg-blue-500 rounded-full"></span>
                                    {activeGroup} Settings
                                </h2>
                                <span className="text-xs font-mono text-zinc-400 bg-zinc-200/50 dark:bg-zinc-800 px-2 py-1 rounded">
                                    {filteredSettings.length} items
                                </span>
                            </div>

                            <div className="overflow-x-auto">
                                <table className="w-full text-sm text-left">
                                    <thead className="text-xs text-zinc-500 uppercase bg-zinc-50 dark:bg-zinc-950/50 border-b border-zinc-100 dark:border-zinc-800">
                                        <tr>
                                            <th className="px-6 py-4 font-bold tracking-wider">Parameter Name</th>
                                            <th className="px-6 py-4 font-bold tracking-wider">Identifier (Slug)</th>
                                            <th className="px-6 py-4 font-bold tracking-wider">Value</th>
                                            <th className="px-6 py-4 font-bold tracking-wider text-right">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-zinc-100 dark:divide-zinc-800/50">
                                        {filteredSettings.length > 0 ? (
                                            filteredSettings.map((setting) => (
                                                <tr key={setting.id} className="group hover:bg-zinc-50 dark:hover:bg-zinc-800/40 transition-colors">
                                                    <td className="px-6 py-4">
                                                        <div className="font-semibold text-zinc-900 dark:text-zinc-100">{setting.name}</div>
                                                    </td>
                                                    <td className="px-6 py-4">
                                                        <code className="text-xs font-mono px-1.5 py-0.5 rounded bg-zinc-100 dark:bg-zinc-800 text-zinc-600 dark:text-zinc-400 border border-zinc-200 dark:border-zinc-700">
                                                            {setting.slug}
                                                        </code>
                                                    </td>
                                                    <td className="px-6 py-4 text-zinc-700 dark:text-zinc-300">
                                                        <div className="max-w-xs truncate font-medium">
                                                            {typeof setting.value === 'object' ? JSON.stringify(setting.value) : setting.value}
                                                        </div>
                                                    </td>
                                                    <td className="px-6 py-4 text-right">
                                                        <div className="flex justify-end gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                                                            <Link
                                                                href={systemSettings.edit.url({ system_setting: setting.id })}
                                                                className="inline-flex items-center justify-center rounded-lg h-9 w-9 text-zinc-400 hover:text-blue-500 hover:bg-blue-50 dark:hover:bg-blue-900/20"
                                                            >
                                                                <FilePen className="h-4 w-4" />
                                                            </Link>
                                                            <button
                                                                onClick={() => handleDelete(setting.id)}
                                                                className="inline-flex items-center justify-center rounded-lg h-9 w-9 text-zinc-400 hover:text-red-500 hover:bg-red-50 dark:hover:bg-red-900/20"
                                                            >
                                                                <Trash2 className="h-4 w-4" />
                                                            </button>
                                                        </div>
                                                    </td>
                                                </tr>
                                            ))
                                        ) : (
                                            <tr>
                                                <td colSpan={4} className="px-6 py-12 text-center text-zinc-500 italic">
                                                    No settings found in this category.
                                                </td>
                                            </tr>
                                        )}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
