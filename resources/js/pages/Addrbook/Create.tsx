import { Head, useForm, Link } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { BreadcrumbItem } from '@/types';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import FormInput from '@/components/Partial/Form/FormInput';
import FormSelect from '@/components/Partial/Form/FormSelect';
import FormMultiSelect from '@/components/Partial/Form/FormMultiSelect';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { User, MapPin, Phone, Mail, DollarSign, Wallet } from 'lucide-react';
import { Textarea } from '@/components/ui/textarea';
import addrbookRoutes from '@/routes/addrbook';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Address Book',
        href: addrbookRoutes.index.url(),
    },
    {
        title: 'Create New',
        href: addrbookRoutes.create.url(),
    },
];

interface AddrbookType {
    id: number;
    name: string;
    slug: string;
}

interface Props {
    types: AddrbookType[];
    preselected_type_id?: number;
    ppn_rate: number;
}

export default function AddrbookCreate({
    types,
    preselected_type_id,
    ppn_rate,
}: Props) {
    const { data, setData, post, processing, errors } = useForm({
        name: '',
        type: preselected_type_id ? preselected_type_id.toString() : '', // Use preselected ID if available
        contact_person: '',
        phone: '',
        email: '',
        address: '',
        is_online: false,
        ppn: false,
        initial_balance: 0,
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        post(addrbookRoutes.store.url());
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Create Address Book Entry" />

            <div className="p-4 sm:p-6 lg:p-8">
                <form onSubmit={submit}>
                    <div className="mb-8 flex flex-col items-start justify-between gap-4 sm:flex-row sm:items-center">
                        <div>
                            <h1 className="mb-1 text-3xl font-bold tracking-tight text-zinc-900 dark:text-zinc-50">
                                Create Entry
                            </h1>
                            <p className="text-zinc-500 dark:text-zinc-400">
                                Add a new customer, supplier, or contact.
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
                                {processing ? 'Saving...' : 'Save Entry'}
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
                                        disabled={!!preselected_type_id} // Disable if preselected
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
                                <CardTitle>Financials</CardTitle>
                                <CardDescription>
                                    Initial setup for accounting.
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <FormInput
                                    id="initial_balance"
                                    label="Initial Balance"
                                    type="number"
                                    value={data.initial_balance}
                                    onChange={(e) =>
                                        setData(
                                            'initial_balance',
                                            parseFloat(e.target.value),
                                        )
                                    }
                                    error={errors.initial_balance}
                                    icon={Wallet}
                                    placeholder="0"
                                    min={0}
                                />
                                <p className="text-xs text-zinc-500">
                                    Set the starting balance. Positive =
                                    Receivable, Negative = Payable (if
                                    applicable).
                                </p>
                            </CardContent>
                        </Card>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}
