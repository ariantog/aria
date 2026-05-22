import { Head, useForm, Link } from '@inertiajs/react';
import {
    Package,
    DollarSign,
    FileText,
    Tag,
    Image as ImageIcon,
    Info,
    Box,
} from 'lucide-react';
import FormInput from '@/components/Partial/Form/FormInput';
import FormTextarea from '@/components/Partial/Form/FormTextarea';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardHeader,
    CardTitle,
    CardDescription,
} from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import itemRoutes from '@/routes/items';
import type { BreadcrumbItem } from '@/types';
import AttributeSelector from './Partials/AttributeSelector';
import ImageUpload from './Partials/ImageUpload';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Items', href: '/items' },
    { title: 'Create New', href: '#' },
];

interface Props {
    brands: { value: string | number; label: string }[];
    itemType: number; // 1: Item, 2: Asset Lancar
    // Explicit tag collections
    jahitTags: any[];
    typeTags: any[];
    sizeTags: any[];
    warnaTags: any[];
}

export default function ItemsCreate({
    brands,
    itemType,
    jahitTags,
    typeTags,
    sizeTags,
    warnaTags,
}: Props) {
    const isAsset = itemType === 2;
    const pageTitle = isAsset ? 'Create New Asset' : 'Create New Item';

    const { data, setData, post, processing, errors } = useForm({
        pcode: '',
        name: '',
        alias: '',
        brand: '', // Not used in UI yet but in logic
        type: itemType.toString(),
        price: '',
        cost: '',
        description: '',
        description2: '',
        image: null as File | null,
        tags: {
            types: '', // Single (Radio)
            sizes: [] as string[], // Multi (Checkbox)
            warna: (isAsset ? [] : '') as string | string[], // Multi if Asset
            jahit: '', // Single (Radio)
        },
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        post(itemRoutes.store.url(), {
            forceFormData: true,
        });
    };

    // Transform tags for AttributeSelector
    const jahitOptions = jahitTags.map((t) => ({ value: t.id, label: t.name }));
    const typeOptions = typeTags.map((t) => ({ value: t.id, label: t.name }));
    const sizeOptions = sizeTags.map((t) => ({ value: t.id, label: t.name }));
    const warnaOptions = warnaTags.map((t) => ({ value: t.id, label: t.name }));

    // Generate Preview Items
    const getPreviewItems = () => {
        const pcode = data.pcode.toUpperCase().trim();
        const alias = data.alias.toUpperCase().trim() || '???';
        if (!pcode) return [];

        const selectedSizes = sizeTags.filter((t) =>
            data.tags.sizes.includes(t.id.toString()),
        );

        let selectedWarnas: any[] = [];
        if (isAsset) {
            if (Array.isArray(data.tags.warna)) {
                selectedWarnas = warnaTags.filter((t) =>
                    (data.tags.warna as string[]).includes(t.id.toString()),
                );
            }
        } else {
            // For ITEM, try to get the single warna code
            const warnaId = data.tags.warna as string;
            const warna = warnaTags.find((t) => t.id.toString() === warnaId);
            selectedWarnas = [warna || null];
        }

        const items: { sku: string; name: string }[] = [];

        selectedSizes.forEach((size) => {
            const sizeCode = size?.code || '???';
            selectedWarnas.forEach((warna) => {
                const warnaCode = warna?.code || '???';
                items.push({
                    sku: `${pcode}-${warnaCode}-${sizeCode}`.toUpperCase(),
                    name: `${alias} - ${warnaCode} - ${sizeCode}`.toUpperCase(),
                });
            });
        });

        return items;
    };

    const previewItems = getPreviewItems();

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
                        Add a new {isAsset ? 'asset' : 'item'} to the inventory
                        system with STRING-NUMBER format (e.g. BOXING-01).
                    </p>
                </div>

                <form onSubmit={submit} className="space-y-8" noValidate>
                    {/* Generic Error Message */}
                    {(errors as any).message && (
                        <div className="mb-6 rounded-lg border border-red-500/20 bg-red-500/10 p-4">
                            <div className="flex items-center gap-2 font-medium text-red-500">
                                <Info className="h-5 w-5" />
                                <div className="text-zinc-900 dark:text-white">
                                    Failed to create{' '}
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
                                            // error={errors.cost}
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

                            {/* Card: Item Summary Preview */}
                            <Card className="border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
                                <CardHeader>
                                    <div className="flex items-center gap-3">
                                        <div className="rounded-lg bg-green-500/10 p-2">
                                            <Box className="h-5 w-5 text-green-500" />
                                        </div>
                                        <div>
                                            <CardTitle className="text-xl text-zinc-900 dark:text-zinc-50">
                                                Item Summary Preview
                                            </CardTitle>
                                            <CardDescription>
                                                Summary of items to be generated
                                            </CardDescription>
                                        </div>
                                    </div>
                                </CardHeader>
                                <CardContent>
                                    <div className="custom-scrollbar max-h-[400px] space-y-2 overflow-y-auto pr-2">
                                        {previewItems.length > 0 ? (
                                            <div className="space-y-1">
                                                {/* Table Header */}
                                                <div className="grid grid-cols-5 gap-4 px-3 py-2 text-xs font-bold tracking-wider text-zinc-500 uppercase">
                                                    <div className="col-span-2">SKU / Code</div>
                                                    <div className="col-span-3">Generated Name</div>
                                                </div>
                                                {/* Table Body */}
                                                <div className="space-y-1">
                                                    {previewItems.map((item, idx) => (
                                                        <div
                                                            key={idx}
                                                            className="grid grid-cols-5 items-center gap-4 rounded-lg border border-zinc-100 bg-zinc-50/50 p-3 dark:border-zinc-800 dark:bg-zinc-950/30"
                                                        >
                                                            <div className="col-span-2 font-mono text-sm font-semibold text-blue-600 dark:text-blue-400 break-all">
                                                                {item.sku}
                                                            </div>
                                                            <div className="col-span-3 text-sm font-medium text-zinc-900 dark:text-zinc-100">
                                                                {item.name}
                                                            </div>
                                                        </div>
                                                    ))}
                                                </div>
                                            </div>
                                        ) : (
                                            <div className="flex flex-col items-center justify-center py-8 text-center text-zinc-500">
                                                <Package className="mb-2 h-10 w-10 opacity-20" />
                                                <p className="text-sm">
                                                    Enter PCode and select Type/Size
                                                    to see preview.
                                                </p>
                                            </div>
                                        )}
                                    </div>
                                    {previewItems.length > 0 && (
                                        <div className="mt-4 border-t border-zinc-100 pt-4 dark:border-zinc-800">
                                            <p className="text-xs text-zinc-500">
                                                Total Items to Create:{' '}
                                                <span className="font-bold text-zinc-900 dark:text-zinc-100">
                                                    {previewItems.length}
                                                </span>
                                            </p>
                                        </div>
                                    )}
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
                                                warna: val,
                                            })
                                        }
                                        modalTitle="Pilih Warna"
                                        searchPlaceholder="Cari warna..."
                                        required={isAsset}
                                        multiple={isAsset}
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

                                    {/* Size: For Item and Asset (Multi for Create) */}
                                    <AttributeSelector
                                        label="Size"
                                        value={data.tags.sizes}
                                        options={sizeOptions}
                                        onSelect={(val) =>
                                            setData('tags', {
                                                ...data.tags,
                                                sizes: val as string[],
                                            })
                                        }
                                        modalTitle="Pilih Size"
                                        searchPlaceholder="Cari size..."
                                        multiple
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
                            Create {isAsset ? 'Asset' : 'Item'}
                        </Button>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}
