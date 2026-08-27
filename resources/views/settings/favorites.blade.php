@extends('layouts.app')
@section('title', 'Favorite links')

@push('settings-content')
    <div class="space-y-6">
        <header>
            <h2 class="text-base font-medium text-gray-900">Favorite links</h2>
            <p class="text-sm text-gray-500">Pin up to {{ $maxFavorites }} shortcuts in the sidebar above Transactions. Only pages you can access are listed.</p>
        </header>

        @if(session('success'))
            <div class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">{{ session('success') }}</div>
        @endif
        @if(session('error'))
            <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ session('error') }}</div>
        @endif

        <form method="POST" action="{{ route('favorites.update') }}" class="space-y-6">
            @csrf
            @method('PUT')

            <section class="space-y-4 rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                @for($i = 0; $i < $maxFavorites; $i++)
                    <div>
                        <label for="favorite-slot-{{ $i + 1 }}" class="mb-1 block text-sm font-medium text-gray-700">
                            Favorite {{ $i + 1 }}
                        </label>
                        <select id="favorite-slot-{{ $i + 1 }}"
                                name="favorites[]"
                                class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-blue-500">
                            <option value="">— None —</option>
                            @foreach($availableGroups as $groupName => $links)
                                <optgroup label="{{ $groupName }}">
                                    @foreach($links as $link)
                                        <option value="{{ $link['key'] }}" @selected(old('favorites.'.$i, $slots[$i]) === $link['key'])>
                                            {{ $link['label'] }}
                                        </option>
                                    @endforeach
                                </optgroup>
                            @endforeach
                        </select>
                        @error('favorites.'.$i)<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    </div>
                @endfor
            </section>

            @error('favorites')<p class="text-sm text-red-600">{{ $message }}</p>@enderror

            <div class="flex items-center gap-4">
                <button type="submit"
                        class="rounded-md bg-blue-700 px-4 py-2 text-sm font-medium text-white hover:bg-blue-800">
                    Save favorites
                </button>
            </div>
        </form>
    </div>
@endpush

@section('content')
    @php
        $breadcrumbs = [
            ['title' => 'Favorite links', 'href' => route('favorites.edit')],
        ];
    @endphp

    @include('settings.partials.nav')
@endsection
