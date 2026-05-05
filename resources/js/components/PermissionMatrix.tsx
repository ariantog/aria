import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';

interface Props {
    permissions: Record<string, { id: number; name: string }[]>;
    selectedPermissions: string[]; // array of permission names
    onChange: (selected: string[]) => void;
}

export default function PermissionMatrix({
    permissions,
    selectedPermissions,
    onChange,
}: Props) {
    // Check if all permissions in a group are selected
    const isGroupSelected = (groupPermissions: { name: string }[]) => {
        return groupPermissions.every((p) =>
            selectedPermissions.includes(p.name),
        );
    };

    const handleGroupToggle = (groupPermissions: { name: string }[]) => {
        const allSelected = isGroupSelected(groupPermissions);
        const groupNames = groupPermissions.map((p) => p.name);

        if (allSelected) {
            // Deselect all
            onChange(
                selectedPermissions.filter(
                    (name) => !groupNames.includes(name),
                ),
            );
        } else {
            // Select all (merge)
            const newSelected = [...selectedPermissions];
            groupNames.forEach((name) => {
                if (!newSelected.includes(name)) newSelected.push(name);
            });
            onChange(newSelected);
        }
    };

    const handlePermissionToggle = (name: string) => {
        if (selectedPermissions.includes(name)) {
            onChange(selectedPermissions.filter((p) => p !== name));
        } else {
            onChange([...selectedPermissions, name]);
        }
    };

    return (
        <div className="space-y-6">
            {Object.entries(permissions).map(([module, modulePermissions]) => (
                <div
                    key={module}
                    className="rounded-lg border border-zinc-200 bg-white p-5 dark:border-zinc-800 dark:bg-zinc-900/50"
                >
                    <div className="mb-4 flex items-center justify-between border-b border-zinc-100 pb-3 dark:border-zinc-800">
                        <Label className="text-base font-semibold tracking-tight text-zinc-900 capitalize dark:text-zinc-100">
                            {module}
                        </Label>
                        <div className="flex items-center space-x-2">
                            <Checkbox
                                id={`select-all-${module}`}
                                checked={isGroupSelected(modulePermissions)}
                                onCheckedChange={() =>
                                    handleGroupToggle(modulePermissions)
                                }
                                className="border-zinc-300 data-[state=checked]:bg-zinc-900 data-[state=checked]:text-zinc-50 dark:border-zinc-600 dark:data-[state=checked]:bg-zinc-50 dark:data-[state=checked]:text-zinc-900"
                            />
                            <Label
                                htmlFor={`select-all-${module}`}
                                className="cursor-pointer text-sm text-zinc-500 transition-colors hover:text-zinc-800 dark:text-zinc-400 dark:hover:text-zinc-200"
                            >
                                Select All
                            </Label>
                        </div>
                    </div>
                    <div className="grid grid-cols-2 gap-4 md:grid-cols-4">
                        {modulePermissions.map((permission) => {
                            // Permission name format usually "module-action"
                            const action = permission.name
                                .split('-')
                                .slice(1)
                                .join('-');

                            return (
                                <div
                                    key={permission.id}
                                    className="group flex items-center space-x-2"
                                >
                                    <Checkbox
                                        id={`perm-${permission.id}`}
                                        checked={selectedPermissions.includes(
                                            permission.name,
                                        )}
                                        onCheckedChange={() =>
                                            handlePermissionToggle(
                                                permission.name,
                                            )
                                        }
                                        className="border-zinc-300 data-[state=checked]:bg-zinc-900 data-[state=checked]:text-zinc-50 dark:border-zinc-600 dark:data-[state=checked]:bg-zinc-50 dark:data-[state=checked]:text-zinc-900"
                                    />
                                    <Label
                                        htmlFor={`perm-${permission.id}`}
                                        className="cursor-pointer text-zinc-600 capitalize transition-colors group-hover:text-zinc-900 dark:text-zinc-400 dark:group-hover:text-zinc-200"
                                    >
                                        {action || permission.name}
                                    </Label>
                                </div>
                            );
                        })}
                    </div>
                </div>
            ))}
        </div>
    );
}
