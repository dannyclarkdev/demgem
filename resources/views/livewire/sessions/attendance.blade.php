<x-ui.card title="Who's coming" :padding="false">
    <x-slot:header>
        @if ($session->scheduled_at !== null)
            <span class="text-xs text-ink-faint">{{ $headcount }}</span>
        @endif
    </x-slot:header>

    @if ($session->scheduled_at !== null && $options->isNotEmpty())
        <div class="flex flex-wrap items-center gap-2 border-b border-line bg-raised/40 px-5 py-3 text-sm">
            <span class="text-ink-muted">This session has a date now. The poll below is what the party said before it did.</span>
            @if ($canPoll)
                <x-ui.button size="sm" variant="ghost" icon="x" wire:click="clearDateOptions" class="ml-auto">Clear the poll</x-ui.button>
            @endif
        </div>
    @endif

    @if ($session->scheduled_at === null || $options->isNotEmpty())
        @include('livewire.sessions.partials.date-poll')
    @endif

    @if ($session->scheduled_at === null)
    @else
        @if ($canRespond)
            <div class="flex flex-wrap items-center gap-2 border-b border-line px-5 py-3">
                <span class="mr-1 text-sm text-ink-muted">Are you coming?</span>
                @foreach (\App\Enums\Rsvp::cases() as $case)
                    <x-ui.button
                        size="sm"
                        :variant="$mine?->rsvp === $case ? 'primary' : 'secondary'"
                        :icon="$case->icon()"
                        wire:click="respond('{{ $case->value }}')"
                        :aria-pressed="$mine?->rsvp === $case ? 'true' : 'false'"
                    >{{ $case->label() }}</x-ui.button>
                @endforeach
                @if ($mine?->rsvp !== null)
                    <x-ui.button size="sm" variant="ghost" wire:click="respond(null)">Clear</x-ui.button>
                @endif
            </div>
        @endif

        @php($played = $session->status === \App\Enums\SessionStatus::Played)

        <ul class="divide-y divide-line">
            @foreach ($members as $member)
                @php($row = $answers->get($member->user_id))
                @php($answer = $row?->rsvp)
                @php($there = $row?->wasThere() ?? false)
                <li wire:key="rsvp-{{ $member->id }}" class="flex items-center gap-3 px-5 py-2.5">
                    @if ($canRecord)
                        <input
                            type="checkbox"
                            class="size-4 rounded border-line-strong bg-canvas text-ember focus:ring-ember/30"
                            id="attended-{{ $member->id }}"
                            @if ($there) checked @endif
                            wire:click="recordAttendance({{ $member->user_id }}, {{ $there ? 'false' : 'true' }})"
                            aria-label="{{ $member->user->name }} was there"
                        >
                    @else
                        <x-ui.avatar :name="$member->user->name" />
                    @endif
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-medium text-ink">{{ $member->user->name }}</p>
                        <p class="text-xs text-ink-faint">{{ $member->role->label() }}</p>
                    </div>
                    @if ($played)
                        @if ($row?->attended !== null || $answer !== null)
                            <x-ui.badge :variant="$there ? 'success' : 'neutral'" :icon="$there ? 'check' : 'minus'">{{ $there ? 'There' : 'Not there' }}</x-ui.badge>
                        @else
                            <span class="text-xs text-ink-faint">Not marked</span>
                        @endif
                    @elseif ($answer !== null)
                        <x-ui.badge :variant="$answer->badgeVariant()" :icon="$answer->icon()">{{ $answer->label() }}</x-ui.badge>
                    @else
                        <span class="text-xs text-ink-faint">Not answered</span>
                    @endif
                </li>
            @endforeach
        </ul>
    @endif
</x-ui.card>
