import { Head, Link, router } from '@inertiajs/react';
import {
    ArrowLeft,
    Package,
    Search,
    Download,
    X,
    Eye,
    EyeOff,
    ImageIcon,
    FilePen,
} from 'lucide-react';
import { useState } from 'react';
import Pagination from '@/components/pagination';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import type { BreadcrumbItem } from '@/types';

interface Addrbook {
    id: number;
    name: string;
    type: number;
    type_slug: string;
}

interface ItemGroup {
    id: number;
    name: string;
    description: string | null;
    description2: string | null;
    image_url: string;
}

interface Item {
    id: number;
    name: string;
    code: string;
    image_url: string;
    price: string;
    description: string | null;
    group?: ItemGroup;
    pivot: {
        quantity: string;
    };
}

interface PaginatedItems {
    data: Item[];
    links: any[];
    current_page: number;
    last_page: number;
    total: number;
}

interface Props {
    addrbook: Addrbook;
    items: PaginatedItems;
    filters: {
        name?: string;
        sort?: string;
        show0?: boolean;
    };
    can: {
        bank_hidden_balance?: boolean;
    };
}

export default function AddrbookItems({
    addrbook,
    items,
    filters,
    can,
}: Props) {
    const [name, setName] = useState(filters.name || '');
    const [sort, setSort] = useState(filters.sort || 'qtydesc');
    const [show0, setShow0] = useState(filters.show0 || false);

    const [showImage, setShowImage] = useState(false);
    const [isOnlineName, setIsOnlineName] = useState(false);

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Address Book', href: '/addrbook' },
        { title: addrbook.name, href: `/addrbook/${addrbook.id}` },
        { title: 'Items', href: '#' },
    ];

    const handleFilter = (e?: React.FormEvent) => {
        if (e) e.preventDefault();
        router.get(
            `/${addrbook.type_slug}/${addrbook.id}/items`,
            {
                name,
                sort,
                show0: show0 ? 'show' : '',
            },
            { preserveState: true },
        );
    };

    const clearFilters = () => {
        setName('');
        setSort('qtydesc');
        setShow0(false);
        router.get(`/${addrbook.type_slug}/${addrbook.id}/items`);
    };

    const formatCurrency = (value: string | number) => {
        return new Intl.NumberFormat('id-ID', {
            style: 'currency',
            currency: 'IDR',
            minimumFractionDigits: 0,
        }).format(Number(value) || 0);
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Items: ${addrbook.name}`} />

            <div className="flex h-full min-h-screen flex-1 flex-col bg-[#0A0A0A] font-sans text-gray-300 antialiased">
                <div className="flex-1 p-8">
                    {/* Header */}
                    <div className="mb-8 flex flex-col justify-between gap-4 md:flex-row md:items-end">
                        <div>
                            <div className="mb-2 flex items-center gap-2">
                                <Link
                                    href={`/addrbook/${addrbook.id}`}
                                    className="text-gray-500 transition-colors hover:text-white"
                                >
                                    <ArrowLeft className="h-4 w-4" />
                                </Link>
                                <span className="font-mono text-sm text-zinc-600">
                                    #{addrbook.id}
                                </span>
                            </div>
                            <h1 className="mb-1 text-2xl font-bold text-white">
                                Warehouse Stock
                            </h1>
                            <p className="text-sm text-gray-500">
                                Available inventory for{' '}
                                <span className="text-blue-400">
                                    {addrbook.name}
                                </span>
                            </p>
                        </div>

                        <div className="flex gap-2">
                            <Button className="border-0 bg-emerald-600 text-white hover:bg-emerald-500">
                                <Download className="mr-2 h-4 w-4" />
                                Download CSV
                            </Button>
                        </div>
                    </div>

                    {/* Tabs Navigation */}
                    <div className="mb-8 flex overflow-x-auto border-b border-gray-800">
                        <Link
                            href={`/${addrbook.type_slug}/${addrbook.id}`}
                            className="border-b-2 border-transparent px-6 py-4 text-sm font-medium whitespace-nowrap text-gray-500 transition-all hover:text-white"
                        >
                            Detail
                        </Link>
                        <Link
                            href={`/${addrbook.type_slug}/${addrbook.id}/transactions`}
                            className="border-b-2 border-transparent px-6 py-4 text-sm font-medium whitespace-nowrap text-gray-500 transition-all hover:text-white"
                        >
                            Transaction
                        </Link>
                        <Link
                            href={`/${addrbook.type_slug}/${addrbook.id}/items`}
                            className="border-b-2 border-blue-500 px-6 py-4 text-sm font-medium whitespace-nowrap text-blue-500 transition-all"
                        >
                            Items
                        </Link>
                        <Link
                            href={`/${addrbook.type_slug}/${addrbook.id}/stats`}
                            className="border-b-2 border-transparent px-6 py-4 text-sm font-medium whitespace-nowrap text-gray-500 transition-all hover:text-white"
                        >
                            Stats
                        </Link>
                        <Link
                            href={`/${addrbook.type_slug}/${addrbook.id}/item-sales`}
                            className="border-b-2 border-transparent px-6 py-4 text-sm font-medium whitespace-nowrap text-gray-500 transition-all hover:text-white"
                        >
                            Item Sale
                        </Link>
                    </div>

                    {/* Filters & Toggles */}
                    <div className="mb-8 space-y-6 rounded-xl border border-gray-800 bg-[#111] p-6">
                        <form
                            onSubmit={handleFilter}
                            className="grid grid-cols-1 items-end gap-4 border-b border-gray-800/50 pb-6 md:grid-cols-4"
                        >
                            <div>
                                <label className="mb-2 block text-[10px] font-bold text-gray-500 uppercase">
                                    Item Name / Code
                                </label>
                                <div className="relative">
                                    <Search className="absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-gray-500" />
                                    <input
                                        type="text"
                                        placeholder="Search..."
                                        value={name}
                                        onChange={(e) =>
                                            setName(e.target.value)
                                        }
                                        className="w-full rounded-lg border border-gray-800 bg-[#161616] py-2 pr-4 pl-10 text-sm text-white focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
                                    />
                                </div>
                            </div>
                            <div>
                                <label className="mb-2 block text-[10px] font-bold text-gray-500 uppercase">
                                    Sort By
                                </label>
                                <select
                                    value={sort}
                                    onChange={(e) => setSort(e.target.value)}
                                    className="w-full rounded-lg border border-gray-800 bg-[#161616] px-4 py-2 text-sm text-white focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
                                >
                                    <option value="qtydesc">
                                        Quantity (High to Low)
                                    </option>
                                    <option value="qtyasc">
                                        Quantity (Low to High)
                                    </option>
                                    <option value="namedesc">Name (Z-A)</option>
                                    <option value="nameasc">Name (A-Z)</option>
                                    <option value="codedesc">Code (Z-A)</option>
                                    <option value="codeasc">Code (A-Z)</option>
                                </select>
                            </div>
                            <div className="flex items-center gap-3 py-2.5">
                                <label className="flex cursor-pointer items-center">
                                    <div className="relative">
                                        <input
                                            type="checkbox"
                                            className="sr-only"
                                            checked={show0}
                                            onChange={(e) =>
                                                setShow0(e.target.checked)
                                            }
                                        />
                                        <div
                                            className={cn(
                                                'h-5 w-10 rounded-full transition-colors',
                                                show0
                                                    ? 'bg-blue-600'
                                                    : 'bg-gray-700',
                                            )}
                                        ></div>
                                        <div
                                            className={cn(
                                                'absolute top-1 left-1 h-3 w-3 rounded-full bg-white transition-transform',
                                                show0
                                                    ? 'translate-x-5'
                                                    : 'translate-x-0',
                                            )}
                                        ></div>
                                    </div>
                                    <span className="ml-3 text-sm font-medium text-gray-400">
                                        Show Zero Stock
                                    </span>
                                </label>
                            </div>
                            <div className="flex gap-2">
                                <Button
                                    type="submit"
                                    className="flex-1 border-0 bg-blue-600 text-white hover:bg-blue-500"
                                >
                                    Apply Filter
                                </Button>
                                <Button
                                    type="button"
                                    onClick={clearFilters}
                                    variant="outline"
                                    className="border-gray-800 text-gray-400 hover:text-white"
                                >
                                    <X className="h-4 w-4" />
                                </Button>
                            </div>
                        </form>

                        <div className="flex flex-wrap items-center gap-8">
                            <div className="flex items-center gap-3">
                                <span className="text-[10px] font-bold text-gray-500 uppercase">
                                    Display Options:
                                </span>
                                <Button
                                    size="sm"
                                    variant="outline"
                                    onClick={() => setShowImage(!showImage)}
                                    className={cn(
                                        'h-8 border-gray-800 px-3',
                                        showImage
                                            ? 'border-blue-500/20 bg-blue-600/10 text-blue-400'
                                            : 'text-gray-500',
                                    )}
                                >
                                    <ImageIcon className="mr-2 h-3.5 w-3.5" />
                                    {showImage ? 'Hide Image' : 'Show Image'}
                                </Button>

                                <div className="flex rounded-lg border border-gray-800 bg-[#161616] p-1">
                                    <button
                                        onClick={() => setIsOnlineName(false)}
                                        className={cn(
                                            'rounded-md px-3 py-1 text-[10px] font-bold uppercase transition-all',
                                            !isOnlineName
                                                ? 'bg-gray-800 text-white'
                                                : 'text-gray-600 hover:text-gray-400',
                                        )}
                                    >
                                        Normal Name
                                    </button>
                                    <button
                                        onClick={() => setIsOnlineName(true)}
                                        className={cn(
                                            'rounded-md px-3 py-1 text-[10px] font-bold uppercase transition-all',
                                            isOnlineName
                                                ? 'bg-gray-800 text-white'
                                                : 'text-gray-600 hover:text-gray-400',
                                        )}
                                    >
                                        Online Name
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    {/* Items Table */}
                    <div className="overflow-hidden rounded-2xl border border-gray-800 bg-[#111] shadow-xl">
                        <div className="overflow-x-auto">
                            <table className="w-full border-collapse text-left">
                                <thead>
                                    <tr className="border-b border-gray-800 bg-[#161616]">
                                        <th className="px-6 py-4 text-[10px] font-bold tracking-widest text-gray-500 uppercase">
                                            ID
                                        </th>
                                        {showImage && (
                                            <th className="px-6 py-4 text-center text-[10px] font-bold tracking-widest text-gray-500 uppercase">
                                                Image
                                            </th>
                                        )}
                                        <th className="px-6 py-4 text-[10px] font-bold tracking-widest text-gray-500 uppercase">
                                            {isOnlineName
                                                ? 'Online Product Name'
                                                : 'Item Name'}
                                        </th>
                                        <th className="px-6 py-4 text-[10px] font-bold tracking-widest text-gray-500 uppercase">
                                            Code
                                        </th>
                                        <th className="px-6 py-4 text-[10px] font-bold tracking-widest text-gray-500 uppercase">
                                            Description
                                        </th>
                                        <th className="px-6 py-4 text-right text-[10px] font-bold tracking-widest text-gray-500 uppercase">
                                            Price
                                        </th>
                                        <th className="px-6 py-4 text-right text-[10px] font-bold tracking-widest text-gray-500 uppercase">
                                            Stock
                                        </th>
                                        <th className="px-6 py-4 text-center text-[10px] font-bold tracking-widest text-gray-500 uppercase">
                                            Action
                                        </th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-gray-800/50">
                                    {items.data.length > 0 ? (
                                        items.data.map((item) => (
                                            <tr
                                                key={item.id}
                                                className="group transition-colors hover:bg-white/[0.02]"
                                            >
                                                <td className="px-6 py-4 font-mono text-xs whitespace-nowrap text-zinc-600">
                                                    #{item.id}
                                                </td>
                                                {showImage && (
                                                    <td className="px-6 py-4 text-center whitespace-nowrap">
                                                        <div className="inline-block rounded-lg border border-gray-800 bg-white/5 p-1">
                                                            <img
                                                                src={
                                                                    item.image_url
                                                                }
                                                                alt={item.name}
                                                                className="h-10 w-10 rounded-md object-cover"
                                                                onError={(
                                                                    e,
                                                                ) => {
                                                                    (
                                                                        e.target as HTMLImageElement
                                                                    ).src =
                                                                        '/images/default-item.png';
                                                                }}
                                                            />
                                                        </div>
                                                    </td>
                                                )}
                                                <td className="px-6 py-4">
                                                    <div className="flex flex-col">
                                                        <span className="text-sm font-medium text-gray-200">
                                                            {isOnlineName
                                                                ? item.group
                                                                      ?.description2 ||
                                                                  item.group
                                                                      ?.description ||
                                                                  item.name
                                                                : item.group
                                                                      ?.description ||
                                                                  item.name}
                                                        </span>
                                                        <span className="font-mono text-[10px] text-zinc-600">
                                                            {item.name}
                                                        </span>
                                                    </div>
                                                </td>
                                                <td className="px-6 py-4 font-mono text-xs text-blue-400">
                                                    {item.code}
                                                </td>
                                                <td className="min-w-[200px] px-6 py-4">
                                                    <p className="text-xs whitespace-normal text-gray-500">
                                                        {item.description ||
                                                            item.group
                                                                ?.description ||
                                                            '-'}
                                                    </p>
                                                </td>
                                                <td className="px-6 py-4 text-right whitespace-nowrap">
                                                    <span className="text-sm font-semibold text-gray-300">
                                                        {formatCurrency(
                                                            item.price,
                                                        )}
                                                    </span>
                                                </td>
                                                <td className="px-6 py-4 text-right whitespace-nowrap">
                                                    <span
                                                        className={cn(
                                                            'font-mono text-sm font-bold',
                                                            parseFloat(
                                                                item.pivot
                                                                    .quantity,
                                                            ) > 0
                                                                ? 'text-emerald-400'
                                                                : 'text-zinc-600',
                                                        )}
                                                    >
                                                        {parseFloat(
                                                            item.pivot.quantity,
                                                        ).toLocaleString()}
                                                    </span>
                                                </td>
                                                <td className="px-6 py-4 text-center">
                                                    <Link
                                                        href={`/items/${item.id}/edit`}
                                                    >
                                                        <Button
                                                            size="icon"
                                                            variant="ghost"
                                                            className="h-8 w-8 text-gray-500 hover:bg-blue-400/10 hover:text-blue-400"
                                                        >
                                                            <FilePen className="h-4 w-4" />
                                                        </Button>
                                                    </Link>
                                                </td>
                                            </tr>
                                        ))
                                    ) : (
                                        <tr>
                                            <td
                                                colSpan={showImage ? 8 : 7}
                                                className="px-6 py-12 text-center text-gray-500 italic"
                                            >
                                                No items found in this
                                                warehouse.
                                            </td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </div>

                        {/* Pagination */}
                        <div className="border-t border-gray-800 bg-[#161616] px-6 py-4">
                            <div className="flex items-center justify-between">
                                <p className="text-xs text-gray-500">
                                    Showing{' '}
                                    <span className="text-white">
                                        {items.data.length}
                                    </span>{' '}
                                    of{' '}
                                    <span className="text-white">
                                        {items.total}
                                    </span>{' '}
                                    items
                                </p>
                                <Pagination links={items.links} />
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
