
import { Head, useForm, Link } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { BreadcrumbItem } from '@/types';
import PermissionMatrix from '@/components/PermissionMatrix';
import roleRoutes from '@/routes/roles';
import { Shield } from 'lucide-react';
import FormInput from '@/components/Partial/Form/FormInput';

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

            <div className="p-4 sm:p-6 lg:p-8">
                {/* Header */}
                <div className="mb-8">
                    <div className="flex items-center gap-2 text-sm text-zinc-500 mb-1">
                        <Link href="/dashboard" className="hover:text-zinc-300 transition-colors">Dashboard</Link>
                        <span>›</span>
                        <Link href="/roles" className="hover:text-zinc-300 transition-colors">Roles</Link>
                        <span>›</span>
                        <span className="text-zinc-100 font-medium">Create New Role</span>
                    </div>
                    <h2 className="text-3xl font-bold tracking-tight text-white mb-2">Create New Role</h2>
                    <p className="text-zinc-400">Define a new role and assign its permissions.</p>
                </div>

                <div className="bg-zinc-900 border border-zinc-800 rounded-xl overflow-hidden shadow-sm p-8">
                    <form onSubmit={submit} className="">
                        <div className="space-y-8">
                            <FormInput
                                id="name"
                                label="Role Name"
                                value={data.name}
                                onChange={e => setData('name', e.target.value)}
                                error={errors.name}
                                icon={Shield}
                                placeholder="e.g. Editor"
                                required
                            />
                        </div>

                        <div>
                            <Label className="text-lg font-semibold text-zinc-100 mb-4 block tracking-tight">Permissions</Label>
                            <PermissionMatrix
                                permissions={permissions}
                                selectedPermissions={data.permissions}
                                onChange={(selected) => setData('permissions', selected)}
                            />
                        </div>

                        <div className="flex justify-end pt-8 gap-4 border-t border-zinc-800">
                            <Link href={roleRoutes.index.url()}>
                                <Button variant="ghost" type="button" className="text-zinc-400 hover:text-zinc-100 hover:bg-zinc-800">Cancel</Button>
                            </Link>
                            <Button type="submit" disabled={processing} className="bg-blue-600 hover:bg-blue-700 text-white min-w-[150px]">
                                {processing ? 'Creating...' : 'Create Role'}
                            </Button>
                        </div>
                    </form>
                </div>
            </div>
        </AppLayout>
    );
}
