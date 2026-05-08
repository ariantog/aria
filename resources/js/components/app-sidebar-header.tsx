import { Link } from '@inertiajs/react';
import { ShoppingCart, Tag, ArrowRightLeft, CornerUpLeft } from 'lucide-react';
import { Breadcrumbs } from '@/components/breadcrumbs';
import { Button } from '@/components/ui/button';
import { SidebarTrigger } from '@/components/ui/sidebar';
import type { BreadcrumbItem as BreadcrumbItemType } from '@/types';

export function AppSidebarHeader({
    breadcrumbs = [],
}: {
    breadcrumbs?: BreadcrumbItemType[];
}) {
    return (
        <header className="flex h-16 shrink-0 items-center justify-between gap-2 border-b border-sidebar-border/50 px-6 transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-12 md:px-4">
            <div className="flex items-center gap-2">
                <SidebarTrigger className="-ml-1" />
                <Breadcrumbs breadcrumbs={breadcrumbs} />
            </div>

            <div className="flex items-center gap-2">
                <Link href="/transactions/buy/create">
                    <Button
                        variant="outline"
                        size="sm"
                        className="h-8 gap-1 rounded-full border-emerald-200 bg-emerald-50 text-emerald-700 hover:bg-emerald-100 hover:text-emerald-800 dark:border-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-300 dark:hover:bg-emerald-900/50"
                    >
                        <ShoppingCart className="h-3.5 w-3.5" />
                        <span className="hidden font-semibold sm:inline">
                            Buy
                        </span>
                    </Button>
                </Link>
                <Link href="/transactions/sell/create">
                    <Button
                        variant="outline"
                        size="sm"
                        className="h-8 gap-1 rounded-full border-blue-200 bg-blue-50 text-blue-700 hover:bg-blue-100 hover:text-blue-800 dark:border-blue-800 dark:bg-blue-900/30 dark:text-blue-300 dark:hover:bg-blue-900/50"
                    >
                        <Tag className="h-3.5 w-3.5" />
                        <span className="hidden font-semibold sm:inline">
                            Sell
                        </span>
                    </Button>
                </Link>
                <Link href="/transactions/move/create">
                    <Button
                        variant="outline"
                        size="sm"
                        className="h-8 gap-1 rounded-full border-amber-200 bg-amber-50 text-amber-700 hover:bg-amber-100 hover:text-amber-800 dark:border-amber-800 dark:bg-amber-900/30 dark:text-amber-300 dark:hover:bg-amber-900/50"
                    >
                        <ArrowRightLeft className="h-3.5 w-3.5" />
                        <span className="hidden font-semibold sm:inline">
                            Move
                        </span>
                    </Button>
                </Link>
            </div>
        </header>
    );
}
