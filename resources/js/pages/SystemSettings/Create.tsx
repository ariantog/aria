import { Head, useForm, Link } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { BreadcrumbItem } from '@/types';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardHeader,
    CardTitle,
    CardDescription,
} from '@/components/ui/card';
import { Save, ArrowLeft, Settings as SettingsIcon } from 'lucide-react';
import FormInput from '@/components/Partial/Form/FormInput';
import FormTextarea from '@/components/Partial/Form/FormTextarea';
import systemSettings from '@/routes/system-settings';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'System Settings',
        href: systemSettings.index.url(),
    },
    {
        title: 'Create',
        href: systemSettings.create.url(),
    },
];

export default function SettingCreate() {
    const { data, setData, post, processing, errors } = useForm({
        group: '',
        name: '',
        slug: '',
        value: '',
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        post(systemSettings.store.url());
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Create Setting" />

            <div className="p-4 sm:p-6 lg:p-8">
                <div className="mx-auto max-w-3xl">
                    <div className="mb-8 flex items-center justify-between">
                        <div>
                            <h1 className="mb-1 text-3xl font-bold tracking-tight text-zinc-900 dark:text-zinc-50">
                                Create Setting
                            </h1>
                            <p className="text-zinc-500 dark:text-zinc-400">
                                Add a new configuration parameter to the system.
                            </p>
                        </div>
                        <Link href={systemSettings.index.url()}>
                            <Button variant="outline" size="sm">
                                <ArrowLeft className="mr-2 h-4 w-4" /> Back to
                                List
                            </Button>
                        </Link>
                    </div>

                    <form onSubmit={submit} className="space-y-6">
                        <Card className="border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900/50">
                            <CardHeader>
                                <CardTitle>Setting Details</CardTitle>
                                <CardDescription>
                                    Define the category, name, unique slug, and
                                    value for this setting.
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <FormInput
                                    id="group"
                                    label="Group / Category"
                                    value={data.group}
                                    onChange={(e) =>
                                        setData('group', e.target.value)
                                    }
                                    placeholder="e.g. Accounting, System, Inventory"
                                    error={errors.group}
                                    required
                                />

                                <FormInput
                                    id="name"
                                    label="Name"
                                    value={data.name}
                                    onChange={(e) =>
                                        setData('name', e.target.value)
                                    }
                                    placeholder="e.g. Sales Tax Rate"
                                    error={errors.name}
                                    required
                                />

                                <FormInput
                                    id="slug"
                                    label="Slug (Unique Identifier)"
                                    value={data.slug}
                                    onChange={(e) =>
                                        setData('slug', e.target.value)
                                    }
                                    placeholder="e.g. sales_tax_rate"
                                    error={errors.slug}
                                    required
                                />
                                <p className="mt-[-12px] text-xs text-zinc-500">
                                    Lowercase letters, numbers, and underscores
                                    only.
                                </p>

                                <FormTextarea
                                    id="value"
                                    label="Value"
                                    value={data.value}
                                    onChange={(e) =>
                                        setData('value', e.target.value)
                                    }
                                    placeholder="Enter the setting value..."
                                    error={errors.value}
                                />
                            </CardContent>
                        </Card>

                        <div className="flex justify-end gap-3">
                            <Link href={systemSettings.index.url()}>
                                <Button
                                    type="button"
                                    variant="outline"
                                    disabled={processing}
                                >
                                    Cancel
                                </Button>
                            </Link>
                            <Button
                                type="submit"
                                className="bg-blue-600 text-white hover:bg-blue-700"
                                disabled={processing}
                            >
                                <Save className="mr-2 h-4 w-4" />{' '}
                                {processing ? 'Creating...' : 'Create Setting'}
                            </Button>
                        </div>
                    </form>
                </div>
            </div>
        </AppLayout>
    );
}
