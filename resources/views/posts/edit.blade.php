@extends('layouts.app')

@section('title', 'Edit Post')

@section('content')
@php
$breadcrumbs = [
    ['title' => 'Posts', 'href' => route('posts.index')],
    ['title' => 'Edit Post', 'href' => route('posts.edit', $post->id)],
];
@endphp

<div class="flex flex-col gap-4 p-3 sm:p-4">
    <div>
        <h2 class="text-2xl font-bold tracking-tight text-gray-900">Edit Post</h2>
    </div>

    <div class="max-w-2xl overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
        <form method="POST" action="{{ route('posts.update', $post->id) }}" class="p-6">
            @csrf
            @method('PUT')
            <div class="mb-4">
                <label class="mb-1 block text-sm font-medium text-gray-700">Title <span class="text-red-500">*</span></label>
                <input type="text" name="title" value="{{ old('title', $post->title) }}" required
                       class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-blue-500">
                @error('title')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>
            <div class="mb-6">
                <label class="mb-1 block text-sm font-medium text-gray-700">Content <span class="text-red-500">*</span></label>
                <textarea name="content" rows="8" required
                          class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-blue-500">{{ old('content', $post->content) }}</textarea>
                @error('content')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>
            <div class="flex justify-end gap-3">
                <a href="{{ route('posts.index') }}" class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-600 hover:bg-gray-50">Cancel</a>
                <button type="submit" class="rounded-lg bg-blue-700 px-6 py-2 text-sm font-medium text-white hover:bg-blue-800">Save Changes</button>
            </div>
        </form>
    </div>
</div>
@endsection
