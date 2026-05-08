import { Head, Link, useForm } from '@inertiajs/react';
// import { useForm } from 'laravel-precognition-react'; // Removed
import { User, Mail, Lock, Eye, EyeOff, MapPin } from 'lucide-react';
import { useState } from 'react';
import FormInput from '@/components/Partial/Form/FormInput';
import FormSelect from '@/components/Partial/Form/FormSelect';
import { Button } from '@/components/ui/button';
import { Switch } from '@/components/ui/switch';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import userRoutes from '@/routes/users';
import type { BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Users', href: '/users' },
    { title: 'Create User', href: '#' },
];

interface Role {
    id: number;
    name: string;
}

interface Location {
    id: number;
    name: string;
}

interface Props {
    roles: Role[];
    locations: Location[];
}

export default function UsersCreate({ roles, locations }: Props) {
    // Backend now handles password generation

    // Using standard Inertia useForm instead of Precognition
    const { data, setData, post, processing, errors } = useForm({
        name: '',
        username: '',
        email: '',
        role_id: '',
        location_id: '',
        // Single role selection for UI, mapped directly
        roles: [] as string[],
        is_active: true, // Dummy status for now
    });

    const [showPassword, setShowPassword] = useState(false);

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        // Ensure roles array is populated if we used role_id (or just use roles direct)
        // For this design, we treat it as single role selection
        post(userRoutes.store.url());
    };

    const handleRoleChange = (value: string) => {
        // Assuming value is role name.
        // Backend expects array of role names? Check UserController.
        // Yes, 'roles' is array. We'll set it as single item array.
        setData('roles', [value]);
    };

    // Helper to get current selected role name for Select value
    const currentRole = data.roles.length > 0 ? data.roles[0] : '';

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Create User" />

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
                            href="/users"
                            className="transition-colors hover:text-zinc-300"
                        >
                            Users
                        </Link>
                        <span>›</span>
                        <span className="font-medium text-zinc-100">
                            Create New User
                        </span>
                    </div>
                    <div className="flex items-center justify-between">
                        <h2 className="mb-2 text-3xl font-bold tracking-tight text-white">
                            Create New User
                        </h2>
                    </div>
                    <p className="text-zinc-400">
                        Deploy a new account with customized roles and
                        permissions.
                    </p>
                </div>

                {/* Main Card */}
                <div className="overflow-hidden rounded-xl border border-zinc-800 bg-zinc-900 shadow-sm">
                    {/* Tabs Placeholder (Visual only to match design) */}

                    <div className="p-8">
                        <form onSubmit={submit} className="" noValidate>
                            {/* Avatar Section Placeholder (Hidden/Removed as per request, but keeping structure clean) */}

                            <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                                {/* Row 1 */}
                                <FormInput
                                    id="name"
                                    label="Full Name"
                                    value={data.name}
                                    onChange={(e) =>
                                        setData('name', e.target.value)
                                    }
                                    error={errors.name}
                                    icon={User}
                                    required
                                    placeholder="e.g. Robert Fox"
                                />

                                <FormInput
                                    id="username"
                                    label="Username"
                                    value={data.username}
                                    onChange={(e) =>
                                        setData('username', e.target.value)
                                    }
                                    error={errors.username}
                                    icon={User}
                                    required
                                    placeholder="e.g. robert.fox"
                                />

                                <FormInput
                                    id="email"
                                    type="text"
                                    label="Email Address"
                                    value={data.email}
                                    onChange={(e) =>
                                        setData('email', e.target.value)
                                    }
                                    error={errors.email}
                                    icon={Mail}
                                    placeholder="robert.fox@company.com"
                                />

                                {/* Row 2 */}
                                <FormSelect
                                    label="Access Role"
                                    value={currentRole}
                                    onValueChange={(value) => {
                                        handleRoleChange(value);
                                    }}
                                    options={roles.map((role) => ({
                                        value: role.name,
                                        label: role.name,
                                    }))}
                                    error={errors.roles}
                                    placeholder="Select a role"
                                    required
                                />

                                <FormSelect
                                    label="Location"
                                    value={data.location_id}
                                    onValueChange={(value) => {
                                        setData('location_id', value);
                                    }}
                                    options={locations.map((location) => ({
                                        value: String(location.id),
                                        label: location.name,
                                    }))}
                                    error={errors.location_id}
                                    icon={MapPin}
                                    placeholder="Select a location"
                                    required
                                />
                            </div>

                            {/* Strength Meter Removed since password is auto-generated in backend */}

                            <hr className="border-zinc-800" />

                            <div className="flex items-center justify-between">
                                <div>
                                    <h4 className="font-medium text-zinc-100">
                                        Account Status
                                    </h4>
                                    <p className="mt-1 max-w-sm text-sm text-zinc-500">
                                        If active, the user will receive an
                                        email invitation to verify their account
                                        and set up their workspace.
                                    </p>
                                </div>
                                <div className="flex items-center gap-3">
                                    <Switch
                                        checked={!!data.is_active}
                                        onCheckedChange={(checked) =>
                                            setData('is_active', checked)
                                        }
                                        className="data-[state=checked]:bg-blue-600"
                                    />
                                    <span className="font-medium text-blue-500">
                                        Active
                                    </span>
                                </div>
                            </div>

                            <div className="flex justify-end gap-4 pt-8">
                                <Link href={userRoutes.index.url()}>
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
                                    className="min-w-[180px] bg-blue-600 text-white hover:bg-blue-700"
                                >
                                    Create User Account
                                </Button>
                            </div>
                        </form>
                    </div>
                </div>

                {/* Footer Copyright Mock */}
                <div className="mt-8 flex justify-between text-sm text-zinc-600">
                    <div>
                        &copy; 2024 CoreAdmin Enterprise. All rights reserved.
                    </div>
                    <div className="flex gap-4">
                        <span className="cursor-pointer hover:text-zinc-400">
                            Privacy Policy
                        </span>
                        <span className="cursor-pointer hover:text-zinc-400">
                            Support Center
                        </span>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
