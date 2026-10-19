<x-mail::message>
# {{ __('Test email') }}

{{ __('If you are reading this, the mail settings of :name are working.', ['name' => $instanceName]) }}

{{ $instanceName }}
</x-mail::message>
