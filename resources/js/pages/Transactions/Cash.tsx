import { Head, Link, useForm } from '@inertiajs/react';
import { Plus, Trash2, ArrowLeft, Loader2, Info } from 'lucide-react';
import { useState, useEffect } from 'react';
import { AsyncCombobox } from '@/components/AsyncCombobox';
import FormInput from '@/components/Partial/Form/FormInput';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import transactions from '@/routes/transactions';

interface Bank {
    id: number;
    name: string;
}

interface Props {
    bankList: Bank[];
    ppn_rate: number;
    type: 'in' | 'out'; // Determiner
    min_date?: string;
}

interface CashItem {
    id: string; // Unique UI ID
    customer_id: string;
    customer: any;
    invoice_number: string;
    note: string;
    total: number;
}

export default function Cash({ bankList, ppn_rate, type, min_date }: Props) {
    const isCashIn = type === 'in';
    const config = {
        title: isCashIn ? 'New Cash In' : 'New Cash Out',
        description: isCashIn
            ? 'Record cash received from customers or other sources.'
            : 'Record cash payments to suppliers or other recipients.',
        saveLabel: isCashIn ? 'Save Cash In' : 'Save Cash Out',
        endpoint: isCashIn
            ? transactions.cashIn.store.url()
            : transactions.cashOut.store.url(),
        lookupType: isCashIn ? 'buy' : 'cash-out', // buy sender is customer/supplier for in, cash-out receiver for out
        lookupRole: isCashIn ? 'sender' : 'receiver',
        sourceLabel: isCashIn ? 'Name / Source' : 'Name / Recipient',
        sourcePlaceholder: isCashIn
            ? 'Select source...'
            : 'Select recipient...',
        cardTitle: isCashIn ? 'Cash In Details' : 'Cash Out Details',
        submitColor: 'bg-blue-700 hover:bg-blue-800',
    };

    const today = new Date().toISOString().split('T')[0];
    const { data, setData, post, processing, errors } = useForm({
        date: (min_date && today < min_date) ? min_date : today,
        account_id: '',
        account: null as Bank | null,
        items: [
            {
                id: Math.random().toString(36).substr(2, 9),
                customer_id: '',
                customer: null,
                invoice_number: '',
                note: '',
                total: 0,
            },
        ] as CashItem[],
    });

    const addItem = () => {
        setData('items', [
            ...data.items,
            {
                id: Math.random().toString(36).substr(2, 9),
                customer_id: '',
                customer: null,
                invoice_number: '',
                note: '',
                total: 0,
            },
        ]);
    };

    const removeItem = (id: string) => {
        if (data.items.length === 1) return;
        setData(
            'items',
            data.items.filter((item) => item.id !== id),
        );
    };

    const updateItem = (id: string, field: keyof CashItem, value: any) => {
        setData(
            'items',
            data.items.map((item) => {
                if (item.id === id) {
                    const newItem = { ...item, [field]: value };
                    if (field === 'customer') {
                        newItem.customer_id = String(value?.id || '');
                    }
                    return newItem;
                }
                return item;
            }),
        );
    };

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        post(config.endpoint);
    };

    const grandTotal = data.items.reduce(
        (sum, item) => sum + Number(item.total || 0),
        0,
    );

    // Focus management
    const [inputRefs] = useState<{ [key: string]: { [key: string]: any } }>({});

    const setRef = (id: string, field: string, el: any) => {
        if (!inputRefs[id]) {
            inputRefs[id] = {};
        }
        inputRefs[id][field] = el;
    };

    const focusField = (id: string, field: string) => {
        setTimeout(() => {
            const el = inputRefs[id]?.[field];
            if (el) {
                el.focus();
                if (el.select) el.select();
            }
        }, 50);
    };

    const handleKeyDown = (
        e: React.KeyboardEvent,
        id: string,
        field: string,
    ) => {
        if (e.key === 'Enter') {
            if (field === 'customer') {
                focusField(id, 'invoice_number');
            } else {
                e.preventDefault();
                if (field === 'invoice_number') {
                    focusField(id, 'note');
                } else if (field === 'note') {
                    focusField(id, 'total');
                } else if (field === 'total') {
                    const newId = Math.random().toString(36).substr(2, 9);
                    setData('items', [
                        ...data.items,
                        {
                            id: newId,
                            customer_id: '',
                            customer: null,
                            invoice_number: '',
                            note: '',
                            total: 0,
                        },
                    ]);
                    focusField(newId, 'customer');
                }
            }
        }
    };

    const getExcludedCustomerIds = (currentId: string) => {
        return data.items
            .filter((item) => item.id !== currentId && item.customer_id)
            .map((item) => item.customer_id);
    };

    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Transactions', href: '/transactions' },
                { title: isCashIn ? 'Cash In' : 'Cash Out', href: '#' },
            ]}
        >
            <Head title={config.title} />

            <form onSubmit={submit} className="sm:p-6 lg:p-8">
                <div className="mb-8 flex flex-col items-start justify-between gap-4 sm:flex-row sm:items-center">
                    <div className="flex items-center gap-4">
                        <Link href={'/transactions'}>
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
                                {config.title}
                            </h2>
                            <p className="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                                {config.description}
                            </p>
                        </div>
                    </div>
                    <div className="flex items-center gap-2">
                        <Button
                            type="submit"
                            disabled={processing}
                            className={cn(
                                config.submitColor,
                                'min-w-[140px] text-white',
                            )}
                        >
                            {processing ? (
                                <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                            ) : (
                                <span className="mr-2">{config.saveLabel}</span>
                            )}
                        </Button>
                    </div>
                </div>

                <div className="space-y-6">
                    <Card>
                        <CardHeader className="border-b pb-3">
                            <CardTitle className="flex items-center gap-2 text-base font-semibold">
                                <Info className="h-4 w-4 text-blue-600" />
                                Basic Information
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="grid grid-cols-1 gap-8 pt-6 md:grid-cols-2">
                            <FormInput
                                id="date"
                                label="Transaction Date"
                                type="date"
                                min={min_date}
                                value={data.date}
                                onChange={(e) =>
                                    setData('date', e.target.value)
                                }
                                error={errors.date}
                                required
                            />
                            <div className="space-y-2">
                                <Label
                                    htmlFor="account_id"
                                    className="mb-2 block text-sm font-semibold"
                                >
                                    Bank (Account){' '}
                                    <span className="text-red-500">*</span>
                                </Label>
                                <AsyncCombobox
                                    id="account_id"
                                    endpoint={transactions.lookup.url({
                                        type: config.lookupType,
                                        role: config.lookupRole,
                                    })}
                                    additionalParams={{ addrbook_type: 3 }} // TYPE_BANK
                                    value={data.account}
                                    onChange={(val) => {
                                        setData((d) => ({
                                            ...d,
                                            account: val,
                                            account_id: val
                                                ? String(val.id)
                                                : '',
                                        }));
                                    }}
                                    placeholder="Select account / bank..."
                                    className="w-full"
                                    isInvalid={!!errors.account_id}
                                />
                                {errors.account_id && (
                                    <p className="mt-1 text-xs text-red-500">
                                        {errors.account_id}
                                    </p>
                                )}
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between border-b pb-3">
                            <CardTitle className="flex items-center gap-2 text-base font-semibold">
                                <div className="flex h-5 w-5 items-center justify-center rounded bg-blue-100 dark:bg-blue-900/30">
                                    <span className="text-xs font-bold text-blue-600">
                                        $
                                    </span>
                                </div>
                                {config.cardTitle}
                            </CardTitle>
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                onClick={addItem}
                                className="h-8"
                            >
                                <Plus className="mr-1 h-4 w-4" /> Add Row
                            </Button>
                        </CardHeader>
                        <CardContent className="p-0">
                            <div className="flex flex-col">
                                {/* Header - Hidden on Mobile */}
                                <div className="hidden grid-cols-12 gap-4 border-b bg-zinc-50 p-3 text-[10px] font-bold tracking-wider text-zinc-500 uppercase sm:grid dark:bg-zinc-900/50">
                                    <div className="col-span-4">{config.sourceLabel}</div>
                                    <div className="col-span-2">Invoice (Opt)</div>
                                    <div className="col-span-3">Note</div>
                                    <div className="col-span-2 text-right">Total Amount</div>
                                    <div className="col-span-1 text-center">Action</div>
                                </div>

                                {/* Rows - Cards on Mobile, Grid on Desktop */}
                                <div className="divide-y">
                                    {data.items.map((item, index) => (
                                        <div
                                            key={item.id}
                                            className="flex flex-col gap-4 p-4 transition-colors hover:bg-zinc-50/50 sm:grid sm:grid-cols-12 sm:items-center sm:gap-4 sm:p-3 dark:hover:bg-zinc-900/50"
                                        >
                                            {/* Source / Name */}
                                            <div className="col-span-4 space-y-1.5">
                                                <Label className="text-[10px] font-bold text-zinc-500 uppercase sm:hidden">
                                                    {config.sourceLabel}
                                                </Label>
                                                <AsyncCombobox
                                                    ref={(el) =>
                                                        setRef(
                                                            item.id,
                                                            'customer',
                                                            el,
                                                        )
                                                    }
                                                    endpoint={transactions.lookup.url(
                                                        {
                                                            type: config.lookupType,
                                                            role: config.lookupRole,
                                                        },
                                                    )}
                                                    value={item.customer}
                                                    onChange={(val) =>
                                                        updateItem(
                                                            item.id,
                                                            'customer',
                                                            val,
                                                        )
                                                    }
                                                    excludedIds={getExcludedCustomerIds(
                                                        item.id,
                                                    )}
                                                    placeholder={
                                                        config.sourcePlaceholder
                                                    }
                                                    className="w-full"
                                                    onKeyDown={(e) =>
                                                        handleKeyDown(
                                                            e,
                                                            item.id,
                                                            'customer',
                                                        )
                                                    }
                                                    onSelect={() =>
                                                        focusField(
                                                            item.id,
                                                            'invoice_number',
                                                        )
                                                    }
                                                />
                                                {errors[
                                                    `items.${index}.customer_id`
                                                ] && (
                                                    <p className="mt-1 text-[10px] text-red-500">
                                                        {
                                                            errors[
                                                                `items.${index}.customer_id`
                                                            ]
                                                        }
                                                    </p>
                                                )}
                                            </div>

                                            {/* Invoice Number */}
                                            <div className="col-span-2 space-y-1.5">
                                                <Label className="text-[10px] font-bold text-zinc-500 uppercase sm:hidden">
                                                    Invoice (Opt)
                                                </Label>
                                                <Input
                                                    ref={(el) =>
                                                        setRef(
                                                            item.id,
                                                            'invoice_number',
                                                            el,
                                                        )
                                                    }
                                                    value={
                                                        item.invoice_number
                                                    }
                                                    onChange={(e) =>
                                                        updateItem(
                                                            item.id,
                                                            'invoice_number',
                                                            e.target.value,
                                                        )
                                                    }
                                                    onKeyDown={(e) =>
                                                        handleKeyDown(
                                                            e,
                                                            item.id,
                                                            'invoice_number',
                                                        )
                                                    }
                                                    placeholder="Ref. Invoice"
                                                    className="h-9"
                                                />
                                            </div>

                                            {/* Note */}
                                            <div className="col-span-3 space-y-1.5">
                                                <Label className="text-[10px] font-bold text-zinc-500 uppercase sm:hidden">
                                                    Note
                                                </Label>
                                                <Input
                                                    ref={(el) =>
                                                        setRef(
                                                            item.id,
                                                            'note',
                                                            el,
                                                        )
                                                    }
                                                    value={item.note}
                                                    onChange={(e) =>
                                                        updateItem(
                                                            item.id,
                                                            'note',
                                                            e.target.value,
                                                        )
                                                    }
                                                    onKeyDown={(e) =>
                                                        handleKeyDown(
                                                            e,
                                                            item.id,
                                                            'note',
                                                        )
                                                    }
                                                    placeholder="Add note..."
                                                    className="h-9"
                                                />
                                            </div>

                                            {/* Total Amount */}
                                            <div className="col-span-2 space-y-1.5">
                                                <Label className="text-[10px] font-bold text-zinc-500 uppercase sm:hidden">
                                                    Total Amount
                                                </Label>
                                                <div className="relative">
                                                    <span className="absolute top-1/2 left-3 -translate-y-1/2 text-xs text-zinc-400">
                                                        Rp
                                                    </span>
                                                    <Input
                                                        ref={(el) =>
                                                            setRef(
                                                                item.id,
                                                                'total',
                                                                el,
                                                            )
                                                        }
                                                        type="number"
                                                        value={item.total}
                                                        onChange={(e) =>
                                                            updateItem(
                                                                item.id,
                                                                'total',
                                                                Number(
                                                                    e.target
                                                                        .value,
                                                                ),
                                                            )
                                                        }
                                                        onKeyDown={(e) =>
                                                            handleKeyDown(
                                                                e,
                                                                item.id,
                                                                'total',
                                                            )
                                                        }
                                                        className="h-9 pl-9 text-right font-medium"
                                                    />
                                                </div>
                                            </div>

                                            {/* Action */}
                                            <div className="col-span-1 flex items-center justify-end sm:justify-center">
                                                <Button
                                                    type="button"
                                                    variant="ghost"
                                                    size="icon"
                                                    onClick={() =>
                                                        removeItem(item.id)
                                                    }
                                                    disabled={
                                                        data.items
                                                            .length === 1
                                                    }
                                                    className="h-8 w-8 text-zinc-400 hover:text-red-500"
                                                >
                                                    <Trash2 className="h-4 w-4" />
                                                </Button>
                                            </div>
                                        </div>
                                    ))}
                                </div>

                                {/* Footer / Grand Total */}
                                <div className="border-t bg-zinc-50 p-4 sm:flex sm:items-center sm:justify-between sm:p-6 dark:bg-zinc-900/50">
                                    <div className="hidden sm:block"></div>
                                    <div className="flex items-center justify-between sm:gap-8">
                                        <span className="text-sm font-bold tracking-tight text-zinc-500 uppercase sm:text-base">
                                            Grand Total
                                        </span>
                                        <span className="text-xl font-black text-blue-700 sm:text-2xl dark:text-blue-400">
                                            IDR{' '}
                                            {grandTotal.toLocaleString()}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <Alert className="border-blue-100 bg-blue-50 dark:border-blue-900/20 dark:bg-blue-900/10">
                        <Info className="h-4 w-4 text-blue-600" />
                        <AlertTitle className="text-blue-800 dark:text-blue-300">
                            Transaction Info
                        </AlertTitle>
                        <AlertDescription className="text-xs text-blue-700/80 dark:text-blue-300/80">
                            Each row will be saved as a separate transaction
                            record. If an invoice reference is provided, it will
                            be linked to this payment.
                        </AlertDescription>
                    </Alert>
                </div>
            </form>
        </AppLayout>
    );
}
