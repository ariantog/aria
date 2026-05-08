import { Head, useForm, Link } from '@inertiajs/react';
import { Shield } from 'lucide-react';
import FormInput from '@/components/Partial/Form/FormInput';
import PermissionMatrix from '@/components/PermissionMatrix';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import roleRoutes from '@/routes/roles';
import type { BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Roles', href: '/roles' },
    { title: 'Edit Role', href: '#' },
];

interface Role {
    id: number;
    name: string;
}

interface Props {
    role: Role;
    permissions: Record<string, { id: number; name: string }[]>;
    rolePermissions: string[];
}

export default function RolesEdit({
    role,
    permissions,
    rolePermissions,
}: Props) {
    const { data, setData, put, processing, errors } = useForm({
        name: role.name,
        permissions: rolePermissions,
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        put(roleRoutes.update.url({ role: role.id }));
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Edit Role: ${role.name}`} />

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
                            href="/roles"
                            className="transition-colors hover:text-zinc-300"
                        >
                            Roles
                        </Link>
                        <span>›</span>
                        <span className="font-medium text-zinc-100">
                            Edit Role
                        </span>
                    </div>
                    <div className="flex items-center gap-3">
                        <h2 className="mb-2 text-3xl font-bold tracking-tight text-white">
                            Edit Role:{' '}
                            <span className="text-blue-500">{role.name}</span>
                        </h2>
                    </div>
                    <p className="text-zinc-400">
                        Update role name and permissions.
                    </p>
                </div>

                <div className="rounded-xl border border-zinc-800 bg-zinc-900 p-8 shadow-sm">
                    <form onSubmit={submit} className="">
                        <div className="space-y-8">
                            <FormInput
                                id="name"
                                label="Role Name"
                                value={data.name}
                                onChange={(e) =>
                                    setData('name', e.target.value)
                                }
                                error={errors.name}
                                icon={Shield}
                                placeholder="e.g. Editor"
                                required
                            />
                        </div>

                        <div>
                            <Label className="mb-4 block text-lg font-semibold tracking-tight text-zinc-100">
                                Permissions
                            </Label>
                            <PermissionMatrix
                                permissions={permissions}
                                selectedPermissions={data.permissions}
                                onChange={(selected) =>
                                    setData('permissions', selected)
                                }
                            />
                        </div>

                        <div className="flex justify-end gap-4 border-t border-zinc-800 pt-8">
                            <Link href={roleRoutes.index.url()}>
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
                                disabled={processing}
                                className="min-w-[150px] bg-blue-600 text-white hover:bg-blue-700"
                            >
                                {processing ? 'Updating...' : 'Update Role'}
                            </Button>
                        </div>
                    </form>
                </div>
            </div>
        </AppLayout>
    );
}
