import { Head, useForm, Link } from '@inertiajs/react';
import { User, MapPin, Phone, Mail, Wallet } from 'lucide-react';
import FormInput from '@/components/Partial/Form/FormInput';
import FormMultiSelect from '@/components/Partial/Form/FormMultiSelect';
import FormSelect from '@/components/Partial/Form/FormSelect';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/app-layout';
import addrbookRoutes from '@/routes/addrbook';
import type { BreadcrumbItem } from '@/types';

interface Addrbook {
    id: number;
    name: string;
    address: string;
    phone: string;
    email: string;
    contact_person: string;
    is_online: boolean;
    ppn: boolean;
    type: number;
    stat?: {
        balance: number;
    };
    additional_fees?: { id: number; name: string }[];
}

interface AddrbookType {
    id: number;
    name: string;
    slug: string;
}

interface Props {
    addrbook: Addrbook;
    types: AddrbookType[];
    ppn_rate: number;
}

export default function AddrbookEdit({ addrbook, types, ppn_rate }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        {
            title: 'Address Book',
            href: addrbookRoutes.index.url(),
        },
        {
            title: addrbook.name,
            href: addrbookRoutes.edit.url(addrbook.id),
        },
    ];

    const { data, setData, put, processing, errors } = useForm({
        name: addrbook.name || '',
        type: addrbook.type?.toString() || '',
        contact_person: addrbook.contact_person || '',
        phone: addrbook.phone || '',
        email: addrbook.email || '',
        address: addrbook.address || '',
        is_online: Boolean(addrbook.is_online),
        ppn: Boolean(addrbook.ppn),
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        put(addrbookRoutes.update.url(addrbook.id));
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Edit ${addrbook.name}`} />

            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <form onSubmit={submit}>
                    <div className="mb-8 flex flex-col items-start justify-between gap-4 sm:flex-row sm:items-center">
                        <div>
                            <h1 className="mb-1 text-3xl font-bold tracking-tight text-zinc-900 dark:text-zinc-50">
                                Edit Entry
                            </h1>
                            <p className="text-zinc-500 dark:text-zinc-400">
                                Update details for {addrbook.name}.
                            </p>
                        </div>
                        <div className="flex items-center gap-3">
                            <Link href={addrbookRoutes.index.url()}>
                                <Button variant="outline" type="button">
                                    Cancel
                                </Button>
                            </Link>
                            <Button
                                type="submit"
                                className="bg-blue-600 text-white hover:bg-blue-700"
                                disabled={processing}
                            >
                                {processing ? 'Saving...' : 'Save Changes'}
                            </Button>
                        </div>
                    </div>

                    <div className="grid grid-cols-1 gap-6 md:grid-cols-2">
                        {/* Basic Information */}
                        <Card className="border-zinc-200 bg-white md:col-span-2 dark:border-zinc-800 dark:bg-zinc-900/50">
                            <CardHeader>
                                <CardTitle>Basic Information</CardTitle>
                                <CardDescription>
                                    Primary details for this contact.
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                                    <FormInput
                                        id="name"
                                        label="Name"
                                        value={data.name}
                                        onChange={(e) =>
                                            setData('name', e.target.value)
                                        }
                                        error={errors.name}
                                        required
                                        icon={User}
                                        placeholder="e.g. PT. Maju Jaya"
                                    />
                                    <FormSelect
                                        label="Type"
                                        value={data.type}
                                        onValueChange={(val) =>
                                            setData('type', val)
                                        }
                                        error={errors.type}
                                        required
                                        options={types.map((t) => ({
                                            value: t.id.toString(),
                                            label: t.name,
                                        }))}
                                        placeholder="Select Type"
                                    />
                                </div>
                                <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                                    <FormInput
                                        id="contact_person"
                                        label="Contact Person"
                                        value={data.contact_person}
                                        onChange={(e) =>
                                            setData(
                                                'contact_person',
                                                e.target.value,
                                            )
                                        }
                                        error={errors.contact_person}
                                        icon={User}
                                        placeholder="e.g. Budi Santoso"
                                    />
                                    <FormInput
                                        id="phone"
                                        label="Phone / WhatsApp"
                                        value={data.phone}
                                        onChange={(e) =>
                                            setData('phone', e.target.value)
                                        }
                                        error={errors.phone}
                                        icon={Phone}
                                        placeholder="e.g. 08123456789"
                                    />
                                </div>
                                <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                                    <FormInput
                                        id="email"
                                        label="Email Address"
                                        type="email"
                                        value={data.email}
                                        onChange={(e) =>
                                            setData('email', e.target.value)
                                        }
                                        error={errors.email}
                                        icon={Mail}
                                        placeholder="e.g. budi@example.com"
                                    />
                                </div>
                                <div className="space-y-2">
                                    <Label
                                        htmlFor="address"
                                        className="font-medium text-zinc-700 dark:text-zinc-300"
                                    >
                                        Address
                                    </Label>
                                    <div className="relative">
                                        <div className="absolute top-3 left-3 text-zinc-500">
                                            <MapPin className="h-5 w-5" />
                                        </div>
                                        <Textarea
                                            id="address"
                                            value={data.address}
                                            onChange={(e) =>
                                                setData(
                                                    'address',
                                                    e.target.value,
                                                )
                                            }
                                            className="min-h-[100px] border-zinc-200 bg-white pl-10 focus:border-blue-600 focus:ring-blue-600/20 dark:border-zinc-800 dark:bg-zinc-950"
                                            placeholder="Full address here..."
                                        />
                                    </div>
                                    {errors.address && (
                                        <div className="text-sm text-red-500">
                                            {errors.address}
                                        </div>
                                    )}
                                </div>
                            </CardContent>
                        </Card>

                        {/* Settings & Financials */}
                        <Card className="border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900/50">
                            <CardHeader>
                                <CardTitle>Settings</CardTitle>
                                <CardDescription>
                                    Status and tax configuration.
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-6">
                                <div className="flex items-center justify-between rounded-lg border border-zinc-200 bg-zinc-50 p-4 dark:border-zinc-800 dark:bg-zinc-950/50">
                                    <div className="space-y-0.5">
                                        <Label className="text-base">
                                            Online
                                        </Label>
                                        <p className="text-sm text-zinc-500">
                                            Is this contact from an online
                                            source?
                                        </p>
                                    </div>
                                    <Switch
                                        checked={data.is_online}
                                        onCheckedChange={(checked) =>
                                            setData('is_online', checked)
                                        }
                                    />
                                </div>

                                <div className="flex items-center justify-between rounded-lg border border-zinc-200 bg-zinc-50 p-4 dark:border-zinc-800 dark:bg-zinc-950/50">
                                    <div className="space-y-0.5">
                                        <Label className="text-base text-zinc-900 dark:text-zinc-50">
                                            PPN ({ppn_rate}%)
                                        </Label>
                                        <p className="text-sm text-zinc-500">
                                            Apply {ppn_rate}% tax for this
                                            contact?
                                        </p>
                                    </div>
                                    <Switch
                                        checked={data.ppn}
                                        onCheckedChange={(checked) =>
                                            setData('ppn', checked)
                                        }
                                    />
                                </div>
                            </CardContent>
                        </Card>

                        <Card className="border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900/50">
                            <CardHeader>
                                <CardTitle>Current Financials</CardTitle>
                                <CardDescription>
                                    View current balance.
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <div className="rounded-lg border border-zinc-200 bg-zinc-50 p-4 dark:border-zinc-800 dark:bg-zinc-950">
                                    <div className="flex items-center gap-3">
                                        <div className="rounded-md bg-blue-100 p-2 dark:bg-blue-900/20">
                                            <Wallet className="h-6 w-6 text-blue-600 dark:text-blue-400" />
                                        </div>
                                        <div>
                                            <p className="text-sm text-zinc-500 dark:text-zinc-400">
                                                Current Balance
                                            </p>
                                            <p className="text-xl font-bold text-zinc-900 dark:text-zinc-100">
                                                {new Intl.NumberFormat(
                                                    'id-ID',
                                                    {
                                                        style: 'currency',
                                                        currency: 'IDR',
                                                    },
                                                ).format(
                                                    addrbook.stat?.balance || 0,
                                                )}
                                            </p>
                                        </div>
                                    </div>
                                </div>
                                <p className="text-center text-xs text-zinc-500">
                                    Balance adjustments should be made via
                                    Transactions.
                                </p>
                            </CardContent>
                        </Card>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}
