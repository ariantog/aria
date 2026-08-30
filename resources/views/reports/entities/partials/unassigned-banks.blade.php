@if($unassignedBanks->isNotEmpty())
    <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-950" data-testid="entities-unassigned-banks">
        <p class="font-medium">Unassigned operating banks</p>
        <p class="mt-1 text-xs text-amber-800">These banks are active in reports but are not mapped to a reporting entity. CashIn to them will not appear on tax or entity reports. Assign them on an entity edit page.</p>
        <ul class="mt-2 flex flex-wrap gap-2">
            @foreach($unassignedBanks as $bank)
                <li>
                    <a
                        href="{{ route('addrbook.type.edit', ['type' => 'bank', 'addrbook' => $bank]) }}"
                        class="inline-flex items-center rounded-full bg-white px-2.5 py-1 text-xs font-medium text-amber-900 ring-1 ring-amber-200 hover:bg-amber-100"
                    >
                        {{ $bank->name }}
                    </a>
                </li>
            @endforeach
        </ul>
        @if($activeEntities->isNotEmpty())
            <p class="mt-2 text-xs text-amber-800">
                Assign via
                @foreach($activeEntities as $entity)
                    <a href="{{ route('reports.entities.edit', $entity) }}" class="font-medium underline hover:no-underline">{{ $entity->name }}</a>@if(! $loop->last), @endif
                @endforeach
            </p>
        @endif
    </div>
@endif
