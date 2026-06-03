import { Head, useForm, Link } from '@inertiajs/react';
import { Save, ArrowLeft, Settings as SettingsIcon } from 'lucide-react';
import FormAsyncCombobox from '@/components/Partial/Form/FormAsyncCombobox';
import FormInput from '@/components/Partial/Form/FormInput';
import FormSelect from '@/components/Partial/Form/FormSelect';
import FormTextarea from '@/components/Partial/Form/FormTextarea';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardHeader,
    CardTitle,
    CardDescription,
} from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import systemSettings from '@/routes/system-settings';
import type { BreadcrumbItem } from '@/types';

interface Setting {
    id: number;
    group: string;
    name: string;
    slug: string;
    value: any;
}

interface Props {
    setting: Setting;
}

export default function SettingEdit({ setting }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        {
            title: 'System Settings',
            href: systemSettings.index.url(),
        },
        {
            title: 'Edit',
            href: systemSettings.edit.url({ system_setting: setting.id }),
        },
    ];

    const { data, setData, put, processing, errors } = useForm({
        group: setting.group || '',
        name: setting.name,
        value: setting.value ?? '',
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        put(systemSettings.update.url({ system_setting: setting.id }));
    };

    const isAccountSetting = ['sell_100', 'ongkir'].includes(setting.slug);
    const isTutupBuku = setting.slug === 'tutup_buku';
    const isNumericSetting = ['batas_cuti_tahunan', 'batas_cuti_sakit'].includes(
        setting.slug,
    );

    const tutupBukuOptions = [3, 4, 5, 6, 7, 8, 9, 10, 20, 28].map((day) => ({
        value: String(day),
        label: `Tanggal ${day}`,
    }));

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Edit Setting: ${setting.name}`} />

            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div className="mx-auto max-w-3xl">
                    <div className="mb-8 flex items-center justify-between">
                        <div>
                            <h1 className="mb-1 text-3xl font-bold tracking-tight text-zinc-900 dark:text-zinc-50">
                                Edit Setting
                            </h1>
                            <p className="text-zinc-500 dark:text-zinc-400">
                                Modify the configuration parameter.
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
                                    Update category, name and value. The slug
                                    cannot be changed.
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <div className="space-y-2 opacity-60">
                                    <Label className="font-medium text-zinc-700 dark:text-zinc-300">
                                        Slug (System Key)
                                    </Label>
                                    <div className="rounded border border-zinc-200 bg-zinc-50 p-2 font-mono text-sm dark:border-zinc-800 dark:bg-zinc-950">
                                        {setting.slug}
                                    </div>
                                </div>

                                <FormInput
                                    id="group"
                                    label="Group / Category"
                                    value={data.group}
                                    onChange={(e) =>
                                        setData('group', e.target.value)
                                    }
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
                                    error={errors.name}
                                    required
                                />

                                {isTutupBuku ? (
                                    <FormSelect
                                        label="Value"
                                        value={String(data.value)}
                                        onValueChange={(val) =>
                                            setData('value', val)
                                        }
                                        options={tutupBukuOptions}
                                        error={errors.value}
                                        required
                                    />
                                ) : isAccountSetting ? (
                                    <FormAsyncCombobox
                                        label="Value (Select Account)"
                                        endpoint="/addrbook/account"
                                        value={data.value}
                                        onChange={(val) =>
                                            setData('value', val)
                                        }
                                        placeholder="Select account..."
                                        error={errors.value}
                                    />
                                ) : isNumericSetting ? (
                                    <FormInput
                                        id="value"
                                        label="Value"
                                        type="number"
                                        value={String(data.value)}
                                        onChange={(e) =>
                                            setData('value', e.target.value)
                                        }
                                        error={errors.value}
                                    />
                                ) : (
                                    <FormTextarea
                                        id="value"
                                        label="Value"
                                        value={
                                            typeof data.value === 'string'
                                                ? data.value
                                                : JSON.stringify(data.value)
                                        }
                                        onChange={(e) =>
                                            setData('value', e.target.value)
                                        }
                                        error={errors.value}
                                    />
                                )}
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
                                {processing ? 'Saving...' : 'Save Changes'}
                            </Button>
                        </div>
                    </form>
                </div>
            </div>
        </AppLayout>
    );
}
