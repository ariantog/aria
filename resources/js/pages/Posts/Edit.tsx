
import { Head, useForm, Link } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { BreadcrumbItem } from '@/types';
import postRoutes from '@/routes/posts';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Posts', href: '/posts' },
    { title: 'Edit Post', href: '#' },
];

interface Post {
    id: number;
    title: string;
    content: string;
}

interface Props {
    post: Post;
}

export default function PostsEdit({ post }: Props) {
    const { data, setData, put, processing, errors } = useForm({
        title: post.title,
        content: post.content,
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        put(postRoutes.update.url({ post: post.id }));
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Edit Post: ${post.title}`} />

            <div className="p-6 max-w-2xl mx-auto">
                <div className="mb-6 flex items-center justify-between">
                    <h2 className="text-xl font-semibold text-gray-800 dark:text-gray-200">Edit Post</h2>
                    <Link href={postRoutes.index.url()} className="text-gray-600 dark:text-gray-400 hover:underline">
                        Cancel
                    </Link>
                </div>

                <div className="bg-white dark:bg-gray-800 shadow rounded-lg p-6">
                    <form onSubmit={submit} className="space-y-6">
                        <div>
                            <Label htmlFor="title">Title</Label>
                            <Input
                                type="text"
                                id="title"
                                value={data.title}
                                onChange={e => setData('title', e.target.value)}
                                className="mt-1"
                            />
                            {errors.title && <div className="text-red-500 text-sm mt-1">{errors.title}</div>}
                        </div>

                        <div>
                            <Label htmlFor="content">Content</Label>
                            <textarea
                                id="content"
                                value={data.content}
                                onChange={e => setData('content', e.target.value)}
                                className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:bg-gray-900 dark:border-gray-700 dark:text-gray-300 sm:text-sm min-h-[150px] p-2"
                            />
                            {errors.content && <div className="text-red-500 text-sm mt-1">{errors.content}</div>}
                        </div>

                        <div className="flex justify-end">
                            <Button type="submit" disabled={processing}>Update Post</Button>
                        </div>
                    </form>
                </div>
            </div>
        </AppLayout>
    );
}
