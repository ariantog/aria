@if ($errors->any())
<div class="rounded-xl border border-red-200 bg-red-50 px-5 py-4 text-sm text-red-800" role="alert" data-testid="form-errors">
    <p class="font-semibold">Please fix the following before submitting again:</p>
    <ul class="mt-2 list-inside list-disc space-y-1">
        @foreach ($errors->all() as $error)
        <li>{{ is_array($error) ? implode(', ', $error) : $error }}</li>
        @endforeach
    </ul>
    <p class="mt-3 text-xs text-red-600">Your entries below have been kept — adjust the highlighted fields and resubmit.</p>
</div>
@endif
