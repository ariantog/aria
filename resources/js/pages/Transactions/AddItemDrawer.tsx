import { X, Check, Plus } from 'lucide-react';
import { useState, useEffect, useRef } from 'react';
import { toast } from 'sonner';
import { AsyncCombobox } from '@/components/AsyncCombobox';
import FormInput from '@/components/Partial/Form/FormInput';
import FormTextarea from '@/components/Partial/Form/FormTextarea';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import {
    Sheet,
    SheetContent,
    SheetHeader,
    SheetTitle,
    SheetFooter,
} from '@/components/ui/sheet';
import { cn } from '@/lib/utils';

interface Props {
    isOpen: boolean;
    onClose: () => void;
    onAdd: (item: any) => void;
    onUpdate?: (item: any, index: number) => void;
    isBuy: boolean;
    warehouses?: any[];
    senderId?: string;
    receiverId?: string;
    priceSource?: 'cost' | 'price';
    checkStock?: boolean;
    type?: string;
    initialItem?: any;
    editIndex?: number | null;
}

export default function AddItemModal({
    isOpen,
    onClose,
    onAdd,
    onUpdate,
    isBuy,
    senderId,
    receiverId,
    priceSource = 'cost',
    checkStock = false,
    type,
    initialItem,
    editIndex = null,
}: Props) {
    const isEdit = editIndex !== null;
    const [selectedItem, setSelectedItem] = useState<any>(null);
    const [quantity, setQuantity] = useState('');
    const [selectedWarehouseId, setSelectedWarehouseId] = useState<string>('');
    const [price, setPrice] = useState('');
    const [discount, setDiscount] = useState('');
    const [subtotal, setSubtotal] = useState('');
    const [error, setError] = useState<string | null>(null);

    // Refs for focus management
    const qtyInputRef = useRef<HTMLInputElement>(null);
    const priceInputRef = useRef<HTMLInputElement>(null);
    const discountInputRef = useRef<HTMLInputElement>(null);

    // Reset state function
    const resetForm = () => {
        setSelectedItem(null);
        setQuantity('');
        setPrice('');
        setDiscount('');
        setSubtotal('');
        setError(null);

        // Focus back to item search
        setTimeout(() => {
            const input = document.querySelector(
                '[data-sheet-content] input',
            ) as HTMLInputElement;
            if (input) input.focus();
        }, 100);
    };

    const handleItemSelect = (item: any) => {
        setSelectedItem(item);
        const defaultPrice =
            priceSource === 'cost'
                ? Number(item.cost || 0)
                : Number(item.price || 0);
        setPrice(String(defaultPrice));
        setQuantity('1'); // Default to 1 on selection/scan
        setTimeout(() => qtyInputRef.current?.focus(), 50);
    };

    // Reset state when opening
    useEffect(() => {
        if (isOpen) {
            if (isEdit && initialItem) {
                // Pre-fill for edit
                setSelectedItem({
                    id: initialItem.id,
                    code: initialItem.code,
                    name: initialItem.name,
                    warehouse_items: [
                        {
                            warehouse_id: initialItem.warehouse_id,
                            quantity: initialItem.warehouse_stock,
                        },
                    ],
                });
                setQuantity(String(initialItem.quantity));
                setPrice(String(initialItem.price));
                setDiscount(String(initialItem.discount || 0));
                setSubtotal(String(initialItem.subtotal));
                setSelectedWarehouseId(String(initialItem.warehouse_id));
                setError(null);

                // Focus quantity input when editing
                setTimeout(() => qtyInputRef.current?.focus(), 100);
            } else {
                resetForm();
                const defaultWh =
                    type === 'buy' || type === 'return' ? receiverId : senderId;
                setSelectedWarehouseId(defaultWh || '');
            }
        }
    }, [isOpen, type, senderId, receiverId, initialItem, isEdit]);

    const getStock = () => {
        if (
            !selectedItem ||
            !selectedItem.warehouse_items ||
            !selectedWarehouseId
        )
            return 0;
        const stock = selectedItem.warehouse_items.find(
            (wi: any) =>
                String(wi.warehouse_id) === String(selectedWarehouseId),
        );
        return stock ? Number(stock.quantity) : 0;
    };

    const autoCalculateSubtotal = () => {
        const qty = Number(quantity) || 0;
        const p = Number(price) || 0;
        const d = Number(discount) || 0;
        return qty * p - d;
    };

    // Auto-update subtotal when price/qty/disc changes
    useEffect(() => {
        setSubtotal(String(autoCalculateSubtotal()));
    }, [quantity, price, discount]);

    const handleAdd = (keepOpen: boolean = false) => {
        if (!selectedItem || !quantity) return;

        if (checkStock) {
            const stock = getStock();
            if (Number(quantity) > stock) {
                setError(`Insufficient stock. Available: ${stock}`);
                return;
            }
        }

        setError(null);
        const itemData = {
            id: selectedItem.id,
            item_id: selectedItem.id, // Ensure item_id is present for store
            code: selectedItem.code,
            name: selectedItem.name,
            quantity: Number(quantity),
            warehouse_id: selectedWarehouseId,
            warehouse_name: 'Selected',
            warehouse_stock: getStock(),
            price: Number(price),
            discount: Number(discount),
            subtotal: Number(subtotal),
            note: '',
        };

        if (isEdit && onUpdate && editIndex !== null) {
            onUpdate(itemData, editIndex);
            toast.success(`${selectedItem.name} updated`);
            onClose();
        } else {
            onAdd(itemData);
            toast.success(`${selectedItem.name} added to list`);
            if (keepOpen) {
                resetForm();
            } else {
                onClose();
            }
        }
    };

    const handleKeyDown = (e: React.KeyboardEvent, field: string) => {
        if (e.key === 'Enter') {
            e.preventDefault();
            if (field === 'qty') priceInputRef.current?.focus();
            if (field === 'price') {
                if (type === 'move') {
                    handleAdd(true); // Keep open on Enter
                } else {
                    discountInputRef.current?.focus();
                }
            }
            if (field === 'discount') {
                handleAdd(true); // Keep open on Enter
            }
        }
    };

    return (
        <Sheet open={isOpen} onOpenChange={(val) => !val && onClose()}>
            <SheetContent
                side="right"
                hideOverlay={true}
                className="flex w-full flex-col border-l border-zinc-200 p-0 shadow-[-15px_0_40px_rgba(0,0,0,0.15)] sm:max-w-[450px] dark:border-zinc-800"
                data-sheet-content
            >
                <SheetHeader className="border-b bg-zinc-50/80 px-4 py-2.5 backdrop-blur-sm dark:bg-zinc-900/80">
                    <SheetTitle className="flex items-center gap-2 text-sm font-black tracking-tight uppercase">
                        <div className="flex items-center justify-center rounded-full bg-blue-600 p-1 text-white">
                            {isEdit ? (
                                <Check className="h-3 w-3" strokeWidth={4} />
                            ) : (
                                <Plus className="h-3 w-3" strokeWidth={4} />
                            )}
                        </div>
                        {isEdit ? 'Edit Item' : 'Add Item'}
                    </SheetTitle>
                </SheetHeader>

                <div className="flex-1 space-y-4 overflow-y-auto px-4 py-4">
                    {/* Item Search */}
                    <div className={cn('space-y-1', isEdit && 'opacity-60')}>
                        <div className="flex items-end justify-between">
                            <Label className="text-[10px] font-black tracking-widest text-zinc-400 uppercase">
                                {isEdit ? 'Selected Item' : 'Search Item'}
                            </Label>
                            {selectedItem && (
                                <div className="rounded bg-blue-50 px-1.5 py-0.5 font-mono text-[9px] leading-none text-blue-600 dark:bg-blue-900/30 dark:text-blue-400">
                                    ID: {selectedItem.id}
                                </div>
                            )}
                        </div>
                        <AsyncCombobox
                            endpoint="/items?json=true&type=1,2"
                            placeholder="Scan barcode or type item name..."
                            onChange={handleItemSelect}
                            value={selectedItem}
                            disabled={isEdit}
                            renderItem={(item: any) => (
                                <div className="flex flex-col py-0.5 text-xs">
                                    <span className="text-sm font-bold tracking-tight">
                                        #{item.id} - {item.name}
                                    </span>
                                    <span className="text-muted-foreground">
                                        {item.code} | Stock:{' '}
                                        {item.warehouse_items?.find(
                                            (wi: any) =>
                                                String(wi.warehouse_id) ===
                                                String(selectedWarehouseId),
                                        )?.quantity || 0}
                                    </span>
                                </div>
                            )}
                            className="h-9 w-full text-sm focus:ring-1 focus:ring-blue-600"
                        />
                        {selectedItem && (
                            <div className="truncate pt-0.5 text-[10px] text-zinc-500">
                                {selectedItem.code} —{' '}
                                <span className="font-medium text-zinc-700 dark:text-zinc-300">
                                    {selectedItem.name}
                                </span>
                            </div>
                        )}
                    </div>

                    <div className="grid grid-cols-12 items-end gap-3">
                        <div className="col-span-7">
                            <FormInput
                                id="qty"
                                label="QTY"
                                ref={qtyInputRef}
                                type="number"
                                value={quantity}
                                onChange={(e) => setQuantity(e.target.value)}
                                onKeyDown={(e) => handleKeyDown(e, 'qty')}
                                className="h-12 text-center text-2xl font-black"
                                autoComplete="off"
                                required
                            />
                        </div>

                        <div className="col-span-5 space-y-1">
                            <Label className="block text-center text-[10px] font-black tracking-widest text-zinc-400 uppercase">
                                Warehouse Stock
                            </Label>
                            <div className="flex h-12 flex-col items-center justify-center rounded-md border bg-zinc-50 font-mono dark:bg-zinc-800/20">
                                <span className="text-xl leading-none font-black text-blue-600">
                                    {selectedItem ? getStock() : 0}
                                </span>
                                <span className="mt-1 text-[8px] font-bold text-zinc-400 uppercase">
                                    Available
                                </span>
                            </div>
                        </div>
                    </div>

                    <div className="grid grid-cols-2 gap-3">
                        <FormInput
                            id="price"
                            label="Unit Price"
                            ref={priceInputRef}
                            type="number"
                            value={price}
                            onChange={(e) => setPrice(e.target.value)}
                            onKeyDown={(e) => handleKeyDown(e, 'price')}
                            className="h-9 text-sm font-bold"
                        />

                        {type !== 'move' && (
                            <FormInput
                                id="discount"
                                label="Discount (Nominal)"
                                ref={discountInputRef}
                                type="number"
                                value={discount}
                                onChange={(e) => setDiscount(e.target.value)}
                                onKeyDown={(e) => handleKeyDown(e, 'discount')}
                                placeholder="0"
                                className="h-9 text-sm font-bold text-red-600"
                            />
                        )}
                    </div>

                    <div className="grid grid-cols-1 gap-3">
                        {/* Note Removed by user request */}
                    </div>

                    {error && (
                        <div className="flex items-center gap-2 rounded border border-red-200 bg-red-50 px-3 py-2 text-[11px] text-red-600 dark:border-red-800 dark:bg-red-900/20 dark:text-red-400">
                            <X className="h-3.5 w-3.5 animate-pulse" />
                            {error}
                        </div>
                    )}
                </div>

                <div className="mt-auto">
                    <div className="flex items-center justify-between bg-zinc-900 px-4 py-3 text-white">
                        <div className="flex flex-col">
                            <span className="text-[9px] leading-none font-bold tracking-widest text-zinc-500 uppercase">
                                Line Item Subtotal
                            </span>
                            <span className="mt-1 font-mono text-[10px] font-bold text-blue-400">
                                Editable Amount
                            </span>
                        </div>
                        <div className="flex items-center gap-2">
                            <span className="text-xs font-bold opacity-50">
                                IDR
                            </span>
                            <input
                                type="number"
                                value={subtotal}
                                onChange={(e) => setSubtotal(e.target.value)}
                                className="w-32 border-b border-blue-600 bg-transparent text-right text-2xl font-black tracking-tight text-blue-400 outline-none focus:border-blue-400"
                            />
                        </div>
                    </div>

                    <SheetFooter className="grid grid-cols-3 gap-2 border-t bg-zinc-50 p-3 sm:space-x-0 dark:bg-zinc-950">
                        <Button
                            variant="ghost"
                            onClick={onClose}
                            className="h-9 text-[10px] font-bold text-zinc-500 uppercase"
                        >
                            Cancel
                        </Button>
                        {!isEdit && (
                            <Button
                                onClick={() => handleAdd(false)}
                                className="h-9 bg-zinc-200 text-[10px] font-black text-zinc-900 uppercase hover:bg-zinc-300 dark:bg-zinc-800 dark:text-zinc-100 dark:hover:bg-zinc-700"
                            >
                                Add & Close
                            </Button>
                        )}
                        <Button
                            onClick={() => handleAdd(true)}
                            className={cn(
                                'h-9 border-b-2 border-blue-900 bg-blue-700 text-[10px] font-black text-white uppercase shadow-md hover:bg-blue-800',
                                isEdit && 'col-span-2',
                            )}
                        >
                            {isEdit ? 'Save Changes' : 'Save Line (Enter)'}
                        </Button>
                    </SheetFooter>
                </div>
            </SheetContent>
        </Sheet>
    );
}
