@props([
    'value',
    'testid' => null,
    'label' => 'Copy',
    'showLabel' => false,
])

<span x-data="{
    copied: false,
    timer: null,
    async copy() {
        if (! await ariaCopyText(@js($value))) {
            return;
        }
        this.copied = true;
        clearTimeout(this.timer);
        this.timer = setTimeout(() => { this.copied = false; }, 2000);
    },
}" class="inline-flex">
    <button type="button"
            @click="copy()"
            @if($testid) data-testid="{{ $testid }}" @endif
            :title="copied ? 'Copied!' : @js($label)"
            class="inline-flex items-center gap-1 rounded border border-gray-300 bg-white px-1.5 py-0.5 text-[10px] font-medium text-gray-600 hover:bg-gray-50">
        <svg x-show="!copied" class="h-3 w-3 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>
        </svg>
        <svg x-show="copied" x-cloak class="h-3 w-3 shrink-0 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
        </svg>
        @if($showLabel)
            <span x-text="copied ? 'Copied' : @js($label)"></span>
        @endif
    </button>
</span>
