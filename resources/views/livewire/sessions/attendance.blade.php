<x-ui.card title="Who's coming" :padding="false">
    <x-slot:header>
        @if ($session->scheduled_at !== null)
            <span class="text-xs text-ink-faint">{{ $headcount }}</span>
        @endif
    </x-slot:header>

    @if ($session->scheduled_at === null)
        <p class="px-5 py-4 text-sm text-ink-faint">No date yet.</p>
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

        <ul class="divide-y divide-line">
            @foreach ($members as $member)
                @php($answer = $answers->get($member->user_id)?->rsvp)
                <li wire:key="rsvp-{{ $member->id }}" class="flex items-center gap-3 px-5 py-2.5">
                    <x-ui.avatar :name="$member->user->name" />
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-medium text-ink">{{ $member->user->name }}</p>
                        <p class="text-xs text-ink-faint">{{ $member->role->label() }}</p>
                    </div>
                    @if ($answer !== null)
                        <x-ui.badge :variant="$answer->badgeVariant()" :icon="$answer->icon()">{{ $answer->label() }}</x-ui.badge>
                    @else
                        <span class="text-xs text-ink-faint">Not answered</span>
                    @endif
                </li>
            @endforeach
        </ul>
    @endif
</x-ui.card>
