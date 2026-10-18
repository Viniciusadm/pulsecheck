<x-mail::message>
# {{ $alert->title }}

@if ($alert->summary !== null)
{{ $alert->summary }}

@endif
@foreach ($alert->fields as $label => $value)
**{{ $label }}:** {{ $value }}<br>
@endforeach

{{ $instanceName }}
</x-mail::message>
