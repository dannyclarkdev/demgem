{{--
    Every member, with a lit dot for the ones who have the campaign open. "Three of
    four" is the sentence a GM wants; "three" on its own is not.

    No poll. A presence channel is the only thing that knows this, and if the socket
    is down every dot is honestly unlit.
--}}
<div class="flex flex-wrap items-center gap-x-3 gap-y-1.5 text-sm">
    <span class="text-ink-faint">{{ $hereCount }} of {{ $members->count() }} here</span>

    @foreach ($members as $member)
        @php ($isHere = in_array($member->user_id, $hereIds, true))
        <span class="inline-flex items-center gap-1.5 {{ $isHere ? 'text-ink' : 'text-ink-faint' }}">
            <x-ui.presence-dot :here="$isHere" :label="$isHere ? 'Has the campaign open' : 'Not here'" />
            <span class="truncate">{{ $member->user->name }}</span>
        </span>
    @endforeach
</div>
