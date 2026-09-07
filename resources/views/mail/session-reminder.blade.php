<x-mail::message>
# {{ $sessionLabel }}@if ($sessionTitle): {{ $sessionTitle }}@endif

**{{ $campaignName }}** plays on {{ $when }}.

Let the GM know whether you are coming.

<x-mail::button :url="$sessionUrl">
Open the session
</x-mail::button>

Turn these off from the campaign's [members page]({{ $membersUrl }}).

{{ config('app.name') }}
</x-mail::message>
