import { Link } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { ChevronLeft, ChevronRight } from 'lucide-react';

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface Props {
    links: PaginationLink[];
    from?: number;
    to?: number;
    total?: number;
    label?: string;
}

export default function Pagination({
    links,
    from,
    to,
    total,
    label = 'results',
}: Props) {
    // If no records at all, don't show anything
    if (total === 0) return null;

    return (
        <div className="flex w-full flex-col items-center justify-between gap-4 sm:flex-row">
            {/* Informative Stats */}
            {from !== undefined && to !== undefined && total !== undefined && (
                <div className="order-2 text-sm text-zinc-500 sm:order-1 dark:text-zinc-400">
                    Showing{' '}
                    <span className="font-bold text-zinc-900 dark:text-zinc-100">
                        {from}
                    </span>{' '}
                    -{' '}
                    <span className="font-bold text-zinc-900 dark:text-zinc-100">
                        {to}
                    </span>{' '}
                    of{' '}
                    <span className="font-bold text-zinc-900 dark:text-zinc-100">
                        {total}
                    </span>{' '}
                    {label}
                </div>
            )}

            {/* Pagination Controls - Only show if more than one page exists */}
            {links.length > 3 && (
                <div className="order-1 flex items-center gap-1 sm:order-2">
                    {links.map((link, key) => {
                        // Determine label content (stripping HTML entities usually returned by Laravel)
                        let labelContent = link.label;
                        const isPrev = labelContent.includes('Previous');
                        const isNext = labelContent.includes('Next');

                        if (isPrev) labelContent = 'Previous';
                        if (isNext) labelContent = 'Next';

                        // For numbers, we want a clean look
                        const isNumber = !isPrev && !isNext;

                        if (link.url === null) {
                            return (
                                <Button
                                    key={key}
                                    variant="outline"
                                    size="sm"
                                    disabled
                                    className="h-9 min-w-[36px] cursor-not-allowed border-zinc-200 px-3 text-zinc-400 opacity-50 dark:border-zinc-800"
                                >
                                    {isPrev && (
                                        <ChevronLeft className="mr-1 h-4 w-4" />
                                    )}
                                    <span
                                        dangerouslySetInnerHTML={{
                                            __html: labelContent,
                                        }}
                                    />
                                    {isNext && (
                                        <ChevronRight className="ml-1 h-4 w-4" />
                                    )}
                                </Button>
                            );
                        }

                        return (
                            <Link
                                key={key}
                                href={link.url}
                                className={isNumber ? 'hidden md:block' : ''}
                            >
                                <Button
                                    variant={
                                        link.active ? 'default' : 'outline'
                                    }
                                    size="sm"
                                    className={`h-9 min-w-[36px] px-3 transition-all duration-200 ${
                                        link.active
                                            ? 'border-blue-600 bg-blue-600 font-bold text-white shadow-sm hover:bg-blue-700'
                                            : 'border-zinc-200 text-zinc-600 hover:bg-zinc-100 dark:border-zinc-800 dark:text-zinc-300 dark:hover:bg-zinc-800'
                                    }`}
                                >
                                    {isPrev && (
                                        <ChevronLeft className="mr-1 h-4 w-4" />
                                    )}
                                    <span
                                        dangerouslySetInnerHTML={{
                                            __html: labelContent,
                                        }}
                                    />
                                    {isNext && (
                                        <ChevronRight className="ml-1 h-4 w-4" />
                                    )}
                                </Button>
                            </Link>
                        );
                    })}

                    {/* Mobile compact number (optional/extra) */}
                    <div className="flex h-9 items-center rounded-md border border-zinc-200 bg-zinc-100 px-3 md:hidden dark:border-zinc-800 dark:bg-zinc-800">
                        <span className="text-xs font-bold text-zinc-600 dark:text-zinc-300">
                            Page {links.find((l) => l.active)?.label}
                        </span>
                    </div>
                </div>
            )}
        </div>
    );
}
