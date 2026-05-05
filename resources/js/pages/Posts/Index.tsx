import { Head, Link, router } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { BreadcrumbItem } from '@/types';
import { Button } from '@/components/ui/button';
import postRoutes from '@/routes/posts';
import Can from '@/components/Can';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Posts',
        href: '/posts',
    },
];

interface Post {
    id: number;
    title: string;
    content: string;
    user: {
        name: string;
    };
    created_at: string;
}

interface Props {
    posts: Post[];
    can: {
        create_post: boolean;
    };
}

export default function PostsIndex({ posts, can }: Props) {
    const handleDelete = (id: number) => {
        if (confirm('Are you sure you want to delete this post?')) {
            router.delete(postRoutes.destroy.url({ post: id }));
        }
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Posts" />

            <div className="p-6">
                <div className="mb-6 flex items-center justify-between">
                    <h2 className="text-xl font-semibold text-gray-800 dark:text-gray-200">
                        Posts
                    </h2>

                    <Can permission="posts-create">
                        <Link href={postRoutes.create.url()}>
                            <Button>Create New Post</Button>
                        </Link>
                    </Can>
                </div>

                <div className="overflow-hidden rounded-lg bg-white shadow dark:bg-gray-800">
                    <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead className="bg-gray-50 dark:bg-gray-700">
                            <tr>
                                <th
                                    scope="col"
                                    className="px-6 py-3 text-left text-xs font-medium tracking-wider text-gray-500 uppercase dark:text-gray-300"
                                >
                                    Title
                                </th>
                                <th
                                    scope="col"
                                    className="px-6 py-3 text-left text-xs font-medium tracking-wider text-gray-500 uppercase dark:text-gray-300"
                                >
                                    Author
                                </th>
                                <th
                                    scope="col"
                                    className="px-6 py-3 text-left text-xs font-medium tracking-wider text-gray-500 uppercase dark:text-gray-300"
                                >
                                    Created At
                                </th>
                                <th
                                    scope="col"
                                    className="px-6 py-3 text-right text-xs font-medium tracking-wider text-gray-500 uppercase dark:text-gray-300"
                                >
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-200 bg-white dark:divide-gray-700 dark:bg-gray-800">
                            {posts.map((post) => (
                                <tr key={post.id}>
                                    <td className="px-6 py-4 text-sm font-medium whitespace-nowrap text-gray-900 dark:text-gray-100">
                                        {post.title}
                                    </td>
                                    <td className="px-6 py-4 text-sm whitespace-nowrap text-gray-500 dark:text-gray-400">
                                        {post.user.name}
                                    </td>
                                    <td className="px-6 py-4 text-sm whitespace-nowrap text-gray-500 dark:text-gray-400">
                                        {new Date(
                                            post.created_at,
                                        ).toLocaleDateString()}
                                    </td>
                                    <td className="px-6 py-4 text-right text-sm font-medium whitespace-nowrap">
                                        <Can permission="posts-edit">
                                            <Link
                                                href={postRoutes.edit.url({
                                                    post: post.id,
                                                })}
                                                className="mr-4 text-indigo-600 hover:text-indigo-900 dark:text-indigo-400"
                                            >
                                                Edit
                                            </Link>
                                        </Can>
                                        <Can permission="posts-delete">
                                            <button
                                                onClick={() =>
                                                    handleDelete(post.id)
                                                }
                                                className="text-red-600 hover:text-red-900 dark:text-red-400"
                                            >
                                                Delete
                                            </button>
                                        </Can>
                                    </td>
                                </tr>
                            ))}
                            {posts.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={4}
                                        className="px-6 py-4 text-center text-sm text-gray-500 dark:text-gray-400"
                                    >
                                        No posts found.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
            </div>
        </AppLayout>
    );
}
