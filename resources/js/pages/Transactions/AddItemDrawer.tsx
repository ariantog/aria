import { useState, useEffect, useRef } from 'react';
import { Sheet, SheetContent, SheetHeader, SheetTitle, SheetFooter } from '@/components/ui/sheet';
import { Label } from '@/components/ui/label';
import { Button } from '@/components/ui/button';
import { AsyncCombobox } from '@/components/AsyncCombobox';
import FormInput from '@/components/Partial/Form/FormInput';
import FormTextarea from '@/components/Partial/Form/FormTextarea';
import { X, Check, Plus } from 'lucide-react';
import { cn } from '@/lib/utils';
import { toast } from 'sonner';

interface Props {
    isOpen: boolean;
    onClose: () => void;
    onAdd: (item: any) => void;
    isBuy: boolean;
    warehouses?: any[];
    senderId?: string;
    receiverId?: string;
    priceSource?: 'cost' | 'price';
    checkStock?: boolean;
    type?: string;
}

export default function AddItemModal({ isOpen, onClose, onAdd, isBuy, senderId, receiverId, priceSource = 'cost', checkStock = false, type }: Props) {
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
            const input = document.querySelector('[data-sheet-content] input') as HTMLInputElement;
            if (input) input.focus();
        }, 100);
    };

    // Reset state when opening
    useEffect(() => {
        if (isOpen) {
            resetForm();
            const defaultWh = (type === 'buy' || type === 'return') ? receiverId : senderId;
            setSelectedWarehouseId(defaultWh || '');
        }
    }, [isOpen, type, senderId, receiverId]);

    const handleItemSelect = (item: any) => {
        setSelectedItem(item);
        const defaultPrice = priceSource === 'cost' ? Number(item.cost || 0) : Number(item.price || 0);
        setPrice(String(defaultPrice));
        setTimeout(() => qtyInputRef.current?.focus(), 50);
    };

    const getStock = () => {
        if (!selectedItem || !selectedItem.warehouse_items || !selectedWarehouseId) return 0;
        const stock = selectedItem.warehouse_items.find((wi: any) => String(wi.warehouse_id) === String(selectedWarehouseId));
        return stock ? Number(stock.quantity) : 0;
    };

    const autoCalculateSubtotal = () => {
        const qty = Number(quantity) || 0;
        const p = Number(price) || 0;
        const d = Number(discount) || 0;
        return (qty * p) - d;
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
        onAdd({
            id: selectedItem.id,
            code: selectedItem.code,
            name: selectedItem.name,
            quantity: Number(quantity),
            warehouse_id: selectedWarehouseId,
            warehouse_name: 'Selected',
            warehouse_stock: getStock(),
            price: Number(price),
            discount: Number(discount),
            subtotal: Number(subtotal),
            note: ''
        });

        toast.success(`${selectedItem.name} added to list`);

        if (keepOpen) {
            resetForm();
        } else {
            onClose();
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
        <Sheet open={isOpen} onOpenChange={onClose} modal={false}>
            <SheetContent
                side="right"
                hideOverlay={true}
                className="w-full sm:max-w-[450px] p-0 flex flex-col shadow-[-15px_0_40px_rgba(0,0,0,0.15)] border-l border-zinc-200 dark:border-zinc-800"
                data-sheet-content
            >
                <SheetHeader className="px-4 py-2.5 border-b bg-zinc-50/80 dark:bg-zinc-900/80 backdrop-blur-sm">
                    <SheetTitle className="flex items-center gap-2 text-sm font-black uppercase tracking-tight">
                        <div className="bg-blue-600 text-white rounded-full p-1 flex items-center justify-center">
                            <Plus className="w-3 h-3" strokeWidth={4} />
                        </div>
                        Add Item
                    </SheetTitle>
                </SheetHeader>

                <div className="flex-1 overflow-y-auto px-4 py-4 space-y-4">
                    {/* Item Search */}
                    <div className="space-y-1">
                        <div className="flex justify-between items-end">
                            <Label className="text-[10px] font-black text-zinc-400 uppercase tracking-widest">Search Item</Label>
                            {selectedItem && (
                                <div className="text-[9px] font-mono text-blue-600 dark:text-blue-400 bg-blue-50 dark:bg-blue-900/30 px-1.5 py-0.5 rounded leading-none">
                                    ID: {selectedItem.id}
                                </div>
                            )}
                        </div>
                        <AsyncCombobox
                            endpoint="/items?json=true&type=1,2"
                            placeholder="Scan barcode or type item name..."
                            onChange={handleItemSelect}
                            value={selectedItem}
                            renderItem={(item: any) => (
                                <div className="flex flex-col py-0.5 text-xs">
                                    <span className="font-bold text-sm tracking-tight">#{item.id} - {item.name}</span>
                                    <span className="text-muted-foreground">{item.code} | Stock: {item.warehouse_items?.find((wi: any) => String(wi.warehouse_id) === String(selectedWarehouseId))?.quantity || 0}</span>
                                </div>
                            )}
                            className="w-full h-9 text-sm focus:ring-1 focus:ring-blue-600"
                        />
                        {selectedItem && (
                            <div className="text-[10px] text-zinc-500 truncate pt-0.5">
                                {selectedItem.code} — <span className="font-medium text-zinc-700 dark:text-zinc-300">{selectedItem.name}</span>
                            </div>
                        )}
                    </div>

                    <div className="grid grid-cols-12 gap-3 items-end">
                        <div className="col-span-7">
                            <FormInput
                                id="qty"
                                label="QTY"
                                ref={qtyInputRef}
                                type="number"
                                value={quantity}
                                onChange={(e) => setQuantity(e.target.value)}
                                onKeyDown={(e) => handleKeyDown(e, 'qty')}
                                className="font-black text-2xl h-12 text-center"
                                autoComplete="off"
                                required
                            />
                        </div>

                        <div className="col-span-5 space-y-1">
                            <Label className="text-[10px] font-black text-zinc-400 uppercase tracking-widest text-center block">Warehouse Stock</Label>
                            <div className="h-12 flex flex-col items-center justify-center rounded-md border bg-zinc-50 dark:bg-zinc-800/20 font-mono">
                                <span className="text-xl font-black text-blue-600 leading-none">{selectedItem ? getStock() : 0}</span>
                                <span className="text-[8px] text-zinc-400 uppercase font-bold mt-1">Available</span>
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
                        <div className="bg-red-50 dark:bg-red-900/20 px-3 py-2 rounded border border-red-200 dark:border-red-800 text-red-600 dark:text-red-400 text-[11px] flex items-center gap-2">
                            <X className="h-3.5 w-3.5 animate-pulse" />
                            {error}
                        </div>
                    )}
                </div>

                <div className="mt-auto">
                    <div className="px-4 py-3 flex justify-between items-center bg-zinc-900 text-white">
                        <div className="flex flex-col">
                            <span className="text-[9px] font-bold uppercase tracking-widest text-zinc-500 leading-none">Line Item Subtotal</span>
                            <span className="text-[10px] text-blue-400 font-mono mt-1 font-bold">Editable Amount</span>
                        </div>
                        <div className="flex items-center gap-2">
                            <span className="text-xs font-bold opacity-50">IDR</span>
                            <input
                                type="number"
                                value={subtotal}
                                onChange={(e) => setSubtotal(e.target.value)}
                                className="bg-transparent border-b border-blue-600 focus:border-blue-400 outline-none text-2xl font-black tracking-tight text-blue-400 w-32 text-right"
                            />
                        </div>
                    </div>

                    <SheetFooter className="p-3 grid grid-cols-3 gap-2 sm:space-x-0 border-t bg-zinc-50 dark:bg-zinc-950">
                        <Button variant="ghost" onClick={onClose} className="h-9 text-[10px] uppercase font-bold text-zinc-500">
                            Cancel
                        </Button>
                        <Button onClick={() => handleAdd(false)} className="h-9 bg-zinc-200 dark:bg-zinc-800 hover:bg-zinc-300 dark:hover:bg-zinc-700 text-zinc-900 dark:text-zinc-100 text-[10px] uppercase font-black">
                            Add & Close
                        </Button>
                        <Button onClick={() => handleAdd(true)} className="h-9 bg-blue-700 hover:bg-blue-800 text-white text-[10px] uppercase font-black shadow-md border-b-2 border-blue-900">
                            Save Line (Enter)
                        </Button>
                    </SheetFooter>
                </div>
            </SheetContent>
        </Sheet>
    );
}
