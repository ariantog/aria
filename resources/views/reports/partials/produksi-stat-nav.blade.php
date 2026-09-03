@php
$statLinks = [
    ['key' => 'potong', 'label' => 'Potong', 'route' => 'reports.produksi-potong'],
    ['key' => 'jahit', 'label' => 'Jahit', 'route' => 'reports.produksi-jahit'],
    ['key' => 'qc', 'label' => 'QC', 'route' => 'reports.produksi-qc'],
    ['key' => 'pritil', 'label' => 'Pritil', 'route' => 'reports.produksi-pritil'],
];
$query = [
    'year' => $filters['year'],
    'month' => $filters['month'] ?? 0,
];
if (! empty($filters['from'])) {
    $query['from'] = $filters['from'];
}
if (! empty($filters['to'])) {
    $query['to'] = $filters['to'];
}
if (($filters['status'] ?? null) !== null && $filters['status'] !== '') {
    $query['status'] = $filters['status'];
}
@endphp
<nav class="flex flex-wrap gap-3 text-sm font-medium">
    @foreach($statLinks as $link)
        @if($current === $link['key'])
            <span class="text-gray-900">{{ $link['label'] }}</span>
        @else
            <a href="{{ route($link['route'], $query) }}" class="text-blue-600 hover:underline">{{ $link['label'] }}</a>
        @endif
    @endforeach
</nav>
