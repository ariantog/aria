@extends('layouts.app')

@section('title', 'Edit '.$entity->name)

@section('content')
<div class="mx-auto max-w-3xl flex flex-col gap-4 p-4">
    <div class="flex items-center gap-3">
        <a href="{{ route('reports.entities.index') }}" class="text-gray-500 hover:text-gray-700">← Entities</a>
        <h2 class="text-xl font-bold text-gray-900">{{ $entity->name }}</h2>
    </div>

    <form method="POST" action="{{ route('reports.entities.update', $entity) }}" class="space-y-6">
        @csrf
        @method('PUT')

        @if($errors->any())
            <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                <p class="font-medium">Could not save entity.</p>
                <ul class="mt-2 list-disc space-y-1 pl-5">
                    @foreach($errors->all() as $message)
                        <li>{{ $message }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm space-y-4">
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700">Name</label>
                    <input type="text" name="name" value="{{ old('name', $entity->name) }}" required class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700">Slug</label>
                    <input type="text" name="slug" value="{{ old('slug', $entity->slug) }}" required class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700">NPWP</label>
                    <input type="text" name="npwp" value="{{ old('npwp', $entity->npwp) }}" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                </div>
                <div class="flex items-center gap-6 pt-6">
                    <label class="flex items-center gap-2 text-sm">
                        <input type="hidden" name="is_pkp" value="0">
                        <input type="checkbox" name="is_pkp" value="1" @checked(old('is_pkp', $entity->is_pkp))>
                        PKP
                    </label>
                    <label class="flex items-center gap-2 text-sm">
                        <input type="hidden" name="is_active" value="0">
                        <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $entity->is_active))>
                        Active
                    </label>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700">Modal (rough)</label>
                    <input type="number" step="0.01" name="modal" value="{{ old('modal', $entity->modal) }}" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700">Laba ditahan awal</label>
                    <input type="number" step="0.01" name="laba_ditahan_awal" value="{{ old('laba_ditahan_awal', $entity->laba_ditahan_awal) }}" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                </div>
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-gray-700">Notes</label>
                <textarea name="notes" rows="2" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">{{ old('notes', $entity->notes) }}</textarea>
            </div>
        </div>

        <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm @error('bank_ids') border-red-300 @enderror">
            <h3 class="mb-1 text-sm font-semibold text-gray-900">Bank accounts</h3>
            <p class="mb-3 text-xs text-gray-500">CashIn to these banks uses this entity's PKP status for tax reporting. Each bank can belong to only one entity.</p>
            @error('bank_ids')
                <div class="mb-3 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-800">
                    @if(is_array($message))
                        <ul class="list-disc space-y-1 pl-5">
                            @foreach($message as $line)
                                <li>{{ $line }}</li>
                            @endforeach
                        </ul>
                    @else
                        {{ $message }}
                    @endif
                </div>
            @enderror
            <div class="grid max-h-64 grid-cols-1 gap-2 overflow-y-auto sm:grid-cols-2">
                @php $selectedBankIds = collect($assignedBankIds)->map(fn ($id) => (int) $id); @endphp
                @foreach($banks as $bank)
                <label class="flex items-center gap-2 rounded-lg border border-gray-200 px-3 py-2 text-sm hover:bg-gray-50">
                    <input type="checkbox" name="bank_ids[]" value="{{ $bank->id }}" @checked($selectedBankIds->contains($bank->id))>
                    <span>{{ $bank->name }}</span>
                </label>
                @endforeach
            </div>
        </div>

        <div class="flex justify-end gap-3">
            <a href="{{ route('reports.entities.index') }}" class="rounded-lg border border-gray-300 px-4 py-2 text-sm">Cancel</a>
            <button type="submit" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">Save</button>
        </div>
    </form>
</div>
@endsection
