import { Head, Link, router, useForm } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { BreadcrumbItem } from '@/types';
import { Search, Link as LinkIcon, ArrowLeft } from 'lucide-react';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { useState } from 'react';
import { toast } from 'sonner';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Items', href: '/items' },
    { title: 'Item Details', href: '#' },
    { title: 'Jubelio Search', href: '#' },
];

interface Item {
    id: number;
    code: string;
    name: string;
}

interface JubelioItem {
    item_id: number;
    item_code: string;
    item_name: string;
}

interface Props {
    item: Item;
    jubelioItems: JubelioItem[];
    query: string;
}

export default function JubelioSearch({ item, jubelioItems, query }: Props) {
    const [searchQuery, setSearchQuery] = useState(query);

    const handleSearch = (e: React.FormEvent) => {
        e.preventDefault();
        router.get(
            `/items/${item.id}/jubelio-search`,
            { q: searchQuery },
            { preserveState: true },
        );
    };

    const handleLink = (jubelioId: number) => {
        router.post(
            `/items/${item.id}/jubelio-link`,
            { jubelio_item_id: jubelioId },
            {
                onSuccess: () => toast.success('Item linked to Jubelio'),
            },
        );
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Link Jubelio Item" />

            <div className="min-h-screen flex-1 bg-[#0A0A0A] p-8 text-gray-300">
                <div className="mx-auto max-w-4xl space-y-8">
                    <div className="flex items-center gap-4">
                        <Link href={`/items/${item.id}/jubelio`}>
                            <Button
                                variant="ghost"
                                size="icon"
                                className="rounded-full hover:bg-gray-800"
                            >
                                <ArrowLeft className="h-5 w-5" />
                            </Button>
                        </Link>
                        <div>
                            <h1 className="text-2xl font-bold text-white">
                                Link Item to Jubelio
                            </h1>
                            <p className="text-gray-500">
                                Current item:{' '}
                                <span className="font-mono text-blue-400">
                                    {item.code}
                                </span>{' '}
                                - {item.name}
                            </p>
                        </div>
                    </div>

                    <Card className="overflow-hidden rounded-2xl border-gray-800 bg-[#111] shadow-xl">
                        <CardHeader className="border-b border-gray-800 bg-[#161616]">
                            <CardTitle className="text-white">
                                Search Jubelio Catalog
                            </CardTitle>
                            <form
                                onSubmit={handleSearch}
                                className="flex gap-2 pt-4"
                            >
                                <Input
                                    value={searchQuery}
                                    onChange={(e) =>
                                        setSearchQuery(e.target.value)
                                    }
                                    placeholder="Search by code or name..."
                                    className="rounded-xl border-gray-800 bg-[#0A0A0A]"
                                />
                                <Button
                                    type="submit"
                                    className="rounded-xl bg-blue-600 px-6 text-white hover:bg-blue-700"
                                >
                                    <Search className="mr-2 h-4 w-4" /> Search
                                </Button>
                            </form>
                        </CardHeader>
                        <CardContent className="p-0">
                            <Table>
                                <TableHeader className="bg-[#161616]/50">
                                    <TableRow className="border-gray-800">
                                        <TableHead className="text-gray-400">
                                            Jubelio Code
                                        </TableHead>
                                        <TableHead className="text-gray-400">
                                            Item Name
                                        </TableHead>
                                        <TableHead className="text-right text-gray-400">
                                            Action
                                        </TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {jubelioItems.length === 0 ? (
                                        <TableRow className="border-gray-800">
                                            <TableCell
                                                colSpan={3}
                                                className="py-12 text-center text-gray-500 italic"
                                            >
                                                No results found in Jubelio.
                                            </TableCell>
                                        </TableRow>
                                    ) : (
                                        jubelioItems.map((jubItem) => (
                                            <TableRow
                                                key={jubItem.item_id}
                                                className="group border-gray-800 hover:bg-[#161616]/50"
                                            >
                                                <TableCell className="font-mono text-blue-400">
                                                    {jubItem.item_code}
                                                </TableCell>
                                                <TableCell className="text-white">
                                                    {jubItem.item_name}
                                                </TableCell>
                                                <TableCell className="text-right">
                                                    <Button
                                                        size="sm"
                                                        onClick={() =>
                                                            handleLink(
                                                                jubItem.item_id,
                                                            )
                                                        }
                                                        className="rounded-lg border border-green-600/20 bg-green-600/10 text-green-500 hover:bg-green-600 hover:text-white"
                                                    >
                                                        <LinkIcon className="mr-2 h-3 w-3" />{' '}
                                                        Link
                                                    </Button>
                                                </TableCell>
                                            </TableRow>
                                        ))
                                    )}
                                </TableBody>
                            </Table>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </AppLayout>
    );
}
