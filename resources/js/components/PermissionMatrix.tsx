import { Search } from 'lucide-react';
import { useState, useMemo, useEffect } from 'react';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';

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
    const modules = useMemo(
        () => Object.keys(permissions).sort((a, b) => a.localeCompare(b)),
        [permissions],
    );
    const [activeGroup, setActiveGroup] = useState('');
    const [searchTerm, setSearchTerm] = useState('');

    // Sync activeGroup when permissions load or current activeGroup disappears
    useEffect(() => {
        if (modules.length > 0) {
            if (!activeGroup || !modules.includes(activeGroup)) {
                setActiveGroup(modules[0]);
            }
        } else {
            setActiveGroup('');
        }
    }, [modules, activeGroup]);

    const formatModuleName = (name: string) => {
        const withSpaces = name.replace(/([A-Z])/g, ' $1');
        return withSpaces
            .split(/[_-]/)
            .filter(Boolean)
            .map(
                (word) => word.charAt(0).toUpperCase() + word.slice(1).toLowerCase(),
            )
            .join(' ')
            .trim();
    };

    const filteredModules = useMemo(() => {
        if (!searchTerm) return modules;
        return modules.filter((module) =>
            formatModuleName(module)
                .toLowerCase()
                .includes(searchTerm.toLowerCase()),
        );
    }, [modules, searchTerm]);

    const getActionLabel = (permissionName: string, moduleKey: string) => {
        if (permissionName.startsWith(moduleKey)) {
            const action = permissionName
                .substring(moduleKey.length)
                .replace(/^[_-]/, '');
            return action.replace(/[_-]/g, ' ');
        }
        return permissionName;
    };

    const getGroupCounts = (moduleKey: string) => {
        const groupPermissions = permissions[moduleKey] || [];
        const total = groupPermissions.length;
        const selected = groupPermissions.filter((p) =>
            selectedPermissions.includes(p.name),
        ).length;
        return { total, selected };
    };

    const isGroupSelected = (groupPermissions: { name: string }[]) => {
        if (!groupPermissions || groupPermissions.length === 0) return false;
        return groupPermissions.every((p) =>
            selectedPermissions.includes(p.name),
        );
    };

    const handleGroupToggle = (groupPermissions: { name: string }[]) => {
        if (!groupPermissions) return;
        const allSelected = isGroupSelected(groupPermissions);
        const groupNames = groupPermissions.map((p) => p.name);

        if (allSelected) {
            onChange(
                selectedPermissions.filter(
                    (name) => !groupNames.includes(name),
                ),
            );
        } else {
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
        <div className="flex flex-col gap-6 lg:flex-row">
            {/* Sidebar: Group List */}
            <div className="w-full shrink-0 lg:w-80">
                <div className="flex flex-col gap-3 rounded-xl border border-zinc-800 bg-zinc-950/50 p-3">
                    {/* Search Input */}
                    <div className="relative">
                        <Search className="absolute top-2.5 left-3 h-4 w-4 text-zinc-500" />
                        <Input
                            type="text"
                            placeholder="Search groups..."
                            value={searchTerm}
                            onChange={(e) => setSearchTerm(e.target.value)}
                            className="h-9 border-zinc-800 bg-zinc-900 pl-9 text-sm text-zinc-200 placeholder:text-zinc-600 focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
                        />
                    </div>

                    {/* Scrollable Group List */}
                    <div className="max-h-[500px] space-y-1 overflow-y-auto pr-1 scrollbar-thin scrollbar-track-zinc-900 scrollbar-thumb-zinc-700">
                        {filteredModules.length > 0 ? (
                            filteredModules.map((module) => {
                                const { total, selected } =
                                    getGroupCounts(module);
                                const isActive = activeGroup === module;

                                return (
                                    <button
                                        key={module}
                                        type="button"
                                        onClick={() => setActiveGroup(module)}
                                        className={cn(
                                            'flex w-full items-center justify-between rounded-lg px-3 py-2.5 text-left transition-all',
                                            isActive
                                                ? 'bg-blue-600 text-white shadow-lg shadow-blue-900/20'
                                                : 'text-zinc-400 hover:bg-zinc-800 hover:text-zinc-200',
                                        )}
                                    >
                                        <span className="truncate text-sm font-medium">
                                            {formatModuleName(module)}
                                        </span>
                                        <span
                                            className={cn(
                                                'ml-2 shrink-0 rounded-full px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider',
                                                isActive
                                                    ? 'bg-blue-500 text-white'
                                                    : selected === total &&
                                                        total > 0
                                                      ? 'bg-green-500/10 text-green-500'
                                                      : selected > 0
                                                        ? 'bg-blue-500/10 text-blue-500'
                                                        : 'bg-zinc-800 text-zinc-500',
                                            )}
                                        >
                                            {selected} / {total}
                                        </span>
                                    </button>
                                );
                            })
                        ) : (
                            <div className="py-8 text-center text-xs text-zinc-600">
                                No groups found
                            </div>
                        )}
                    </div>
                </div>
            </div>

            {/* Content: Permission List */}
            <div className="min-w-0 flex-1">
                {activeGroup && permissions[activeGroup] ? (
                    <div className="h-full rounded-xl border border-zinc-800 bg-zinc-900/50 p-6">
                        <div className="mb-6 flex items-center justify-between border-b border-zinc-800 pb-4">
                            <div>
                                <h4 className="text-xl font-bold text-white">
                                    {formatModuleName(activeGroup)}
                                </h4>
                                <p className="text-sm text-zinc-500">
                                    Manage permissions for this module.
                                </p>
                            </div>
                            <div className="flex items-center space-x-2">
                                <Checkbox
                                    id={`select-all-${activeGroup}`}
                                    checked={isGroupSelected(
                                        permissions[activeGroup],
                                    )}
                                    onCheckedChange={() =>
                                        handleGroupToggle(
                                            permissions[activeGroup],
                                        )
                                    }
                                    className="border-zinc-700 data-[state=checked]:bg-blue-600 data-[state=checked]:text-white"
                                />
                                <Label
                                    htmlFor={`select-all-${activeGroup}`}
                                    className="cursor-pointer text-sm font-medium text-zinc-400 transition-colors hover:text-zinc-200"
                                >
                                    Select All
                                </Label>
                            </div>
                        </div>

                        <div className="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
                            {permissions[activeGroup].map((permission) => {
                                const action = getActionLabel(
                                    permission.name,
                                    activeGroup,
                                );
                                const isSelected = selectedPermissions.includes(
                                    permission.name,
                                );

                                return (
                                    <div
                                        key={permission.id}
                                        className={cn(
                                            'group flex items-center space-x-3 rounded-lg border border-zinc-800 p-4 transition-all hover:border-zinc-700',
                                            isSelected
                                                ? 'bg-blue-500/5 border-blue-500/30'
                                                : 'bg-zinc-950/30',
                                        )}
                                    >
                                        <Checkbox
                                            id={`perm-${permission.id}`}
                                            checked={isSelected}
                                            onCheckedChange={() =>
                                                handlePermissionToggle(
                                                    permission.name,
                                                )
                                            }
                                            className="border-zinc-700 data-[state=checked]:bg-blue-600 data-[state=checked]:text-white"
                                        />
                                        <Label
                                            htmlFor={`perm-${permission.id}`}
                                            className="flex-1 cursor-pointer text-sm font-medium text-zinc-300 capitalize transition-colors group-hover:text-white"
                                        >
                                            {action}
                                        </Label>
                                    </div>
                                );
                            })}
                        </div>
                    </div>
                ) : (
                    <div className="flex h-full items-center justify-center rounded-xl border border-zinc-800 bg-zinc-900/50 p-6 text-zinc-500">
                        Select a group from the sidebar to view permissions.
                    </div>
                )}
            </div>
        </div>
    );
}
