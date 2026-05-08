import { Head, useForm } from '@inertiajs/react';
import { ArrowRightLeft, Calendar, FileText, Info, Save } from 'lucide-react';
import React from 'react';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';

interface Bank {
    id: number;
    name: string;
}

interface Props {
    bankList: Bank[];
    min_date?: string;
}

export default function Transfer({ bankList, min_date }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Transactions', href: '/transactions' },
        { title: 'Transfer', href: '/transactions/transfer' },
    ];

    const { data, setData, post, processing, errors } = useForm({
        date: min_date || new Date().toISOString().split('T')[0],
        sender: '',
        receiver: '',
        invoice: '',
        description: '',
        total: 0,
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post('/transactions/transfer');
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Transfer Money" />

            <div className="p-4 sm:p-6 lg:p-8">
                <div className="mb-8">
                    <h2 className="text-2xl font-bold tracking-tight text-zinc-900 dark:text-zinc-50">
                        Transfer Money
                    </h2>
                    <p className="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                        Transfer balances between bank accounts.
                    </p>
                </div>

                <form onSubmit={handleSubmit} className="space-y-6">
                    <Card className="border-zinc-200 shadow-sm dark:border-zinc-800">
                        <CardContent className="p-6">
                            <div className="grid grid-cols-1 gap-6 md:grid-cols-2">
                                {/* Date and Invoice */}
                                <div className="space-y-4">
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
                                                    ? 'border-red-500'
                                                    : ''
                                            }
                                        />
                                        {errors.date && (
                                            <p className="text-xs text-red-500">
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
                                            <p className="text-xs text-red-500">
                                                {errors.invoice}
                                            </p>
                                        )}
                                    </div>
                                </div>

                                {/* From and To */}
                                <div className="space-y-4">
                                    <div className="space-y-2">
                                        <Label htmlFor="sender">
                                            From Bank
                                        </Label>
                                        <Select
                                            value={data.sender}
                                            onValueChange={(value) =>
                                                setData('sender', value)
                                            }
                                        >
                                            <SelectTrigger
                                                id="sender"
                                                className={
                                                    errors.sender
                                                        ? 'border-red-500'
                                                        : ''
                                                }
                                            >
                                                <SelectValue placeholder="Choose source account" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {bankList.map((bank) => (
                                                    <SelectItem
                                                        key={bank.id}
                                                        value={bank.id.toString()}
                                                    >
                                                        {bank.name}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                        {errors.sender && (
                                            <p className="text-xs text-red-500">
                                                {errors.sender}
                                            </p>
                                        )}
                                    </div>

                                    <div className="space-y-2">
                                        <Label htmlFor="receiver">
                                            To Bank
                                        </Label>
                                        <Select
                                            value={data.receiver}
                                            onValueChange={(value) =>
                                                setData('receiver', value)
                                            }
                                        >
                                            <SelectTrigger
                                                id="receiver"
                                                className={
                                                    errors.receiver
                                                        ? 'border-red-500'
                                                        : ''
                                                }
                                            >
                                                <SelectValue placeholder="Choose destination account" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {bankList.map((bank) => (
                                                    <SelectItem
                                                        key={bank.id}
                                                        value={bank.id.toString()}
                                                    >
                                                        {bank.name}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                        {errors.receiver && (
                                            <p className="text-xs text-red-500">
                                                {errors.receiver}
                                            </p>
                                        )}
                                    </div>
                                </div>
                            </div>

                            <div className="mt-8 space-y-6 border-t border-zinc-100 pt-8 dark:border-zinc-800">
                                <div className="space-y-2">
                                    <Label htmlFor="total">Total Amount</Label>
                                    <div className="relative">
                                        <span className="absolute top-1/2 left-3 -translate-y-1/2 text-sm font-medium text-zinc-500">
                                            Rp
                                        </span>
                                        <Input
                                            id="total"
                                            type="number"
                                            className={`pl-10 text-lg font-semibold ${errors.total ? 'border-red-500' : ''}`}
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
                                        <p className="text-xs text-red-500">
                                            {errors.total}
                                        </p>
                                    )}
                                </div>

                                <div className="space-y-2">
                                    <Label
                                        htmlFor="description"
                                        className="flex items-center gap-2"
                                    >
                                        <Info className="h-4 w-4 text-zinc-400" />
                                        Note
                                    </Label>
                                    <Textarea
                                        id="description"
                                        placeholder="Add transaction details here..."
                                        className="h-24 resize-none"
                                        value={data.description}
                                        onChange={(e) =>
                                            setData(
                                                'description',
                                                e.target.value,
                                            )
                                        }
                                    />
                                </div>
                            </div>

                            <div className="mt-8 flex items-center justify-between rounded-lg border border-zinc-100 bg-zinc-50 p-4 dark:border-zinc-800 dark:bg-zinc-900/50">
                                <div className="flex items-center gap-3 text-zinc-600 dark:text-zinc-400">
                                    <ArrowRightLeft className="h-5 w-5" />
                                    <span className="text-sm">
                                        Summary: Transfer funds between internal
                                        accounts.
                                    </span>
                                </div>
                                <Button
                                    type="submit"
                                    disabled={processing}
                                    className="h-11 bg-indigo-600 px-8 font-semibold text-white hover:bg-indigo-700"
                                >
                                    {processing ? (
                                        'Processing...'
                                    ) : (
                                        <>
                                            <Save className="mr-2 h-4 w-4" />{' '}
                                            Save Transfer
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
