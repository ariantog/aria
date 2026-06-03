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
    { title: 'Create Role', href: '/roles/create' },
];

interface Props {
    permissions: Record<string, { id: number; name: string }[]>;
}

export default function RolesCreate({ permissions }: Props) {
    const { data, setData, post, processing, errors } = useForm({
        name: '',
        permissions: [] as string[],
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        post(roleRoutes.store.url());
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Create Role" />

            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
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
                            Create New Role
                        </span>
                    </div>
                    <h2 className="mb-2 text-3xl font-bold tracking-tight text-white">
                        Create New Role
                    </h2>
                    <p className="text-zinc-400">
                        Define a new role and assign its permissions.
                    </p>
                </div>

                <div className="overflow-hidden rounded-xl border border-zinc-800 bg-zinc-900 p-8 shadow-sm">
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
                                {processing ? 'Creating...' : 'Create Role'}
                            </Button>
                        </div>
                    </form>
                </div>
            </div>
        </AppLayout>
    );
}
