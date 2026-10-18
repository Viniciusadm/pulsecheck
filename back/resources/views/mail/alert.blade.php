<x-mail::message>
# {{ $alert->title }}

@if ($alert->summary !== null)
{{ $alert->summary }}

@endif
@foreach ($alert->fields as $label => $value)
**{{ $label }}:** {{ $value }}<br>
@endforeach

{{ config('app.name') }}
</x-mail::message>
