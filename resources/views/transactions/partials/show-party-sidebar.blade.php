@php
    $sideStatus = $sideStatus ?? null;
@endphp
<div class="rounded-xl border border-gray-200 bg-white shadow-sm">
    <div class="border-b border-gray-100 px-5 py-4">
        <h2 class="text-base font-semibold text-gray-900">{{ $title }}</h2>
    </div>
    <div class="px-5 py-4">
        @if($party)
            <dl class="space-y-3 text-sm">
                <div>
                    <dt class="text-gray-500">Name</dt>
                    <dd class="mt-0.5 font-medium text-gray-900">
                        <a href="{{ route('addrbook.type.show', ['type' => $party->type_slug, 'addrbook' => $party->id]) }}"
                           class="text-blue-600 hover:underline">{{ $party->name }}</a>
                    </dd>
                </div>
                <div>
                    <dt class="text-gray-500">Type</dt>
                    <dd class="mt-0.5 text-gray-900">{{ $party->type_name }}</dd>
                </div>
                @if($party->phone)
                <div>
                    <dt class="text-gray-500">Phone</dt>
                    <dd class="mt-0.5 text-gray-900">{{ $party->phone }}</dd>
                </div>
                @endif
                @if($party->address)
                <div>
                    <dt class="text-gray-500">Address</dt>
                    <dd class="mt-0.5 whitespace-pre-line text-gray-900">{{ $party->address }}</dd>
                </div>
                @endif
                @if($sideStatus && $sideStatus['jubelioLocation'])
                <div>
                    <dt class="text-gray-500">Jubelio</dt>
                    <dd class="mt-0.5 text-gray-900">
                        @if($sideStatus['submitted'])
                            <span class="text-green-700">Synced</span>
                        @elseif($sideStatus['needsSync'])
                            <span class="text-amber-700">Pending sync</span>
                        @else
                            <span class="text-gray-600">Mapped</span>
                        @endif
                        <span class="text-gray-500"> · {{ $sideStatus['jubelioLocation'] }}</span>
                    </dd>
                </div>
                @endif
            </dl>
        @else
            <p class="text-sm italic text-gray-400">{{ $emptyText ?? 'No information' }}</p>
        @endif
    </div>
</div>
