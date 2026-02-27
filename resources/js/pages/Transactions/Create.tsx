import { useState, useEffect } from 'react';
import AppLayout from '@/layouts/app-layout';
import { Head, Link, useForm } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Plus, Trash2, ArrowLeft, Loader2, Info } from 'lucide-react';
import { cn } from '@/lib/utils';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { TriangleAlert } from 'lucide-react';
import transactions from '@/routes/transactions';
import { AsyncCombobox } from '@/components/AsyncCombobox';
import { Separator } from '@/components/ui/separator';
import AddItemDrawer from './AddItemDrawer';
import FormInput from '@/components/Partial/Form/FormInput';
import FormTextarea from '@/components/Partial/Form/FormTextarea';
import FormAsyncCombobox from '@/components/Partial/Form/FormAsyncCombobox';

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

export default function Create({ type, config, ppn_rate }: Props) {
    const isBuy = type === 'buy';
    const [isAddItemDrawerOpen, setIsAddItemDrawerOpen] = useState(false);

    const { data, setData, post, processing, errors, transform } = useForm({
        date: new Date().toISOString().split('T')[0],
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
        grand_total: 0
    });

    // Transform data before submission
    transform((data) => ({
        ...data,
        // No transformation needed as item discounts are now nominal
    }));

    // --- Calculations ---
    useEffect(() => {
        const totalQty = data.items.reduce((s, i) => s + Number(i.quantity || 0), 0);
        const totalLineItems = data.items.reduce((s, i) => s + Number(i.subtotal || 0), 0);

        const discountAmount = totalLineItems * (Number(data.discount_percent || 0) / 100);
        const afterDiscount = totalLineItems - discountAmount;
        const withAdjustment = afterDiscount + Number(data.adjustment || 0);

        // PPN Logic: Check selected contact
        const contact = type === 'buy' ? data.sender : data.receiver;
        const isPpn = contact?.ppn || false;

        const ppn = isPpn ? (withAdjustment * (ppn_rate / 100)) : 0;
        const finalTotal = withAdjustment + ppn;

        setData(prev => ({
            ...prev,
            total_quantity: totalQty,
            total_before_discount: totalLineItems,
            total_before_ppn: withAdjustment,
            ppn_amount: ppn,
            grand_total: finalTotal
        }));
    }, [data.items, data.discount_percent, data.adjustment, data.sender, data.receiver]);

    // Keyboard Shortcut Ctrl+I
    useEffect(() => {
        const handleKeyDown = (e: KeyboardEvent) => {
            if ((e.ctrlKey || e.metaKey) && (e.key.toLowerCase() === 'i' || e.code === 'KeyI')) {
                e.preventDefault();
                setIsAddItemDrawerOpen(true);
            }
        };

        window.addEventListener('keydown', handleKeyDown);
        return () => window.removeEventListener('keydown', handleKeyDown);
    }, []);

    const addItem = (itemData: any) => {
        if (!itemData) return;

        setData('items', [
            ...data.items,
            {
                item_id: String(itemData.id),
                code: itemData.code,
                name: itemData.name,
                quantity: itemData.quantity,
                warehouse_name: itemData.warehouse_name || 'Central',
                warehouse_stock: itemData.warehouse_stock,
                price: itemData.price,
                discount: itemData.discount,
                subtotal: itemData.subtotal,
                note: itemData.note
            }
        ]);
        // Modal closes in modal component, but state managed here? 
        // Logic in modal calls onAdd then onClose ideally.
    };

    const removeItem = (index: number) => {
        const newItems = [...data.items];
        newItems.splice(index, 1);
        setData('items', newItems);
    };

    const updateItem = (index: number, field: keyof TransactionItem, value: any) => {
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
        const subtotal = (qty * price) - discNominal;
        item.subtotal = subtotal;

        newItems[index] = item;
        setData('items', newItems);
    };

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        post(transactions.store.url(), {
            onError: (errors) => {
                console.error("Submission errors:", errors);
            }
        });
    };

    return (
        <AppLayout breadcrumbs={[
            { title: 'Transactions', href: '/transactions' },
            { title: `${type === 'buy' ? 'Buy' : 'Sell'} Transaction`, href: '#' },
        ]}>
            <Head title={`New ${type.charAt(0).toUpperCase() + type.slice(1)} Transaction`} />

            <form onSubmit={submit} className="p-4 sm:p-6 lg:p-8 max-w-[1600px] mx-auto">

                {/* Header */}
                <div className="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-8 gap-4">
                    <div className="flex items-center gap-4">
                        <Link href={transactions.index.url()}>
                            <Button variant="ghost" size="icon" className="-ml-2">
                                <ArrowLeft className="h-4 w-4" />
                            </Button>
                        </Link>
                        <div>
                            <h2 className="text-2xl font-bold tracking-tight text-zinc-900 dark:text-zinc-50">New {type.charAt(0).toUpperCase() + type.slice(1)} Transaction</h2>
                            <p className="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                                Create a new {type} transaction and add items.
                            </p>
                        </div>
                    </div>
                    <div className="flex items-center gap-2">
                        <Button variant="outline" type="button" onClick={() => window.history.back()}>
                            Discard
                        </Button>
                        <Button type="submit" disabled={processing} className="bg-blue-700 hover:bg-blue-800 text-white min-w-[140px]">
                            {processing ? (
                                <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                            ) : (
                                <span className="mr-2">Save Transaction</span>
                            )}
                        </Button>
                    </div>
                </div>

                <div className="grid grid-cols-1 xl:grid-cols-3 gap-6">

                    {/* Left Column (Main Form) */}
                    <div className="xl:col-span-2 space-y-6">

                        {/* Basic Info */}
                        <Card>
                            <CardHeader className="pb-3">
                                <CardTitle className="text-base font-semibold flex items-center gap-2">
                                    <Info className="h-4 w-4 text-blue-600" />
                                    Basic Info
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <FormInput
                                        id="date"
                                        label="Date"
                                        type="date"
                                        value={data.date}
                                        onChange={e => setData('date', e.target.value)}
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
                                            onChange={e => setData('due_date', e.target.value)}
                                        />
                                    )}
                                </div>
                                <div>
                                    <FormAsyncCombobox
                                        label={`Sender (${config.sender_label})`}
                                        endpoint={config.sender_route}
                                        value={data.sender}
                                        onChange={(val) => setData(data => ({ ...data, sender_id: String(val?.id || ''), sender: val }))}
                                        placeholder={`Select ${config.sender_label}...`}
                                        error={errors.sender_id}
                                    />
                                </div>
                                <div>
                                    <FormAsyncCombobox
                                        label={`Receiver (${config.receiver_label})`}
                                        endpoint={config.receiver_route}
                                        value={data.receiver}
                                        onChange={(val) => setData(data => ({ ...data, receiver_id: String(val?.id || ''), receiver: val }))}
                                        placeholder={`Select ${config.receiver_label}...`}
                                        error={errors.receiver_id}
                                    />
                                </div>
                            </CardContent>
                        </Card>

                        {/* Transaction Details */}
                        <Card>
                            <CardHeader className="pb-3">
                                <CardTitle className="text-base font-semibold flex items-center gap-2">
                                    <div className="h-4 w-4 rounded bg-blue-100 dark:bg-blue-900/30 flex items-center justify-center">
                                        <span className="text-xs font-bold text-blue-600">≡</span>
                                    </div>
                                    Transaction Details
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <FormInput
                                        id="invoice_number"
                                        label="Invoice Number"
                                        value={data.invoice_number}
                                        onChange={e => setData('invoice_number', e.target.value)}
                                        placeholder="INV-202X-XXX"
                                    />
                                </div>
                                <div>
                                    <FormTextarea
                                        id="note"
                                        label="Note"
                                        value={data.note}
                                        onChange={e => setData('note', e.target.value)}
                                        placeholder="Add optional notes..."
                                        rows={1}
                                    />
                                </div>
                            </CardContent>
                        </Card>

                        {/* Line Items */}
                        <Card>
                            <CardHeader className="pb-3 flex flex-row items-center justify-between">
                                <CardTitle className="text-base font-semibold flex items-center gap-2">
                                    <div className="h-4 w-4 rounded bg-blue-100 dark:bg-blue-900/30 flex items-center justify-center">
                                        <span className="text-xs font-bold text-blue-600">🛒</span>
                                    </div>
                                    Line Items
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                {errors.items && (
                                    <Alert variant="destructive" className="mb-4">
                                        <TriangleAlert className="h-4 w-4" />
                                        <AlertTitle>Error</AlertTitle>
                                        <AlertDescription>
                                            {errors.items}
                                        </AlertDescription>
                                    </Alert>
                                )}

                                {/* Items Table */}
                                <div className="rounded-md border mb-4">
                                    <div className="grid grid-cols-12 gap-4 p-3 bg-zinc-50 dark:bg-zinc-900/50 text-xs font-medium text-zinc-500 border-b">
                                        <div className={type === 'move' ? "col-span-6" : "col-span-5"}>ITEM</div>
                                        <div className="col-span-2 text-center">QTY / WH STOCK</div>
                                        <div className="col-span-2 text-right">PRICE</div>
                                        {type !== 'move' && <div className="col-span-1 text-right">DISC</div>}
                                        <div className="col-span-2 text-right">SUBTOTAL</div>
                                    </div>

                                    {/* Rows */}
                                    <div className="divide-y">
                                        {data.items.length === 0 && (
                                            <div className="p-8 text-center text-sm text-zinc-500">
                                                No items added. Press <span className="font-mono bg-zinc-100 px-1 rounded">Ctrl+I</span> or click below to add.
                                            </div>
                                        )}
                                        {data.items.map((item, index) => (
                                            <div key={index} className="grid grid-cols-12 gap-4 p-3 items-center text-sm hover:bg-zinc-50/50 transition-colors group relative">
                                                <div className={type === 'move' ? "col-span-6" : "col-span-5"}>
                                                    <div className="font-mono text-xs text-zinc-500">{item.code}</div>
                                                    <div className="font-medium">{item.name}</div>
                                                    {item.note && (
                                                        <div className="text-xs text-zinc-400 truncate mt-0.5 max-w-[300px]" title={item.note}>
                                                            📝 {item.note}
                                                        </div>
                                                    )}
                                                </div>
                                                <div className="col-span-2 text-center">
                                                    <span className="font-bold">{item.quantity}</span>
                                                    <span className="text-zinc-400 mx-1">/</span>
                                                    <span className="text-zinc-500">{item.warehouse_stock}</span>
                                                </div>
                                                <div className="col-span-2 text-right">
                                                    {item.price.toLocaleString()}
                                                </div>
                                                {type !== 'move' && (
                                                    <div className="col-span-1 text-right">
                                                        {item.discount > 0 ? <span className="text-red-500">-{item.discount.toLocaleString()}</span> : '-'}
                                                    </div>
                                                )}
                                                <div className="col-span-2 flex items-center justify-end gap-2">
                                                    <span className="font-semibold">{item.subtotal.toLocaleString()}</span>
                                                    <button
                                                        type="button"
                                                        onClick={() => removeItem(index)}
                                                        className="text-zinc-400 hover:text-red-500 opacity-0 group-hover:opacity-100 transition-opacity ml-2"
                                                    >
                                                        <Trash2 className="h-4 w-4" />
                                                    </button>
                                                </div>
                                            </div>
                                        ))}
                                    </div>
                                </div>

                                {/* Add New Line Item Button */}
                                <button
                                    type="button"
                                    onClick={() => setIsAddItemDrawerOpen(true)}
                                    className="w-full py-3 border-2 border-dashed rounded-lg text-sm text-zinc-400 hover:text-blue-600 hover:border-blue-200 hover:bg-blue-50/30 transition-all flex items-center justify-center gap-2"
                                >
                                    <Plus className="h-4 w-4" /> Add New Line Item (Ctrl+I)
                                </button>
                            </CardContent>
                        </Card>

                    </div>

                    {/* Right Column (Totals) */}
                    <div className="space-y-6">

                        {/* Totals Summary */}
                        <div className="bg-[#1e293b] text-white rounded-xl shadow-lg p-6 space-y-6">
                            <div className="flex items-center gap-3 mb-2">
                                <div className="p-1.5 bg-white/10 rounded">
                                    <span className="text-xl">🧾</span>
                                </div>
                                <div>
                                    <h3 className="font-bold text-lg leading-tight">Totals</h3>
                                    <div className="text-blue-200 text-sm">Summary</div>
                                </div>
                            </div>

                            <Separator className="bg-white/10" />

                            <div className="space-y-3 text-sm">
                                <div className="flex justify-between">
                                    <span className="text-blue-100">TOTAL QTY</span>
                                    <span className="font-semibold">{data.total_quantity.toLocaleString()}</span>
                                </div>
                                {type !== 'move' && (
                                    <>
                                        <div className="flex justify-between">
                                            <span className="text-blue-100">BEFORE DISC</span>
                                            <span className="font-semibold">{data.total_before_discount.toLocaleString()}</span>
                                        </div>
                                        <div className="flex justify-between items-center">
                                            <span className="text-blue-100">DISCOUNT (%)</span>
                                            <Input
                                                type="number"
                                                className="h-8 w-16 bg-white/10 border-white/20 text-right text-white"
                                                value={data.discount_percent}
                                                onChange={e => setData('discount_percent', Number(e.target.value))}
                                            />
                                        </div>
                                        <div className="flex justify-between">
                                            <span className="text-blue-100">TOTAL</span>
                                            <span className="font-semibold">{data.total_before_discount.toLocaleString()}</span>
                                        </div>
                                        <div className="flex justify-between">
                                            <span className="text-blue-100">TOTAL BEFORE PPN</span>
                                            <span className="font-semibold">{data.total_before_ppn.toLocaleString()}</span>
                                        </div>
                                        <div className="flex justify-between">
                                            <span className="text-blue-100">PPN ({ppn_rate}%)</span>
                                            <span className="font-semibold">{data.ppn_amount.toLocaleString()}</span>
                                        </div>
                                        <div className="flex justify-between items-center">
                                            <span className="text-blue-100">ADJUSTMENT</span>
                                            <Input
                                                type="number"
                                                className="h-8 w-24 bg-white/10 border-white/20 text-right text-white"
                                                value={data.adjustment}
                                                onChange={e => setData('adjustment', Number(e.target.value))}
                                            />
                                        </div>
                                    </>
                                )}
                            </div>

                            <Separator className="bg-white/10" />

                            <div className="space-y-1">
                                <div className="text-blue-200 text-xs uppercase tracking-wider">{type === 'move' ? 'Total' : 'Grand Total'}</div>
                                <div className="text-3xl font-bold">
                                    <span className="text-blue-200 text-lg mr-1">IDR</span>
                                    {data.grand_total.toLocaleString()}
                                </div>
                            </div>

                            <Button onClick={submit} type="button" className="w-full bg-white text-blue-900 hover:bg-blue-50 font-bold mt-4" size="lg">
                                SUBMIT NEW {type.toUpperCase()}
                            </Button>

                            <p className="text-xs text-blue-200 text-center leading-relaxed opacity-80">
                                By clicking submit, you agree to post this transaction to the general ledger.
                            </p>
                        </div>

                    </div>
                </div>

                <AddItemDrawer
                    isOpen={isAddItemDrawerOpen}
                    onClose={() => setIsAddItemDrawerOpen(false)}
                    onAdd={addItem}
                    isBuy={isBuy}
                    senderId={data.sender_id}
                    receiverId={data.receiver_id}
                    priceSource={config.price_source}
                    checkStock={data.sender?.type ? String(data.sender.type) === '2' : String(config.sender_type) === '2'}
                    type={type}
                />

            </form>
        </AppLayout>
    );
}
