
import { usePage } from '@inertiajs/react';

export function usePermission() {
    const { auth } = usePage().props as any;

    const hasPermission = (permission: string) => {
        if (!auth?.permissions) return false;

        // Super Admin Bypass (optional, if you want frontend to reflect backend god mode)
        // Generally backend handles security, but frontend UX should match.
        if (auth.roles && auth.roles.includes('superadmin')) return true;

        return auth.permissions.includes(permission);
    };

    const hasRole = (role: string) => {
        if (!auth?.roles) return false;
        return auth.roles.includes(role);
    };

    return { hasPermission, hasRole };
}
