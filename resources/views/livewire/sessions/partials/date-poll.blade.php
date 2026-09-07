{{-- The poll: candidate times as columns, members as rows, a tick where a member can make it. --}}
@php
    $timezone = $campaign->timezone;
    $me = auth()->id();
@endphp
@if ($options->isEmpty())
    <p class="px-5 py-4 text-sm text-ink-faint">
        {{ $canPoll ? 'No date yet. Offer the party some times and they will tick the ones they can make.' : 'No date yet. The GM has not offered any times to choose from.' }}
    </p>
@else
    <div class="overflow-x-auto">
        <table class="w-full min-w-max text-sm">
            <thead>
                <tr class="border-b border-line text-left">
                    <th scope="col" class="px-5 py-2 text-xs font-medium text-ink-faint">Who</th>
                    @foreach ($options as $option)
                        @php($when = $option->starts_at->copy()->setTimezone($timezone))
                        <th scope="col" class="px-3 py-2 text-center font-medium text-ink" wire:key="option-head-{{ $option->id }}">
                            <span class="block">{{ $when->format('D j M') }}</span>
                            <span class="block text-xs font-normal text-ink-muted">{{ $when->format('H:i') }} {{ $when->format('T') }}</span>
                            <span class="mt-1 block text-xs font-normal text-ink-faint">{{ $option->votes->count() }} can make it</span>
                        </th>
                    @endforeach
                </tr>
            </thead>
            <tbody class="divide-y divide-line">
                @foreach ($members as $member)
                    <tr wire:key="poll-row-{{ $member->id }}" @class(['bg-raised/40' => $member->user_id === $me])>
                        <td class="px-5 py-2">
                            <span class="font-medium text-ink">{{ $member->user->name }}</span>
                            @if ($member->user_id === $me)<span class="ml-1 text-xs text-ink-faint">you</span>@endif
                        </td>
                        @foreach ($options as $option)
                            @php($ticked = $option->votes->contains('user_id', $member->user_id))
                            <td class="px-3 py-2 text-center" wire:key="poll-cell-{{ $member->id }}-{{ $option->id }}">
                                @if ($member->user_id === $me && $canVote)
                                    <input
                                        type="checkbox"
                                        class="size-4 rounded border-line-strong bg-canvas text-ember focus:ring-ember/30"
                                        @if ($ticked) checked @endif
                                        wire:click="toggleDateVote('{{ $option->id }}')"
                                        aria-label="I can make {{ $option->starts_at->copy()->setTimezone($timezone)->format('D j M H:i') }}"
                                    >
                                @elseif ($ticked)
                                    <x-ui.icon name="check" class="mx-auto size-4 text-success" />
                                @else
                                    <span class="text-ink-faint">·</span>
                                @endif
                            </td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
            @if ($canPoll)
                <tfoot>
                    <tr class="border-t border-line">
                        <td class="px-5 py-2 text-xs text-ink-faint">Pick one</td>
                        @foreach ($options as $option)
                            <td class="px-3 py-2 text-center" wire:key="option-foot-{{ $option->id }}">
                                <div class="flex items-center justify-center gap-1">
                                    @if ($session->scheduled_at === null)
                                        <x-ui.button size="sm" icon="check" wire:click="pickDate('{{ $option->id }}')" wire:confirm="Set {{ $session->label() }} to {{ $option->starts_at->copy()->setTimezone($timezone)->format('D j M \a\t H:i') }} and close the poll?">Pick</x-ui.button>
                                    @endif
                                    <x-ui.button size="icon" variant="ghost" icon="x" wire:click="removeDateOption('{{ $option->id }}')" aria-label="Remove this time" />
                                </div>
                            </td>
                        @endforeach
                    </tr>
                </tfoot>
            @endif
        </table>
    </div>
@endif

@if ($canPoll && $session->scheduled_at === null)
    <form wire:submit="addDateOption" class="flex flex-wrap items-end gap-2 border-t border-line px-5 py-3">
        <x-ui.input label="Offer a time" name="newOption" type="datetime-local" wire:model="newOption" class="min-w-56" :hint="'In '.str_replace('_', ' ', $timezone).'.'" />
        <x-ui.button type="submit" variant="secondary" size="sm" icon="plus" wire:loading.attr="disabled">Add</x-ui.button>
    </form>
@endif
