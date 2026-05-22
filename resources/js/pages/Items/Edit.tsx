import { Head, useForm, Link, router } from '@inertiajs/react';
import { Info, FileText, Tag, Image as ImageIcon, Box, Package } from 'lucide-react';
import FormInput from '@/components/Partial/Form/FormInput';
import FormTextarea from '@/components/Partial/Form/FormTextarea';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import itemRoutes from '@/routes/items';
import type { BreadcrumbItem } from '@/types';
import AttributeSelector from './Partials/AttributeSelector';
import ImageUpload from './Partials/ImageUpload';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Items', href: '/items' },
    { title: 'Edit Item', href: '#' },
];

interface Item {
    id: number;
    pcode: string;
    alias?: string;
    name: string;
    brand: number;
    type: number;
    price: number;
    cost: number;
    description: string;
    description2: string;
    tags?: any[];
    image_path?: string;
    image_url?: string;
    group?: {
        alias?: string;
    };
}

interface Tag {
    id: number;
    name: string;
    code: string;
}

interface Props {
    item: Item;
    brands: { value: string | number; label: string }[];
    types: { value: string | number; label: string }[];
    tags: Record<number, Tag[]>; // 1: Type, 2: Size, 3: Warna, 4: Jahit
}

export default function ItemsEdit({ item, brands, types, tags }: Props) {
    const isAsset = Number(item.type) === 2;
    const pageTitle = isAsset ? 'Edit Asset' : 'Edit Item';

    // Tag Types matching App\Models\Tag constants
    const TYPE_JAHIT = 2;
    const TYPE_TYPE = 3;
    const TYPE_SIZE = 7;
    const TYPE_WARNA = 20;

    // Helper to find initial tag values
    const findTagId = (type: number) =>
        item.tags?.find((t: any) => t.type === type)?.id?.toString() || '';
    const findTagIds = (type: number) =>
        item.tags
            ?.filter((t: any) => t.type === type)
            .map((t: any) => t.id.toString()) || [];

    const { data, setData, post, processing, errors } = useForm({
        _method: 'PUT',
        pcode: item.pcode || '',
        name: item.name || '',
        alias: item.alias || item.group?.alias || '',
        brand: item.brand?.toString() || '',
        type: item.type?.toString() || '', // Kept for logic, but usually readonly or derived
        price: item.price?.toString() || '',
        cost: item.cost?.toString() || '',
        description: item.description || '',
        description2: item.description2 || '',
        image: null as File | null,
        tags: {
            types: findTagId(TYPE_TYPE), // Single
            sizes: findTagIds(TYPE_SIZE), // Multi
            warna: findTagId(TYPE_WARNA), // Single
            jahit: findTagId(TYPE_JAHIT), // Single (matching Create.tsx)
        },
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();

        const routeUrl = isAsset
            ? `/assetlancar/${item.id}`
            : itemRoutes.update.url({ item: item.id });

        post(routeUrl, {
            forceFormData: true,
        });
    };

    // Transform tags for selectors
    const typeOptions =
        tags[TYPE_TYPE]?.map((t) => ({ value: t.id, label: t.name })) || [];
    const sizeOptions =
        tags[TYPE_SIZE]?.map((t) => ({ value: t.id, label: t.name })) || [];
    const warnaOptions =
        tags[TYPE_WARNA]?.map((t) => ({ value: t.id, label: t.name })) || [];
    const jahitOptions =
        tags[TYPE_JAHIT]?.map((t) => ({ value: t.id, label: t.name })) || [];

    // Generate Preview Logic
    const getPreview = () => {
        const pcode = data.pcode.toUpperCase().trim();
        const alias = data.alias.toUpperCase().trim() || '???';
        if (!pcode) return null;

        const selectedSize = tags[TYPE_SIZE]?.find(
            (t) => t.id.toString() === (data.tags.sizes[0] || ''),
        );
        const selectedWarna = tags[TYPE_WARNA]?.find(
            (t) => t.id.toString() === (data.tags.warna as string),
        );

        const sizeCode = selectedSize?.code || '???';
        const warnaCode = selectedWarna?.code || '???';

        return {
            sku: `${pcode}-${warnaCode}-${sizeCode}`.toUpperCase(),
            name: `${alias} - ${warnaCode} - ${sizeCode}`.toUpperCase(),
        };
    };

    const preview = getPreview();

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={pageTitle} />

            <div className="p-4 sm:p-6 lg:p-8">
                {/* Header */}
                <div className="mb-8">
                    <h2 className="mb-2 text-3xl font-bold tracking-tight text-zinc-900 dark:text-zinc-50">
                        {pageTitle}
                    </h2>
                    <p className="text-zinc-500 dark:text-zinc-400">
                        Update details for {item.name}.
                    </p>
                </div>

                <form onSubmit={submit} className="space-y-8" noValidate>
                    {/* Generic Error Message */}
                    {(errors as any).message && (
                        <div className="mb-6 rounded-lg border border-red-500/20 bg-red-500/10 p-4">
                            <div className="flex items-center gap-2 font-medium text-red-500">
                                <Info className="h-5 w-5" />
                                <div className="text-zinc-900 dark:text-white">
                                    Failed to update{' '}
                                    {isAsset ? 'asset' : 'item'}
                                </div>
                            </div>
                            <p className="mt-1 ml-7 text-sm text-red-600 dark:text-red-400">
                                {(errors as any).message}
                            </p>
                        </div>
                    )}

                    <div className="grid grid-cols-1 gap-8 lg:grid-cols-3">
                        {/* Left Column: Basic Info, Details & Preview */}
                        <div className="space-y-6 lg:col-span-2">
                            {/* Card: Basic & Financial */}
                            <Card className="border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
                                <CardHeader>
                                    <div className="flex items-center gap-3">
                                        <div className="rounded-lg bg-blue-500/10 p-2">
                                            <Info className="h-5 w-5 text-blue-500" />
                                        </div>
                                        <CardTitle className="text-xl text-zinc-900 dark:text-zinc-50">
                                            Basic & Financial Information
                                        </CardTitle>
                                    </div>
                                </CardHeader>
                                <CardContent className="grid grid-cols-1 gap-6 md:grid-cols-2">
                                    <FormInput
                                        id="code"
                                        label="Code (PCode)"
                                        value={data.pcode}
                                        onChange={(e) =>
                                            setData('pcode', e.target.value)
                                        }
                                        error={errors.pcode}
                                        placeholder="e.g. BOXING-01"
                                        required
                                    />

                                    <FormInput
                                        id="alias"
                                        label="Alias"
                                        value={data.alias}
                                        onChange={(e) =>
                                            setData('alias', e.target.value)
                                        }
                                        error={errors.alias}
                                        placeholder="Alternative Name"
                                    />

                                    {isAsset && (
                                        <FormInput
                                            id="name"
                                            label="Name"
                                            value={data.name}
                                            onChange={(e) =>
                                                setData('name', e.target.value)
                                            }
                                            error={errors.name}
                                            placeholder="Asset Name"
                                            required
                                        />
                                    )}

                                    <FormInput
                                        id="price"
                                        label="Selling Price"
                                        type="number"
                                        value={data.price}
                                        onChange={(e) =>
                                            setData('price', e.target.value)
                                        }
                                        error={errors.price}
                                        placeholder="$ 0.00"
                                    />

                                    {isAsset && (
                                        <FormInput
                                            id="cost"
                                            label="Cost Price"
                                            type="number"
                                            value={data.cost}
                                            onChange={(e) =>
                                                setData('cost', e.target.value)
                                            }
                                            error={errors.cost}
                                            placeholder="$ 0.00"
                                            required
                                        />
                                    )}
                                </CardContent>
                            </Card>

                            {/* Card: Details */}
                            <Card className="border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
                                <CardHeader>
                                    <div className="flex items-center gap-3">
                                        <div className="rounded-lg bg-yellow-500/10 p-2">
                                            <FileText className="h-5 w-5 text-yellow-500" />
                                        </div>
                                        <CardTitle className="text-xl text-zinc-900 dark:text-zinc-50">
                                            Details
                                        </CardTitle>
                                    </div>
                                </CardHeader>
                                <CardContent className="space-y-6">
                                    <FormTextarea
                                        id="description"
                                        label="Description"
                                        value={data.description}
                                        onChange={(e) =>
                                            setData(
                                                'description',
                                                e.target.value,
                                            )
                                        }
                                        error={errors.description}
                                        placeholder="Item description..."
                                        rows={4}
                                    />

                                    <FormTextarea
                                        id="nb"
                                        label="Notes (NB)"
                                        value={data.description2}
                                        onChange={(e) =>
                                            setData(
                                                'description2',
                                                e.target.value,
                                            )
                                        }
                                        error={errors.description2}
                                        placeholder="Additional notes..."
                                        rows={3}
                                    />
                                </CardContent>
                            </Card>

                            {/* Card: Item Preview */}
                            <Card className="border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
                                <CardHeader>
                                    <div className="flex items-center gap-3">
                                        <div className="rounded-lg bg-green-500/10 p-2">
                                            <Box className="h-5 w-5 text-green-500" />
                                        </div>
                                        <div>
                                            <CardTitle className="text-xl text-zinc-900 dark:text-zinc-50">
                                                Item Preview
                                            </CardTitle>
                                            <CardDescription>
                                                Preview of updated SKU and Name
                                            </CardDescription>
                                        </div>
                                    </div>
                                </CardHeader>
                                <CardContent>
                                    <div className="space-y-2">
                                        {preview ? (
                                            <div className="space-y-1">
                                                {/* Table Header */}
                                                <div className="grid grid-cols-5 gap-4 px-3 py-2 text-xs font-bold tracking-wider text-zinc-500 uppercase">
                                                    <div className="col-span-2">SKU / Code</div>
                                                    <div className="col-span-3">Generated Name</div>
                                                </div>
                                                {/* Table Body */}
                                                <div className="grid grid-cols-5 items-center gap-4 rounded-lg border border-zinc-100 bg-zinc-50/50 p-3 dark:border-zinc-800 dark:bg-zinc-950/30">
                                                    <div className="col-span-2 font-mono text-sm font-semibold text-blue-600 dark:text-blue-400 break-all">
                                                        {preview.sku}
                                                    </div>
                                                    <div className="col-span-3 text-sm font-medium text-zinc-900 dark:text-zinc-100">
                                                        {preview.name}
                                                    </div>
                                                </div>
                                            </div>
                                        ) : (
                                            <div className="flex flex-col items-center justify-center py-8 text-center text-zinc-500">
                                                <Package className="mb-2 h-10 w-10 opacity-20" />
                                                <p className="text-sm">
                                                    Enter PCode to see preview.
                                                </p>
                                            </div>
                                        )}
                                    </div>
                                </CardContent>
                            </Card>
                        </div>


                        {/* Right Column: Attributes & Image */}
                        <div className="space-y-6">
                            {/* Card: Attributes */}
                            <Card className="border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
                                <CardHeader>
                                    <div className="flex items-center gap-3">
                                        <div className="rounded-lg bg-purple-500/10 p-2">
                                            <Tag className="h-5 w-5 text-purple-500" />
                                        </div>
                                        <CardTitle className="text-xl text-zinc-900 dark:text-zinc-50">
                                            Attributes
                                        </CardTitle>
                                    </div>
                                </CardHeader>
                                <CardContent className="space-y-6">
                                    {/* Warna: For Item and Asset */}
                                    <AttributeSelector
                                        label="Warna (Color)"
                                        value={data.tags.warna}
                                        options={warnaOptions}
                                        onSelect={(val) =>
                                            setData('tags', {
                                                ...data.tags,
                                                warna: val as string,
                                            })
                                        }
                                        modalTitle="Pilih Warna"
                                        searchPlaceholder="Cari warna..."
                                        required={isAsset}
                                        error={errors['tags.warna'] as string}
                                    />

                                    {/* Jahit: Item Only */}
                                    {!isAsset && (
                                        <AttributeSelector
                                            label="Jahit"
                                            value={data.tags.jahit}
                                            options={jahitOptions}
                                            onSelect={(val) =>
                                                setData('tags', {
                                                    ...data.tags,
                                                    jahit: val as string,
                                                })
                                            }
                                            modalTitle="Pilih Jahit"
                                            searchPlaceholder="Cari tipe jahit..."
                                            required
                                            error={
                                                errors['tags.jahit'] as string
                                            }
                                        />
                                    )}

                                    {/* Type: For Item and Asset */}
                                    <AttributeSelector
                                        label="Type"
                                        value={data.tags.types}
                                        options={typeOptions}
                                        onSelect={(val) =>
                                            setData('tags', {
                                                ...data.tags,
                                                types: val as string,
                                            })
                                        }
                                        modalTitle="Pilih Type"
                                        searchPlaceholder="Cari tipe..."
                                        required
                                        error={errors['tags.types'] as string}
                                    />

                                    {/* Size: For Item and Asset (Single for Edit) */}
                                    <AttributeSelector
                                        label="Size"
                                        value={data.tags.sizes[0] || ''}
                                        options={sizeOptions}
                                        onSelect={(val) =>
                                            setData('tags', {
                                                ...data.tags,
                                                sizes: [val as string],
                                            })
                                        }
                                        modalTitle="Pilih Size"
                                        searchPlaceholder="Cari size..."
                                        required
                                        error={errors['tags.sizes'] as string}
                                    />
                                </CardContent>
                            </Card>

                            {/* Card: Image */}
                            <Card className="border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
                                <CardHeader>
                                    <div className="flex items-center gap-3">
                                        <div className="rounded-lg bg-pink-500/10 p-2">
                                            <ImageIcon className="h-5 w-5 text-pink-500" />
                                        </div>
                                        <CardTitle className="text-xl text-zinc-900 dark:text-zinc-50">
                                            Image
                                        </CardTitle>
                                    </div>
                                </CardHeader>
                                <CardContent>
                                    <ImageUpload
                                        label=""
                                        onChange={(file) =>
                                            setData('image', file)
                                        }
                                        error={errors.image}
                                        initialPreview={
                                            item.image_url ||
                                            (item.image_path
                                                ? `/storage/${item.image_path}`
                                                : undefined)
                                        }
                                    />
                                </CardContent>
                            </Card>
                        </div>
                    </div>

                    {/* Footer Actions */}
                    <div className="flex justify-end gap-4 border-t border-zinc-200 pt-8 dark:border-zinc-800">
                        <Button
                            variant="ghost"
                            type="button"
                            className="text-zinc-500 hover:bg-zinc-100 hover:text-zinc-900 dark:text-zinc-400 dark:hover:bg-zinc-800 dark:hover:text-zinc-100"
                            onClick={() => window.history.back()}
                        >
                            Cancel
                        </Button>
                        <Button
                            type="submit"
                            loading={processing}
                            className="min-w-[150px] bg-blue-600 text-white hover:bg-blue-700"
                        >
                            Update {isAsset ? 'Asset' : 'Item'}
                        </Button>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}
