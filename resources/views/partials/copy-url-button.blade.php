@props([
    'url',
    'testid' => null,
    'label' => 'Copy link',
])

<span x-data="{
    copied: false,
    timer: null,
    async copy() {
        if (! await ariaCopyText(@js($url))) {
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
            class="inline-flex items-center rounded border border-gray-300 bg-white px-1.5 py-0.5 text-[10px] font-medium text-gray-600 hover:bg-gray-50">
        <svg x-show="!copied" class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/>
        </svg>
        <svg x-show="copied" x-cloak class="h-3 w-3 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
        </svg>
        <span x-show="copied" x-cloak class="ml-1 text-green-700">Copied</span>
    </button>
</span>
