@php
$monthNames = ['', 'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
$statusOptions = \App\Models\Produksi::reportStatusFilterOptions();
@endphp

<div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
    <form method="GET" action="{{ $action }}" class="flex flex-wrap items-end gap-4">
        <div class="grid w-[180px] gap-1.5">
            <label class="text-sm font-medium" for="month">Month</label>
            <select id="month" name="month" class="rounded-md border border-gray-300 px-2.5 py-1.5 text-sm">
                <option value="0">All Months</option>
                @for($m = 1; $m <= 12; $m++)
                    <option value="{{ $m }}" @selected((string)($filters['month'] ?? '0') === (string)$m)>{{ $monthNames[$m] }}</option>
                @endfor
            </select>
        </div>
        <div class="grid w-[180px] gap-1.5">
            <label class="text-sm font-medium" for="year">Year</label>
            <select id="year" name="year" class="rounded-md border border-gray-300 px-2.5 py-1.5 text-sm">
                @foreach($yearList as $y)
                    <option value="{{ $y }}" @selected((int)$filters['year'] === (int)$y)>{{ $y }}</option>
                @endforeach
            </select>
        </div>
        <div class="grid w-[180px] gap-1.5">
            <label class="text-sm font-medium" for="produksi-report-from">From</label>
            <input type="date" id="produksi-report-from" name="from" value="{{ $filters['from'] ?? '' }}" data-testid="produksi-report-from" class="rounded-md border border-gray-300 px-2.5 py-1.5 text-sm">
        </div>
        <div class="grid w-[180px] gap-1.5">
            <label class="text-sm font-medium" for="produksi-report-to">To</label>
            <input type="date" id="produksi-report-to" name="to" value="{{ $filters['to'] ?? '' }}" data-testid="produksi-report-to" class="rounded-md border border-gray-300 px-2.5 py-1.5 text-sm">
        </div>
        <div class="grid w-[180px] gap-1.5">
            <label class="text-sm font-medium" for="produksi-report-status">Status</label>
            <select id="produksi-report-status" name="status" data-testid="produksi-report-status" class="rounded-md border border-gray-300 px-2.5 py-1.5 text-sm">
                <option value="">All Statuses</option>
                @foreach($statusOptions as $s)
                    <option value="{{ $s['id'] }}" @selected(($filters['status'] ?? null) !== null && (int) $filters['status'] === (int) $s['id'])>{{ $s['name'] }}</option>
                @endforeach
            </select>
        </div>
        <div class="flex gap-2">
            <button type="submit" class="rounded-lg bg-blue-700 px-4 py-1.5 text-sm font-medium text-white hover:bg-blue-800">Filter</button>
            <a href="{{ $action }}" class="rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-sm font-medium text-gray-600 hover:bg-gray-50">Clear</a>
        </div>
    </form>
</div>
