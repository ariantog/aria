<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePostRequest;
use App\Http\Requests\UpdatePostRequest;
use App\Models\Post;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class PostController extends Controller
{
    public function index()
    {
        // Simple authorization check (or use middleware)
        // Gate::authorize(Post::getPermissions()['view']);

        return Inertia::render('Posts/Index', [
            'posts' => Post::with('user')->latest()->get(),
            'can' => [
                'create_post' => auth()->user()->can(Post::getPermissions()['create']),
            ],
        ]);
    }

    public function create()
    {
        Gate::authorize(Post::getPermissions()['create']);

        return Inertia::render('Posts/Create');
    }

    public function store(StorePostRequest $request)
    {
        Gate::authorize(Post::getPermissions()['create']);

        $request->user()->posts()->create($request->validated());

        return redirect()->route('posts.index')->with('success', 'Post created successfully.');
    }

    public function edit(Post $post)
    {
        Gate::authorize(Post::getPermissions()['edit']);

        return Inertia::render('Posts/Edit', [
            'post' => $post,
        ]);
    }

    public function update(UpdatePostRequest $request, Post $post)
    {
        Gate::authorize(Post::getPermissions()['edit']);

        $post->update($request->validated());

        return redirect()->route('posts.index')->with('success', 'Post updated successfully.');
    }

    public function destroy(Post $post)
    {
        Gate::authorize(Post::getPermissions()['delete']);

        $post->delete();

        return redirect()->route('posts.index')->with('success', 'Post deleted successfully.');
    }
}
