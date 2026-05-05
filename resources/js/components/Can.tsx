import { usePermission } from '@/hooks/usePermission';
import { ReactNode } from 'react';

interface Props {
    permission?: string;
    role?: string;
    children: ReactNode;
}

export default function Can({ permission, role, children }: Props) {
    const { hasPermission, hasRole } = usePermission();

    if (permission && hasPermission(permission)) {
        return <>{children}</>;
    }

    if (role && hasRole(role)) {
        return <>{children}</>;
    }

    return null;
}
