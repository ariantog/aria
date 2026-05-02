import { Head, useForm } from '@inertiajs/react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { index as cronIndex, update as cronUpdate, toggle as cronToggle } from '@/actions/App/Http/Controllers/ScheduledTaskController';
import type { BreadcrumbItem } from '@/types';
import { useState } from 'react';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { toast } from 'sonner';

interface ScheduledTask {
    id: number;
    name: string;
    command: string;
    frequency: string;
    is_active: boolean;
    description: string | null;
    last_run_at: string | null;
}

interface Props {
    tasks: ScheduledTask[];
}

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'System Settings',
        href: '/system-settings',
    },
    {
        title: 'Cron Manager',
        href: '/cron-manager',
    },
];

const FREQUENCY_OPTIONS = [
    { label: 'Every Minute', value: 'everyMinute' },
    { label: 'Every Two Minutes', value: 'everyTwoMinutes' },
    { label: 'Every Five Minutes', value: 'everyFiveMinutes' },
    { label: 'Every Ten Minutes', value: 'everyTenMinutes' },
    { label: 'Every Thirty Minutes', value: 'everyThirtyMinutes' },
    { label: 'Hourly', value: 'hourly' },
    { label: 'Every Two Hours', value: 'everyTwoHours' },
    { label: 'Every Three Hours', value: 'everyThreeHours' },
    { label: 'Every Six Hours', value: 'everySixHours' },
    { label: 'Daily', value: 'daily' },
    { label: 'Weekly', value: 'weekly' },
    { label: 'Monthly', value: 'monthly' },
    { label: 'Quarterly', value: 'quarterly' },
    { label: 'Yearly', value: 'yearly' },
];

export default function CronManager({ tasks }: Props) {
    const [editingTask, setEditingTask] = useState<ScheduledTask | null>(null);

    const { data, setData, patch, processing, errors, reset } = useForm({
        name: '',
        frequency: '',
        is_active: true,
        description: '',
    });

    const handleEdit = (task: ScheduledTask) => {
        setEditingTask(task);
        setData({
            name: task.name,
            frequency: task.frequency,
            is_active: task.is_active,
            description: task.description || '',
        });
    };

    const handleToggle = (task: ScheduledTask) => {
        const toggleRoute = cronToggle(task.id);
        // @ts-ignore - toggle is post
        patch(toggleRoute.url, {
            onSuccess: () => toast.success('Status updated'),
        });
    };

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        if (!editingTask) return;

        const updateRoute = cronUpdate(editingTask.id);
        patch(updateRoute.url, {
            onSuccess: () => {
                setEditingTask(null);
                reset();
                toast.success('Task updated successfully');
            },
        });
    };

    const getFrequencyLabel = (value: string) => {
        return FREQUENCY_OPTIONS.find(opt => opt.value === value)?.label || value;
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Cron Manager" />

            <div className="p-4 sm:p-6 lg:p-8">
                <div className="space-y-6">
                    <Heading
                        title="Cron Manager"
                        description="Manage scheduled tasks and their execution cycles."
                    />

                    <Card className="border-zinc-200 dark:border-zinc-800 rounded-2xl overflow-hidden shadow-sm">
                        <CardHeader className="p-6 border-b border-zinc-100 dark:border-zinc-800 bg-zinc-50/50 dark:bg-zinc-900/50">
                            <CardTitle>Scheduled Tasks</CardTitle>
                            <CardDescription>
                                List of all tasks registered in the system.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="p-0">
                            <div className="overflow-x-auto">
                                <Table>
                                    <TableHeader className="text-xs text-zinc-500 uppercase bg-zinc-50 dark:bg-zinc-950/50 border-b border-zinc-100 dark:border-zinc-800">
                                        <TableRow>
                                            <TableHead className="px-6 py-4 font-bold tracking-wider">Name</TableHead>
                                            <TableHead className="px-6 py-4 font-bold tracking-wider">Frequency</TableHead>
                                            <TableHead className="px-6 py-4 font-bold tracking-wider text-center">Status</TableHead>
                                            <TableHead className="px-6 py-4 font-bold tracking-wider">Last Run</TableHead>
                                            <TableHead className="px-6 py-4 font-bold tracking-wider text-right">Actions</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody className="divide-y divide-zinc-100 dark:divide-zinc-800/50">
                                        {tasks.map((task) => (
                                            <TableRow key={task.id} className="group hover:bg-zinc-50 dark:hover:bg-zinc-800/40 transition-colors">
                                                <TableCell className="px-6 py-4">
                                                    <div className="font-semibold text-zinc-900 dark:text-zinc-100">{task.name}</div>
                                                    <div className="text-xs text-muted-foreground">{task.command}</div>
                                                </TableCell>
                                                <TableCell className="px-6 py-4">
                                                    <span className="inline-flex items-center rounded-full bg-blue-50 dark:bg-blue-900/20 px-2.5 py-0.5 text-xs font-medium text-blue-700 dark:text-blue-300 border border-blue-100 dark:border-blue-800">
                                                        {getFrequencyLabel(task.frequency)}
                                                    </span>
                                                </TableCell>
                                                <TableCell className="px-6 py-4 text-center">
                                                    <Switch 
                                                        checked={task.is_active} 
                                                        onCheckedChange={() => handleToggle(task)}
                                                    />
                                                </TableCell>
                                                <TableCell className="px-6 py-4 text-sm text-zinc-700 dark:text-zinc-300">
                                                    {task.last_run_at ? new Date(task.last_run_at).toLocaleString() : 'Never'}
                                                </TableCell>
                                                <TableCell className="px-6 py-4 text-right">
                                                    <Button variant="outline" size="sm" onClick={() => handleEdit(task)} className="rounded-lg">
                                                        Edit
                                                    </Button>
                                                </TableCell>
                                            </TableRow>
                                        ))}
                                    </TableBody>
                                </Table>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <Dialog open={!!editingTask} onOpenChange={(open) => !open && setEditingTask(null)}>
                    <DialogContent className="sm:max-w-[425px] rounded-2xl">
                        <DialogHeader>
                            <DialogTitle>Edit Task: {editingTask?.name}</DialogTitle>
                            <DialogDescription>
                                Update the name and execution schedule for this task.
                            </DialogDescription>
                        </DialogHeader>
                        <form onSubmit={submit} className="space-y-4 pt-4">
                            <div className="space-y-2">
                                <Label htmlFor="name">Name</Label>
                                <Input 
                                    id="name" 
                                    value={data.name} 
                                    onChange={e => setData('name', e.target.value)} 
                                    className="rounded-xl"
                                />
                                {errors.name && <div className="text-xs text-destructive">{errors.name}</div>}
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="frequency">Schedule Frequency</Label>
                                <Select 
                                    value={data.frequency} 
                                    onValueChange={value => setData('frequency', value)}
                                >
                                    <SelectTrigger className="rounded-xl">
                                        <SelectValue placeholder="Select frequency" />
                                    </SelectTrigger>
                                    <SelectContent className="rounded-xl max-h-48">
                                        {FREQUENCY_OPTIONS.map(opt => (
                                            <SelectItem key={opt.value} value={opt.value}>
                                                {opt.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                {errors.frequency && <div className="text-xs text-destructive">{errors.frequency}</div>}
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="description">Description</Label>
                                <Input 
                                    id="description" 
                                    value={data.description} 
                                    onChange={e => setData('description', e.target.value)} 
                                    className="rounded-xl"
                                />
                            </div>
                            <DialogFooter className="pt-4">
                                <Button type="button" variant="ghost" onClick={() => setEditingTask(null)} className="rounded-xl">Cancel</Button>
                                <Button type="submit" disabled={processing} className="bg-blue-600 hover:bg-blue-700 text-white rounded-xl shadow-lg shadow-blue-500/20">Save Changes</Button>
                            </DialogFooter>
                        </form>
                    </DialogContent>
                </Dialog>
            </div>
        </AppLayout>
    );
}
