import { Head, Link, useForm } from '@inertiajs/react';
import { ShieldAlert, LogOut } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { logout } from '@/routes';

export default function Banned() {
    const { post } = useForm();

    const handleLogout = (e: React.FormEvent) => {
        e.preventDefault();
        post(logout.url());
    };

    return (
        <div className="flex min-h-screen flex-col items-center justify-center bg-zinc-950 p-4 text-zinc-100">
            <Head title="Account Suspended" />

            <div className="w-full max-w-md space-y-8 text-center">
                <div className="mx-auto flex h-24 w-24 items-center justify-center rounded-full bg-red-900/20">
                    <ShieldAlert className="h-12 w-12 text-red-500" />
                </div>

                <div className="space-y-4">
                    <h1 className="text-3xl font-bold tracking-tighter text-white sm:text-4xl">
                        Account Suspended
                    </h1>
                    <p className="text-lg text-zinc-400">
                        Your account has been deactivated by an administrator.
                        You strictly cannot access this application.
                    </p>
                </div>

                <div className="rounded-lg border border-zinc-800 bg-zinc-900/50 p-6 text-sm text-zinc-400">
                    <p>
                        If you believe this is a mistake, please contact support
                        immediately with your account details.
                    </p>
                    <div className="mt-4 border-t border-zinc-800 pt-4">
                        <p className="font-mono text-zinc-300">
                            support@active-aria.test
                        </p>
                    </div>
                </div>

                <div className="flex justify-center">
                    <form onSubmit={handleLogout}>
                        <Button
                            variant="outline"
                            className="gap-2 border-zinc-700 hover:bg-zinc-800 hover:text-white"
                        >
                            <LogOut className="h-4 w-4" />
                            Sign Out
                        </Button>
                    </form>
                </div>
            </div>
        </div>
    );
}
