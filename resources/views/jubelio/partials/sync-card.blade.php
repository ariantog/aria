@php
    $isSubmitted = !empty($submittedBy);
    $isDeduct = $type == 2;
    $hasWarning = !empty($warning);
@endphp
@if(!$needsSync)
    <div class="rounded-xl border border-gray-200 bg-gray-50 p-6 opacity-50 shadow-sm">
        <h3 class="mb-4 flex items-center gap-2 text-xs font-bold uppercase text-gray-400">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            {{ $title }}
        </h3>
        <div class="flex items-center justify-between">
            <span class="text-xs font-medium">{{ $whName }}</span>
            <span class="inline-flex rounded bg-gray-100 px-2 py-0.5 text-[9px] font-bold uppercase text-gray-600">System Completed</span>
        </div>
    </div>
@else
    @php
        $theme = $isSubmitted ? 'green' : ($hasWarning ? 'yellow' : ($role === 'sender' ? 'zinc' : 'blue'));
        $themeBorder = ['green' => 'border-green-500/30 bg-green-50', 'yellow' => 'border-yellow-500/30 bg-yellow-50', 'blue' => 'border-blue-500/30 bg-blue-50', 'zinc' => 'border-gray-400/30 bg-gray-50'][$theme];
        $themeText = ['green' => 'text-green-600', 'yellow' => 'text-yellow-700', 'blue' => 'text-blue-600', 'zinc' => 'text-gray-600'][$theme];
    @endphp
    <div class="rounded-xl border p-6 shadow-sm {{ $themeBorder }}">
        <div class="mb-4 flex items-center justify-between">
            <h3 class="flex items-center gap-2 font-bold {{ $themeText }}">
                @if($isSubmitted)
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                @else
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/></svg>
                @endif
                {{ $title }}
            </h3>
            @if($isSubmitted)
            <span class="inline-flex rounded border border-green-500/30 px-2 py-0.5 text-[9px] font-bold uppercase text-green-600">Synced</span>
            @elseif($hasWarning)
            <span class="inline-flex rounded border border-yellow-500/30 px-2 py-0.5 text-[9px] font-bold uppercase text-yellow-700">Warning</span>
            @endif
        </div>

        <div class="mb-6 space-y-3">
            <div class="flex justify-between text-xs">
                <span class="text-[9px] font-bold uppercase text-gray-400">Internal WH</span>
                <span class="font-medium">{{ $whName }}</span>
            </div>
            <div class="flex justify-between text-xs">
                <span class="text-[9px] font-bold uppercase text-gray-400">Jubelio WH</span>
                <span class="ml-4 text-right font-medium">{{ $jubName ?: 'Not Linked' }}</span>
            </div>
            <div class="flex justify-between border-t border-gray-200 pt-2 text-xs">
                <span class="text-[9px] font-bold uppercase text-gray-400">Adjustment</span>
                <span class="font-bold {{ $isDeduct ? 'text-red-500' : 'text-green-500' }}">{{ $isDeduct ? '-' : '+' }}{{ $qty }} Items</span>
            </div>
        </div>

        @if($isSubmitted)
        <div class="text-[10px] text-gray-500">
            <p>Synced by <span class="font-bold">{{ $submittedBy }}</span></p>
            <p class="mt-0.5 font-mono">Ref: {{ $referenceId ?: 'N/A' }}</p>
        </div>
        @elseif($hasWarning)
        <div class="space-y-3">
            <p class="text-xs text-yellow-800">
                Push ke Jubelio sudah dicoba tetapi status tidak jelas. Konfirmasi jika berhasil di Jubelio, atau hapus peringatan untuk coba lagi.
            </p>
            <form method="POST" action="{{ route('jubelio.transaction.sync-confirm', ['transaction' => $transactionId]) }}" class="space-y-2">
                @csrf
                <input type="hidden" name="side" value="{{ $side }}">
                <input type="text" name="reference_id" placeholder="Reference ID (opsional)"
                       class="w-full rounded-md border border-yellow-300 px-2 py-1 text-xs">
                <button type="submit" class="h-8 w-full rounded-lg bg-yellow-600 text-xs font-bold uppercase text-white hover:bg-yellow-700">
                    Konfirmasi Berhasil
                </button>
            </form>
            <form method="POST" action="{{ route('jubelio.transaction.sync-clear', ['transaction' => $transactionId]) }}">
                @csrf
                <input type="hidden" name="side" value="{{ $side }}">
                <button type="submit" class="h-8 w-full rounded-lg border border-yellow-400 bg-white text-xs font-bold uppercase text-yellow-800 hover:bg-yellow-50">
                    Hapus Peringatan
                </button>
            </form>
        </div>
        @else
        <button type="button"
                @click="openConfirm({{ $side }}, {{ $whType }}, {{ $type }}, @js($whName))"
                @if(!$jubName || $disabled) disabled @endif
                class="h-9 w-full rounded-lg bg-blue-700 text-xs font-bold uppercase text-white hover:bg-blue-800 disabled:cursor-not-allowed disabled:opacity-40">
            Push to Jubelio
        </button>
        @endif
    </div>
@endif
