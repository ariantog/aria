import { usePage } from '@inertiajs/react';

export function usePermission() {
    const { auth } = usePage().props as any;

    const hasPermission = (permission: string) => {
        if (!auth?.permissions) return false;

        // Super Admin Bypass
        if (auth.roles && auth.roles.includes('superadmin')) return true;

        // Wildcard Permission Check
        if (auth.permissions.includes('*')) return true;

        return auth.permissions.includes(permission);
    };

    const hasRole = (role: string) => {
        if (!auth?.roles) return false;
        return auth.roles.includes(role);
    };

    return { hasPermission, hasRole };
}
