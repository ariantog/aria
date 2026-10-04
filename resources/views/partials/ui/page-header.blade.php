{{--
    @param string $title
    @param string|null $lead
    Optional slot via $actionsHtml or @slot in future; use $actionsHtml for inline HTML.
--}}
@php
    $lead = $lead ?? null;
    $actionsHtml = $actionsHtml ?? null;
@endphp
<header class="ui-page-header">
    <div class="min-w-0">
        <h1 class="ui-page-title">{{ $title }}</h1>
        @if($lead)
            <p class="ui-page-lead">{{ $lead }}</p>
        @endif
    </div>
    @if($actionsHtml)
        <div class="ui-page-actions">{!! $actionsHtml !!}</div>
    @endif
</header>
