import { Head, useForm } from '@inertiajs/react';
import { AlertCircle, Calendar, FileText, Info, Save } from 'lucide-react';
import React from 'react';
import { AsyncCombobox } from '@/components/AsyncCombobox';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/app-layout';
import transactions from '@/routes/transactions';
import type { BreadcrumbItem } from '@/types';

interface Props {
    min_date?: string;
}

export default function Adjust({ min_date }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Transactions', href: '/transactions' },
        { title: 'New Adjust', href: '/transactions/adjust' },
    ];

    const today = new Date().toISOString().split('T')[0];
    const { data, setData, post, processing, errors } = useForm({
        date: (min_date && today < min_date) ? min_date : today,
        invoice: '',
        sender: '', // Debit(+)
        receiver: '', // Credit(+)
        description: '',
        total: 0,
    });

    const [senderBalance, setSenderBalance] = React.useState<number | null>(
        null,
    );
    const [receiverBalance, setReceiverBalance] = React.useState<number | null>(
        null,
    );

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post('/transactions/adjust');
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="New Adjust" />

            <div className="p-4 sm:p-6 lg:p-8">
                <div className="mb-8">
                    <h2 className="text-2xl font-bold tracking-tight text-indigo-600 text-zinc-900 dark:text-zinc-50">
                        New Adjust
                    </h2>
                    <p className="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                        Direct balance adjustment between accounts and contacts.
                    </p>
                </div>

                <form onSubmit={handleSubmit} className="max-w-5xl space-y-6">
                    <Card className="border-border shadow-sm">
                        <CardContent className="p-6">
                            <div className="grid grid-cols-1 gap-8 md:grid-cols-2">
                                {/* General Info */}
                                <div className="space-y-5">
                                    <div className="space-y-2">
                                        <Label
                                            htmlFor="date"
                                            className="flex items-center gap-2"
                                        >
                                            <Calendar className="h-4 w-4 text-zinc-400" />
                                            Date
                                        </Label>
                                        <Input
                                            id="date"
                                            type="date"
                                            min={min_date}
                                            value={data.date}
                                            onChange={(e) =>
                                                setData('date', e.target.value)
                                            }
                                            className={
                                                errors.date
                                                    ? 'border-destructive'
                                                    : ''
                                            }
                                        />
                                        {errors.date && (
                                            <p className="text-xs text-destructive">
                                                {errors.date}
                                            </p>
                                        )}
                                    </div>

                                    <div className="space-y-2">
                                        <Label
                                            htmlFor="invoice"
                                            className="flex items-center gap-2"
                                        >
                                            <FileText className="h-4 w-4 text-zinc-400" />
                                            Invoice Number
                                        </Label>
                                        <Input
                                            id="invoice"
                                            placeholder="Optional"
                                            value={data.invoice}
                                            onChange={(e) =>
                                                setData(
                                                    'invoice',
                                                    e.target.value,
                                                )
                                            }
                                        />
                                        {errors.invoice && (
                                            <p className="text-xs text-destructive">
                                                {errors.invoice}
                                            </p>
                                        )}
                                    </div>
                                </div>

                                {/* Entities */}
                                <div className="space-y-5">
                                    <div className="space-y-2">
                                        <Label
                                            htmlFor="receiver"
                                            className="flex items-center gap-2 font-semibold text-emerald-600"
                                        >
                                            Credit (+) / Receiver
                                        </Label>
                                        <AsyncCombobox
                                            placeholder="Search Account / Contact..."
                                            endpoint={transactions.lookup.url({
                                                type: 'adjust',
                                                role: 'receiver',
                                            })}
                                            onChange={(item: any) => {
                                                setData(
                                                    'receiver',
                                                    item?.id.toString() || '',
                                                );
                                                setReceiverBalance(
                                                    item
                                                        ? Number(
                                                              item.balance || 0,
                                                          )
                                                        : null,
                                                );
                                            }}
                                            isInvalid={!!errors.receiver}
                                        />
                                        {receiverBalance !== null && (
                                            <div className="mt-1.5 flex items-center gap-2 rounded-lg border border-emerald-100 bg-emerald-50 px-3 py-1.5 dark:border-emerald-900/30 dark:bg-emerald-900/20">
                                                <span className="text-[10px] font-bold tracking-wider text-emerald-600 uppercase dark:text-emerald-400">
                                                    Current Balance
                                                </span>
                                                <span className="font-mono text-sm font-bold text-emerald-700 dark:text-emerald-300">
                                                    Rp{' '}
                                                    {receiverBalance.toLocaleString()}
                                                </span>
                                            </div>
                                        )}
                                        {errors.receiver && (
                                            <p className="text-xs text-destructive">
                                                {errors.receiver}
                                            </p>
                                        )}
                                    </div>

                                    <div className="space-y-2">
                                        <Label
                                            htmlFor="sender"
                                            className="flex items-center gap-2 font-semibold text-rose-600"
                                        >
                                            Debit (+) / Sender
                                        </Label>
                                        <AsyncCombobox
                                            placeholder="Search Account / Contact..."
                                            endpoint={transactions.lookup.url({
                                                type: 'adjust',
                                                role: 'sender',
                                            })}
                                            onChange={(item: any) => {
                                                setData(
                                                    'sender',
                                                    item?.id.toString() || '',
                                                );
                                                setSenderBalance(
                                                    item
                                                        ? Number(
                                                              item.balance || 0,
                                                          )
                                                        : null,
                                                );
                                            }}
                                            isInvalid={!!errors.sender}
                                        />
                                        {senderBalance !== null && (
                                            <div className="mt-1.5 flex items-center gap-2 rounded-lg border border-rose-100 bg-rose-50 px-3 py-1.5 dark:border-rose-900/30 dark:bg-rose-900/20">
                                                <span className="text-[10px] font-bold tracking-wider text-rose-600 uppercase dark:text-rose-400">
                                                    Current Balance
                                                </span>
                                                <span className="font-mono text-sm font-bold text-rose-700 dark:text-rose-300">
                                                    Rp{' '}
                                                    {senderBalance.toLocaleString()}
                                                </span>
                                            </div>
                                        )}
                                        {errors.sender && (
                                            <p className="text-xs text-destructive">
                                                {errors.sender}
                                            </p>
                                        )}
                                    </div>
                                </div>
                            </div>

                            <div className="mt-8 space-y-6 border-t pt-8">
                                <div className="grid grid-cols-1 gap-6 md:grid-cols-3">
                                    <div className="space-y-2 md:col-span-2">
                                        <Label
                                            htmlFor="description"
                                            className="flex items-center gap-2"
                                        >
                                            <Info className="h-4 w-4 text-zinc-400" />
                                            Note
                                        </Label>
                                        <Textarea
                                            id="description"
                                            placeholder="Brief explanation for this adjustment..."
                                            className="h-20 resize-none"
                                            value={data.description}
                                            onChange={(e) =>
                                                setData(
                                                    'description',
                                                    e.target.value,
                                                )
                                            }
                                        />
                                    </div>

                                    <div className="space-y-2">
                                        <Label htmlFor="total">
                                            Total Amount
                                        </Label>
                                        <div className="relative">
                                            <span className="absolute top-1/2 left-3 -translate-y-1/2 text-sm font-medium text-zinc-500">
                                                Rp
                                            </span>
                                            <Input
                                                id="total"
                                                type="number"
                                                className={`pl-10 text-lg font-bold ${errors.total ? 'border-destructive' : ''}`}
                                                value={
                                                    data.total === 0
                                                        ? ''
                                                        : data.total
                                                }
                                                onChange={(e) =>
                                                    setData(
                                                        'total',
                                                        parseFloat(
                                                            e.target.value,
                                                        ) || 0,
                                                    )
                                                }
                                                placeholder="0"
                                            />
                                        </div>
                                        {errors.total && (
                                            <p className="text-xs text-destructive">
                                                {errors.total}
                                            </p>
                                        )}
                                    </div>
                                </div>
                            </div>

                            <div className="mt-8 flex flex-col items-center justify-between gap-4 rounded-xl border bg-zinc-50 p-4 sm:flex-row dark:bg-zinc-900/50">
                                <div className="flex max-w-md items-start gap-3 text-zinc-600 dark:text-zinc-400">
                                    <AlertCircle className="mt-0.5 h-5 w-5 flex-shrink-0 text-amber-500" />
                                    <span className="text-xs leading-relaxed">
                                        Important: At least one side must be a{' '}
                                        <b>Journal Account</b>. Adjustments
                                        between two Journal Accounts or using
                                        Warehouse entities are not permitted.
                                    </span>
                                </div>
                                <Button
                                    type="submit"
                                    disabled={processing}
                                    size="lg"
                                    className="h-12 bg-indigo-600 px-10 font-bold text-white shadow-md transition-all hover:bg-indigo-700 hover:shadow-lg"
                                >
                                    {processing ? (
                                        'Saving...'
                                    ) : (
                                        <>
                                            <Save className="mr-2 h-5 w-5" />{' '}
                                            Save Adjust
                                        </>
                                    )}
                                </Button>
                            </div>
                        </CardContent>
                    </Card>
                </form>
            </div>
        </AppLayout>
    );
}
