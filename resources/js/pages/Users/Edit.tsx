import { Head, useForm, Link } from '@inertiajs/react';
import {
    User as UserIcon,
    Mail,
    Shield,
    AlertTriangle,
    MapPin,
} from 'lucide-react';
import FormInput from '@/components/Partial/Form/FormInput';
import FormSelect from '@/components/Partial/Form/FormSelect';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import AppLayout from '@/layouts/app-layout';
import userRoutes from '@/routes/users';
import type { BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Users', href: userRoutes.index.url() },
    { title: 'Edit User', href: '#' },
];

interface Role {
    id: number;
    name: string;
}

interface User {
    id: number;
    name: string;
    username: string;
    email: string;
    is_active: boolean;
    location_id: number | null;
}

interface Location {
    id: number;
    name: string;
}

interface Props {
    user: User;
    roles: Role[];
    userRoles: string[]; // array of role names
    locations: Location[];
}

export default function UsersEdit({
    user,
    roles,
    userRoles,
    locations,
}: Props) {
    const { data, setData, put, processing, errors } = useForm({
        name: user.name,
        username: user.username,
        email: user.email,
        roles: userRoles.length > 0 ? userRoles : ([] as string[]),
        role_id: userRoles.length > 0 ? userRoles[0] : '', // For Select component
        is_active: Boolean(user.is_active),
        location_id: user.location_id || '',
        update_password: false,
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        put(userRoutes.update.url({ user: user.id }));
    };

    // Handle Role Change for Select component (Single role for now as per CoreAdmin style)
    const handleRoleChange = (value: string) => {
        setData((previousData) => ({
            ...previousData,
            role_id: value,
            roles: [value],
        }));
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Edit User: ${user.name}`} />

            <div className="p-4 sm:p-6 lg:p-8">
                {/* Header */}
                <div className="mb-8 flex flex-col items-start justify-between gap-4 sm:flex-row sm:items-center">
                    <div>
                        <h2 className="text-2xl font-bold tracking-tight text-zinc-900 dark:text-zinc-50">
                            Edit User:{' '}
                            <span className="text-blue-500">{user.name}</span>
                        </h2>
                        <p className="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                            Manage user details, role, and account status.
                        </p>
                    </div>
                </div>

                <form onSubmit={submit} className="space-y-8" noValidate>
                    <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <FormInput
                            id="name"
                            label="Full Name"
                            value={data.name}
                            onChange={(e) => setData('name', e.target.value)}
                            error={errors.name}
                            required
                            icon={UserIcon}
                        />

                        <FormInput
                            id="username"
                            label="Username"
                            value={data.username}
                            onChange={(e) =>
                                setData('username', e.target.value)
                            }
                            error={errors.username}
                            required
                            icon={UserIcon}
                        />

                        <FormInput
                            id="email"
                            label="Email Address"
                            type="email"
                            value={data.email}
                            onChange={(e) => setData('email', e.target.value)}
                            error={errors.email}
                            required
                            icon={Mail}
                        />

                        <FormSelect
                            label="Role Assignment"
                            value={data.role_id}
                            onValueChange={handleRoleChange}
                            options={roles.map((role) => ({
                                value: role.name,
                                label: role.name,
                            }))}
                            error={errors.roles || errors.role_id} // Handle potential error keys
                            icon={Shield}
                            placeholder="Select a role"
                            required
                        />

                        <FormSelect
                            label="Location"
                            value={String(data.location_id)}
                            onValueChange={(value) =>
                                setData(
                                    'location_id',
                                    value === 'null' ? '' : Number(value),
                                )
                            }
                            options={[
                                { value: 'null', label: 'No Location' },
                                ...locations.map((location) => ({
                                    value: String(location.id),
                                    label: location.name,
                                })),
                            ]}
                            error={errors.location_id}
                            icon={MapPin}
                            placeholder="Select a location"
                        />
                    </div>

                    {/* Danger Zone */}
                    <div className="mt-8 border-t border-zinc-200 pt-8 dark:border-zinc-800">
                        <div className="rounded-lg border border-red-900/30 bg-red-900/10 p-6">
                            <div className="flex items-start gap-4">
                                <div className="rounded-lg bg-red-900/20 p-2">
                                    <AlertTriangle className="h-6 w-6 text-red-500" />
                                </div>
                                <div className="flex-1">
                                    <h3 className="mb-1 text-lg font-semibold text-red-500">
                                        Danger Zone
                                    </h3>
                                    <p className="mb-6 text-sm text-zinc-500 dark:text-zinc-400">
                                        Actions here can affect the user's
                                        ability to access the system.
                                    </p>

                                    <div className="flex flex-col gap-4">
                                        <div className="flex items-center justify-between rounded-lg border border-red-900/20 bg-white/50 p-4 dark:bg-zinc-950/50">
                                            <div className="space-y-0.5">
                                                <Label className="text-base text-zinc-900 dark:text-zinc-200">
                                                    Reset Password
                                                </Label>
                                                <p className="text-sm text-zinc-600 dark:text-zinc-500">
                                                    Generate a new random
                                                    password for this user.
                                                </p>
                                            </div>
                                            <div className="flex items-center gap-2">
                                                <Switch
                                                    checked={
                                                        data.update_password
                                                    }
                                                    onCheckedChange={(
                                                        checked,
                                                    ) =>
                                                        setData(
                                                            'update_password',
                                                            checked,
                                                        )
                                                    }
                                                    className="data-[state=checked]:bg-blue-600"
                                                />
                                                <span
                                                    className={`font-medium ${data.update_password ? 'text-blue-500' : 'text-zinc-500'}`}
                                                >
                                                    {data.update_password
                                                        ? 'RESET ON SAVE'
                                                        : 'No Change'}
                                                </span>
                                            </div>
                                        </div>

                                        <div className="flex items-center justify-between rounded-lg border border-red-900/20 bg-white/50 p-4 dark:bg-zinc-950/50">
                                            <div className="space-y-0.5">
                                                <Label className="text-base text-zinc-900 dark:text-zinc-200">
                                                    Ban User
                                                </Label>
                                                <p className="text-sm text-zinc-600 dark:text-zinc-500">
                                                    Prevent this user from
                                                    logging in.
                                                </p>
                                            </div>
                                            <div className="flex items-center gap-2">
                                                <Switch
                                                    checked={!data.is_active}
                                                    onCheckedChange={(
                                                        checked,
                                                    ) =>
                                                        setData(
                                                            'is_active',
                                                            !checked,
                                                        )
                                                    }
                                                    className="data-[state=checked]:bg-red-600"
                                                />
                                                <span
                                                    className={`font-medium ${!data.is_active ? 'text-red-500' : 'text-zinc-500'}`}
                                                >
                                                    {!data.is_active
                                                        ? 'BANNED'
                                                        : 'Active'}
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div className="flex justify-end gap-4 pt-8">
                        <Link href={userRoutes.index.url()}>
                            <Button variant="ghost" type="button">
                                Cancel
                            </Button>
                        </Link>
                        <Button type="submit" loading={processing}>
                            Save Changes
                        </Button>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}
