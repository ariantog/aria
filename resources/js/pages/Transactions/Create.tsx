import { Head, Link, useForm } from '@inertiajs/react';
import axios from 'axios';
import { Plus, Trash2, ArrowLeft, Loader2, Info, Scan, Camera } from 'lucide-react';
import { TriangleAlert } from 'lucide-react';
import { useState, useEffect } from 'react';
import { toast } from 'sonner';
import { AsyncCombobox } from '@/components/AsyncCombobox';
import FormAsyncCombobox from '@/components/Partial/Form/FormAsyncCombobox';
import FormInput from '@/components/Partial/Form/FormInput';
import FormTextarea from '@/components/Partial/Form/FormTextarea';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { cn } from '@/lib/utils';
import transactions from '@/routes/transactions';
import { Separator } from '@/components/ui/separator';
import AppLayout from '@/layouts/app-layout';
import AddItemDrawer from './AddItemDrawer';
import CameraScanner from './CameraScanner';

interface Props {
    type: string;
    config: {
        id: number;
        sender_label: string;
        receiver_label: string;
        sender_route: string;
        receiver_route: string;
        [key: string]: any;
    };
    ppn_rate: number;
    min_date?: string;
}

interface TransactionItem {
    item_id: string;
    code: string;
    name: string;
    quantity: number;
    warehouse_name: string;
    price: number;
    discount: number;
    subtotal: number;
    note?: string;
    warehouse_stock?: number; // Added
}

export default function Create({ type, config, ppn_rate, min_date }: Props) {
    const isBuy = type === 'buy';
    const [isAddItemDrawerOpen, setIsAddItemDrawerOpen] = useState(false);
    const [isCameraScannerOpen, setIsCameraScannerOpen] = useState(false);
    const [isScanning, setIsScanning] = useState(false);
    const [scannedItem, setScannedItem] = useState<any>(null);

    const { data, setData, post, processing, errors, transform } = useForm({
        date: min_date || new Date().toISOString().split('T')[0],
        due_date: '',
        type: type,
        sender_id: '',
        sender_type: config.sender_type,
        sender: null as any,
        receiver_id: '',
        receiver_type: config.receiver_type,
        receiver: null as any,
        invoice_number: '',
        note: '',
        items: [] as TransactionItem[],

        // Footer Totals
        discount_percent: 0,
        adjustment: 0,

        // Calculated/Read-only fields
        total_quantity: 0,
        total_before_discount: 0,
        total_before_ppn: 0,
        ppn_amount: 0,
        grand_total: 0,
    });

    // Transform data before submission
    transform((data) => ({
        ...data,
        // No transformation needed as item discounts are now nominal
    }));

    // --- Calculations ---
    useEffect(() => {
        const totalQty = data.items.reduce(
            (s, i) => s + Number(i.quantity || 0),
            0,
        );
        const totalLineItems = data.items.reduce(
            (s, i) => s + Number(i.subtotal || 0),
            0,
        );

        const discountAmount =
            totalLineItems * (Number(data.discount_percent || 0) / 100);
        const afterDiscount = totalLineItems - discountAmount;
        const withAdjustment = afterDiscount + Number(data.adjustment || 0);

        // PPN Logic: Check selected contact
        const contact = type === 'buy' ? data.sender : data.receiver;
        const isPpn = contact?.ppn || false;

        const ppn = isPpn ? withAdjustment * (ppn_rate / 100) : 0;
        const finalTotal = withAdjustment + ppn;

        setData((prev) => ({
            ...prev,
            total_quantity: totalQty,
            total_before_discount: totalLineItems,
            total_before_ppn: withAdjustment,
            ppn_amount: ppn,
            grand_total: finalTotal,
        }));
    }, [
        data.items,
        data.discount_percent,
        data.adjustment,
        data.sender,
        data.receiver,
    ]);

    // Keyboard Shortcut Ctrl+I and Barcode Scanner (Global)
    useEffect(() => {
        let buffer = '';
        let lastKeyTime = Date.now();

        const handleKeyDown = (e: KeyboardEvent) => {
            // Shortcut Ctrl+I
            if (
                (e.ctrlKey || e.metaKey) &&
                (e.key.toLowerCase() === 'i' || e.code === 'KeyI')
            ) {
                e.preventDefault();
                setIsAddItemDrawerOpen(true);
                return;
            }

            // Global Barcode Listener
            const target = e.target as HTMLElement;
            // Ignore if typing in an input
            if (target.tagName === 'INPUT' || target.tagName === 'TEXTAREA' || target.tagName === 'SELECT') {
                return;
            }

            const currentTime = Date.now();
            if (currentTime - lastKeyTime > 50) {
                buffer = '';
            }
            lastKeyTime = currentTime;

            if (e.key === 'Enter') {
                if (buffer.length > 2) {
                    e.preventDefault();
                    handleBarcodeScan(buffer);
                }
                buffer = '';
            } else if (e.key.length === 1) {
                buffer += e.key;
            }
        };

        window.addEventListener('keydown', handleKeyDown);
        return () => window.removeEventListener('keydown', handleKeyDown);
    }, [data.sender_id, data.receiver_id]);

    const handleBarcodeScan = async (code: string) => {
        if (isScanning) return;
        setIsScanning(true);

        try {
            const response = await axios.get('/items', {
                params: {
                    json: true,
                    id: code, // Search specifically by ID as requested
                    type: '1,2'
                }
            });

            const items = response.data.data || response.data;
            if (items && items.length > 0) {
                const item = items[0];
                setScannedItem(item);
                setIsAddItemDrawerOpen(true);
            } else {
                toast.error(`Item with ID "${code}" not found.`);
            }
        } catch (err) {
            console.error('Barcode scan error:', err);
            toast.error('Failed to lookup item code.');
        } finally {
            setIsScanning(false);
        }
    };

    const addItem = (itemData: any) => {
        if (!itemData) return;

        const existingIndex = data.items.findIndex(
            (i) => String(i.item_id) === String(itemData.id),
        );

        if (existingIndex > -1) {
            const newItems = [...data.items];
            const item = { ...newItems[existingIndex] };
            item.quantity += Number(itemData.quantity || 1);
            item.subtotal = item.quantity * item.price - item.discount;
            newItems[existingIndex] = item;
            setData('items', newItems);
            toast.success(`Updated ${item.name} quantity to ${item.quantity}`);
        } else {
            setData('items', [
                ...data.items,
                {
                    item_id: String(itemData.id),
                    code: itemData.code,
                    name: itemData.name,
                    quantity: Number(itemData.quantity || 1),
                    warehouse_name: itemData.warehouse_name || 'Central',
                    warehouse_stock: itemData.warehouse_stock,
                    price: Number(itemData.price),
                    discount: Number(itemData.discount || 0),
                    subtotal: Number(itemData.subtotal),
                    note: itemData.note,
                },
            ]);
            toast.success(`${itemData.name} added to list`);
        }
    };

    const removeItem = (index: number) => {
        const newItems = [...data.items];
        newItems.splice(index, 1);
        setData('items', newItems);
    };

    const updateItem = (
        index: number,
        field: keyof TransactionItem,
        value: any,
    ) => {
        const newItems = [...data.items];
        const item = { ...newItems[index], [field]: value };

        const qty = Number(item.quantity) || 0;
        const price = Number(item.price) || 0;

        // Recalculate subtotal based on simple formula or if discount logic changes
        // Current simple: (qty * price) - (qty * price * discount / 100) if discount is percent?
        // Wait, in modal we treated discount as percent. Here previous logic was different?
        // Previous: item.subtotal = (qty * price) - disc; (disc was amount)
        // Let's stick to discount as percent to match modal.

        const discNominal = Number(item.discount) || 0;
        const subtotal = qty * price - discNominal;
        item.subtotal = subtotal;

        newItems[index] = item;
        setData('items', newItems);
    };

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        post(transactions.store.url(), {
            onError: (errors) => {
                console.error('Submission errors:', errors);
            },
        });
    };

    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Transactions', href: '/transactions' },
                {
                    title: `${type === 'buy' ? 'Buy' : 'Sell'} Transaction`,
                    href: '#',
                },
            ]}
        >
            <Head
                title={`New ${type.charAt(0).toUpperCase() + type.slice(1)} Transaction`}
            />

            <form
                onSubmit={submit}
                className="mx-auto max-w-[1600px] p-4 sm:p-6 lg:p-8"
            >
                {/* Header */}
                <div className="mb-8 flex flex-col items-start justify-between gap-4 sm:flex-row sm:items-center">
                    <div className="flex items-center gap-4">
                        <Link href={transactions.index.url()}>
                            <Button
                                variant="ghost"
                                size="icon"
                                className="-ml-2"
                            >
                                <ArrowLeft className="h-4 w-4" />
                            </Button>
                        </Link>
                        <div>
                            <h2 className="text-2xl font-bold tracking-tight text-zinc-900 dark:text-zinc-50">
                                New{' '}
                                {type.charAt(0).toUpperCase() + type.slice(1)}{' '}
                                Transaction
                            </h2>
                            <p className="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                                Create a new {type} transaction and add items.
                            </p>
                        </div>
                    </div>
                    <div className="flex items-center gap-2">
                        <Button
                            variant="outline"
                            type="button"
                            onClick={() => window.history.back()}
                        >
                            Discard
                        </Button>
                        <Button
                            type="submit"
                            disabled={processing}
                            className="min-w-[140px] bg-blue-700 text-white hover:bg-blue-800"
                        >
                            {processing ? (
                                <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                            ) : (
                                <span className="mr-2">Save Transaction</span>
                            )}
                        </Button>
                    </div>
                </div>

                <div className="grid grid-cols-1 gap-6 xl:grid-cols-3">
                    {/* Left Column (Main Form) */}
                    <div className="space-y-6 xl:col-span-2">
                        {/* Basic Info */}
                        <Card>
                            <CardHeader className="pb-3">
                                <CardTitle className="flex items-center gap-2 text-base font-semibold">
                                    <Info className="h-4 w-4 text-blue-600" />
                                    Basic Info
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="grid grid-cols-1 gap-6 md:grid-cols-2">
                                <div>
                                    <FormInput
                                        id="date"
                                        label="Date"
                                        type="date"
                                        min={min_date}
                                        value={data.date}
                                        onChange={(e) =>
                                            setData('date', e.target.value)
                                        }
                                        error={errors.date}
                                        required
                                    />
                                </div>
                                <div>
                                    {type !== 'move' && (
                                        <FormInput
                                            id="due_date"
                                            label="Due Date"
                                            type="date"
                                            value={data.due_date}
                                            onChange={(e) =>
                                                setData(
                                                    'due_date',
                                                    e.target.value,
                                                )
                                            }
                                        />
                                    )}
                                </div>
                                <div>
                                    <FormAsyncCombobox
                                        label={`Sender (${config.sender_label})`}
                                        endpoint={config.sender_route}
                                        value={data.sender}
                                        onChange={(val) =>
                                            setData((data) => ({
                                                ...data,
                                                sender_id: String(
                                                    val?.id || '',
                                                ),
                                                sender: val,
                                            }))
                                        }
                                        placeholder={`Select ${config.sender_label}...`}
                                        error={errors.sender_id}
                                    />
                                </div>
                                <div>
                                    <FormAsyncCombobox
                                        label={`Receiver (${config.receiver_label})`}
                                        endpoint={config.receiver_route}
                                        value={data.receiver}
                                        onChange={(val) =>
                                            setData((data) => ({
                                                ...data,
                                                receiver_id: String(
                                                    val?.id || '',
                                                ),
                                                receiver: val,
                                            }))
                                        }
                                        placeholder={`Select ${config.receiver_label}...`}
                                        error={errors.receiver_id}
                                    />
                                </div>
                            </CardContent>
                        </Card>

                        {/* Transaction Details */}
                        <Card>
                            <CardHeader className="pb-3">
                                <CardTitle className="flex items-center gap-2 text-base font-semibold">
                                    <div className="flex h-4 w-4 items-center justify-center rounded bg-blue-100 dark:bg-blue-900/30">
                                        <span className="text-xs font-bold text-blue-600">
                                            ≡
                                        </span>
                                    </div>
                                    Transaction Details
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="grid grid-cols-1 gap-6 md:grid-cols-2">
                                <div>
                                    <FormInput
                                        id="invoice_number"
                                        label="Invoice Number"
                                        value={data.invoice_number}
                                        onChange={(e) =>
                                            setData(
                                                'invoice_number',
                                                e.target.value,
                                            )
                                        }
                                        placeholder="INV-202X-XXX"
                                    />
                                </div>
                                <div>
                                    <FormTextarea
                                        id="note"
                                        label="Note"
                                        value={data.note}
                                        onChange={(e) =>
                                            setData('note', e.target.value)
                                        }
                                        placeholder="Add optional notes..."
                                        rows={1}
                                    />
                                </div>
                            </CardContent>
                        </Card>

                        {/* Line Items */}
                        <Card>
                            <CardHeader className="flex flex-row items-center justify-between pb-3">
                                <CardTitle className="flex items-center gap-2 text-base font-semibold">
                                    <div className="flex h-4 w-4 items-center justify-center rounded bg-blue-100 dark:bg-blue-900/30">
                                        <span className="text-xs font-bold text-blue-600">
                                            🛒
                                        </span>
                                    </div>
                                    Line Items
                                </CardTitle>
                                <div className="flex gap-2">
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        type="button"
                                        onClick={() => setIsCameraScannerOpen(true)}
                                        className="h-8 gap-1.5 text-xs font-bold"
                                    >
                                        <Camera className="h-3.5 w-3.5" />
                                        Camera Scan
                                    </Button>
                                </div>
                            </CardHeader>
                            <CardContent>
                                {errors.items && (
                                    <Alert
                                        variant="destructive"
                                        className="mb-4"
                                    >
                                        <TriangleAlert className="h-4 w-4" />
                                        <AlertTitle>Error</AlertTitle>
                                        <AlertDescription>
                                            {errors.items}
                                        </AlertDescription>
                                    </Alert>
                                )}

                                {/* Items Table */}
                                <div className="mb-4 rounded-md border">
                                    <div className="grid grid-cols-12 gap-4 border-b bg-zinc-50 p-3 text-xs font-medium text-zinc-500 dark:bg-zinc-900/50">
                                        <div
                                            className={
                                                type === 'move'
                                                    ? 'col-span-6'
                                                    : 'col-span-5'
                                            }
                                        >
                                            ITEM
                                        </div>
                                        <div className="col-span-2 text-center">
                                            QTY / WH STOCK
                                        </div>
                                        <div className="col-span-2 text-right">
                                            PRICE
                                        </div>
                                        {type !== 'move' && (
                                            <div className="col-span-1 text-right">
                                                DISC
                                            </div>
                                        )}
                                        <div className="col-span-2 text-right">
                                            SUBTOTAL
                                        </div>
                                    </div>

                                    {/* Rows */}
                                    <div className="divide-y">
                                        {data.items.length === 0 && (
                                            <div className="p-8 text-center text-sm text-zinc-500">
                                                No items added. Scan a barcode or press{' '}
                                                <span className="rounded bg-zinc-100 px-1 font-mono">
                                                    Ctrl+I
                                                </span>{' '}
                                                to add.
                                            </div>
                                        )}
                                        {data.items.map((item, index) => (
                                            <div
                                                key={index}
                                                className="group relative grid grid-cols-12 items-center gap-4 p-3 text-sm transition-colors hover:bg-zinc-50/50"
                                            >
                                                <div
                                                    className={
                                                        type === 'move'
                                                            ? 'col-span-6'
                                                            : 'col-span-5'
                                                    }
                                                >
                                                    <div className="font-mono text-xs text-zinc-500">
                                                        {item.code}
                                                    </div>
                                                    <div className="font-medium">
                                                        {item.name}
                                                    </div>
                                                    {item.note && (
                                                        <div
                                                            className="mt-0.5 max-w-[300px] truncate text-xs text-zinc-400"
                                                            title={item.note}
                                                        >
                                                            📝 {item.note}
                                                        </div>
                                                    )}
                                                </div>
                                                <div className="col-span-2 text-center">
                                                    <span className="font-bold">
                                                        {item.quantity}
                                                    </span>
                                                    <span className="mx-1 text-zinc-400">
                                                        /
                                                    </span>
                                                    <span className="text-zinc-500">
                                                        {item.warehouse_stock}
                                                    </span>
                                                </div>
                                                <div className="col-span-2 text-right">
                                                    {item.price.toLocaleString()}
                                                </div>
                                                {type !== 'move' && (
                                                    <div className="col-span-1 text-right">
                                                        {item.discount > 0 ? (
                                                            <span className="text-red-500">
                                                                -
                                                                {item.discount.toLocaleString()}
                                                            </span>
                                                        ) : (
                                                            '-'
                                                        )}
                                                    </div>
                                                )}
                                                <div className="col-span-2 flex items-center justify-end gap-2">
                                                    <span className="font-semibold">
                                                        {item.subtotal.toLocaleString()}
                                                    </span>
                                                    <button
                                                        type="button"
                                                        onClick={() =>
                                                            removeItem(index)
                                                        }
                                                        className="ml-2 text-zinc-400 opacity-0 transition-opacity group-hover:opacity-100 hover:text-red-500"
                                                    >
                                                        <Trash2 className="h-4 w-4" />
                                                    </button>
                                                </div>
                                            </div>
                                        ))}
                                    </div>
                                </div>

                                {/* Add New Line Item Button */}
                                <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                    <button
                                        type="button"
                                        onClick={() => setIsAddItemDrawerOpen(true)}
                                        className="flex w-full items-center justify-center gap-2 rounded-lg border-2 border-dashed py-3 text-sm text-zinc-400 transition-all hover:border-blue-200 hover:bg-blue-50/30 hover:text-blue-600"
                                    >
                                        <Plus className="h-4 w-4" /> Add Item (Ctrl+I)
                                    </button>
                                    <button
                                        type="button"
                                        onClick={() => setIsCameraScannerOpen(true)}
                                        className="flex w-full items-center justify-center gap-2 rounded-lg border-2 border-dashed py-3 text-sm text-zinc-400 transition-all hover:border-zinc-200 hover:bg-zinc-50/50 hover:text-zinc-600 sm:hidden"
                                    >
                                        <Scan className="h-4 w-4" /> Scan Barcode
                                    </button>
                                </div>
                            </CardContent>
                        </Card>
                    </div>

                    {/* Right Column (Totals) */}
                    <div className="space-y-6">
                        {/* Totals Summary */}
                        <div className="space-y-6 rounded-xl bg-[#1e293b] p-6 text-white shadow-lg">
                            <div className="mb-2 flex items-center gap-3">
                                <div className="rounded bg-white/10 p-1.5">
                                    <span className="text-xl">🧾</span>
                                </div>
                                <div>
                                    <h3 className="text-lg leading-tight font-bold">
                                        Totals
                                    </h3>
                                    <div className="text-sm text-blue-200">
                                        Summary
                                    </div>
                                </div>
                            </div>

                            <Separator className="bg-white/10" />

                            <div className="space-y-3 text-sm">
                                <div className="flex justify-between">
                                    <span className="text-blue-100">
                                        TOTAL QTY
                                    </span>
                                    <span className="font-semibold">
                                        {data.total_quantity.toLocaleString()}
                                    </span>
                                </div>
                                {type !== 'move' && (
                                    <>
                                        <div className="flex justify-between">
                                            <span className="text-blue-100">
                                                BEFORE DISC
                                            </span>
                                            <span className="font-semibold">
                                                {data.total_before_discount.toLocaleString()}
                                            </span>
                                        </div>
                                        <div className="flex items-center justify-between">
                                            <span className="text-blue-100">
                                                DISCOUNT (%)
                                            </span>
                                            <Input
                                                type="number"
                                                className="h-8 w-16 border-white/20 bg-white/10 text-right text-white"
                                                value={data.discount_percent}
                                                onChange={(e) =>
                                                    setData(
                                                        'discount_percent',
                                                        Number(e.target.value),
                                                    )
                                                }
                                            />
                                        </div>
                                        <div className="flex justify-between">
                                            <span className="text-blue-100">
                                                TOTAL
                                            </span>
                                            <span className="font-semibold">
                                                {data.total_before_discount.toLocaleString()}
                                            </span>
                                        </div>
                                        <div className="flex justify-between">
                                            <span className="text-blue-100">
                                                TOTAL BEFORE PPN
                                            </span>
                                            <span className="font-semibold">
                                                {data.total_before_ppn.toLocaleString()}
                                            </span>
                                        </div>
                                        <div className="flex justify-between">
                                            <span className="text-blue-100">
                                                PPN ({ppn_rate}%)
                                            </span>
                                            <span className="font-semibold">
                                                {data.ppn_amount.toLocaleString()}
                                            </span>
                                        </div>
                                        <div className="flex items-center justify-between">
                                            <span className="text-blue-100">
                                                ADJUSTMENT
                                            </span>
                                            <Input
                                                type="number"
                                                className="h-8 w-24 border-white/20 bg-white/10 text-right text-white"
                                                value={data.adjustment}
                                                onChange={(e) =>
                                                    setData(
                                                        'adjustment',
                                                        Number(e.target.value),
                                                    )
                                                }
                                            />
                                        </div>
                                    </>
                                )}
                            </div>

                            <Separator className="bg-white/10" />

                            <div className="space-y-1">
                                <div className="text-xs tracking-wider text-blue-200 uppercase">
                                    {type === 'move' ? 'Total' : 'Grand Total'}
                                </div>
                                <div className="text-3xl font-bold">
                                    <span className="mr-1 text-lg text-blue-200">
                                        IDR
                                    </span>
                                    {data.grand_total.toLocaleString()}
                                </div>
                            </div>

                            <Button
                                onClick={submit}
                                type="button"
                                className="mt-4 w-full bg-white font-bold text-blue-900 hover:bg-blue-50"
                                size="lg"
                            >
                                SUBMIT NEW {type.toUpperCase()}
                            </Button>

                            <p className="text-center text-xs leading-relaxed text-blue-200 opacity-80">
                                By clicking submit, you agree to post this
                                transaction to the general ledger.
                            </p>
                        </div>
                    </div>
                </div>

                <AddItemDrawer
                    isOpen={isAddItemDrawerOpen}
                    onClose={() => {
                        setIsAddItemDrawerOpen(false);
                        setScannedItem(null);
                    }}
                    onAdd={addItem}
                    isBuy={isBuy}
                    senderId={data.sender_id}
                    receiverId={data.receiver_id}
                    priceSource={config.price_source}
                    checkStock={
                        data.sender?.type
                            ? String(data.sender.type) === '2'
                            : String(config.sender_type) === '2'
                    }
                    type={type}
                    initialItem={scannedItem}
                />

                <CameraScanner 
                    isOpen={isCameraScannerOpen}
                    onClose={() => setIsCameraScannerOpen(false)}
                    onScan={(code) => {
                        handleBarcodeScan(code);
                        setIsCameraScannerOpen(false);
                    }}
                />
            </form>
        </AppLayout>
    );
}
