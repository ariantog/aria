import { Link } from '@inertiajs/react';
import { ChevronLeft, ChevronRight } from 'lucide-react';
import { Button } from '@/components/ui/button';

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface Props {
    links: PaginationLink[];
}

export default function Pagination({ links }: Props) {
    // Standard Laravel pagination returns 3 links when there's only 1 page (Prev, 1, Next)
    // If only 1 page, we often don't want to show pagination, but let's check
    if (links.length <= 3) return null;

    return (
        <div className="mt-6 flex flex-wrap justify-center gap-1">
            {links.map((link, key) => {
                // Determine label content (stripping HTML entities usually returned by Laravel)
                let label = link.label;
                if (label.includes('&laquo;')) label = '«';
                if (label.includes('&raquo;')) label = '»';
                if (label.includes('Previous')) label = 'Previous';
                if (label.includes('Next')) label = 'Next';

                return link.url === null ? (
                    <Button
                        key={key}
                        variant="outline"
                        size="sm"
                        disabled
                        className="h-8 min-w-[32px] cursor-not-allowed px-3 text-zinc-400 opacity-50"
                    >
                        <span
                            dangerouslySetInnerHTML={{ __html: link.label }}
                        />
                    </Button>
                ) : (
                    <Link key={key} href={link.url}>
                        <Button
                            variant={link.active ? 'default' : 'outline'}
                            size="sm"
                            className={`h-8 min-w-[32px] px-3 ${link.active ? 'border-blue-600 bg-blue-600 text-white hover:bg-blue-700' : 'text-zinc-600 hover:bg-zinc-100 dark:text-zinc-300 dark:hover:bg-zinc-800'}`}
                        >
                            <span
                                dangerouslySetInnerHTML={{ __html: link.label }}
                            />
                        </Button>
                    </Link>
                );
            })}
        </div>
    );
}
