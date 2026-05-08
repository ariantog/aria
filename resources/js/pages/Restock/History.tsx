import { Head } from '@inertiajs/react';
import { format } from 'date-fns';
import Pagination from '@/components/Partial/Pagination';
import { Badge } from '@/components/ui/badge';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Stuff', href: '#' },
    { title: 'Restock', href: '/restock' },
    { title: 'History', href: '#' },
];

export default function RestockHistory({ restock, histories }: any) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`History - ${restock.item?.name}`} />
            <div className="p-4 sm:p-6 lg:p-8">
                <div className="mb-8 text-left">
                    <h1 className="mb-1 text-3xl font-bold tracking-tight text-zinc-900 dark:text-zinc-50">
                        History: {restock.item?.name}
                    </h1>
                    <p className="text-zinc-500 dark:text-zinc-400">
                        Detailed log of all stock movements and updates for <span className="font-medium text-zinc-700 dark:text-zinc-300">{restock.item?.code}</span>.
                    </p>
                </div>

                <div className="overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-800 dark:bg-zinc-900/50">
                    <div className="overflow-x-auto text-left">
                        <table className="w-full text-left text-sm">
                            <thead className="border-b border-zinc-200 bg-zinc-50 text-xs text-zinc-500 uppercase dark:border-zinc-800 dark:bg-zinc-900/50">
                                <tr>
                                    <th className="px-6 py-4 font-bold tracking-wider">Date</th>
                                    <th className="px-6 py-4 font-bold tracking-wider">Step / Action</th>
                                    <th className="px-6 py-4 font-bold tracking-wider text-right">Qty Before</th>
                                    <th className="px-6 py-4 font-bold tracking-wider text-right">Qty Changed</th>
                                    <th className="px-6 py-4 font-bold tracking-wider text-right">Qty After</th>
                                    <th className="px-6 py-4 font-bold tracking-wider">User / Invoice</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-zinc-200 dark:divide-zinc-800/50">
                                {histories.data.length > 0 ? (
                                    histories.data.map((log: any) => (
                                        <tr key={log.id} className="hover:bg-zinc-50 dark:hover:bg-zinc-800/40 transition-colors">
                                            <td className="px-6 py-4 whitespace-nowrap">
                                                <div className="text-zinc-900 dark:text-zinc-100 font-medium">
                                                    {format(new Date(log.date), 'dd MMM yyyy')}
                                                </div>
                                                <div className="text-[10px] text-zinc-500 font-mono">
                                                    {format(new Date(log.created_at), 'HH:mm:ss')}
                                                </div>
                                            </td>
                                            <td className="px-6 py-4">
                                                <div className="flex flex-col gap-1">
                                                    <Badge variant="outline" className="w-fit uppercase text-[9px] px-1 py-0 h-4 font-bold">
                                                        {log.step}
                                                    </Badge>
                                                    <span className="text-xs text-zinc-500 italic">{log.action}</span>
                                                </div>
                                            </td>
                                            <td className="px-6 py-4 text-right font-mono text-zinc-500">{log.qty_before}</td>
                                            <td className={`px-6 py-4 text-right font-mono font-bold ${log.qty_changed >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-600 dark:text-red-400'}`}>
                                                {log.qty_changed > 0 ? `+${log.qty_changed}` : log.qty_changed}
                                            </td>
                                            <td className="px-6 py-4 text-right font-mono text-zinc-900 dark:text-zinc-100">{log.qty_after}</td>
                                            <td className="px-6 py-4 whitespace-nowrap">
                                                <div className="text-zinc-900 dark:text-zinc-100 text-xs font-medium">
                                                    {log.user?.name || 'System'}
                                                </div>
                                                {log.invoice && (
                                                    <div className="text-[10px] text-zinc-500 flex items-center gap-1">
                                                        <span className="opacity-50">Ref:</span> {log.invoice}
                                                    </div>
                                                )}
                                            </td>
                                        </tr>
                                    ))
                                ) : (
                                    <tr>
                                        <td colSpan={6} className="px-6 py-12 text-center text-zinc-400 italic">
                                            No history found for this item.
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                    {histories.total > 0 && (
                        <Pagination 
                            links={histories.links} 
                            from={histories.from} 
                            to={histories.to} 
                            total={histories.total} 
                        />
                    )}
                </div>
            </div>
        </AppLayout>
    );
}
