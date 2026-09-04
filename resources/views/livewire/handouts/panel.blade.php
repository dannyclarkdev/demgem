{{--
    What the party has been handed, and for a GM, what they could hand over next.

    A hidden handout is only ever in this list for a GM: Entity::visibleTo() is the
    filter, so a player's copy never loaded the row.
--}}
<div wire:poll.visible.{{ $pollSeconds }}s x-data="{ src: '', caption: '' }">
    @if ($handouts->isEmpty())
        <x-ui.empty-state
            icon="paperclip"
            title="{{ $canManage ? 'No handouts yet' : 'Nothing handed over' }}"
            :description="$canManage
                ? 'The letter, the map fragment, the ledger page. Write it up, attach the scan, and drop it on the table when the party earns it.'
                : 'When the GM hands something over, it turns up here.'"
        />
    @else
        <ul class="divide-y divide-line">
            @foreach ($handouts as $handout)
                @php($tiles = $handout->files())
                <li wire:key="handout-{{ $handout->id }}" class="flex flex-wrap items-start gap-3 py-3 first:pt-0 last:pb-0">
                    @if ($tiles->isNotEmpty() && $tiles->first()->hasGeneratedConversion('tile'))
                        <button
                            type="button"
                            class="size-14 shrink-0 overflow-hidden rounded border border-line"
                            @click="src = @js($tiles->first()->getUrl()); caption = @js($handout->name); $dispatch('open-modal', { name: 'handout-panel-file' })"
                            aria-label="Open {{ $handout->name }} full size"
                        >
                            <img src="{{ $tiles->first()->getUrl('tile') }}" alt="" class="size-full object-cover">
                        </button>
                    @else
                        <span class="inline-flex size-14 shrink-0 items-center justify-center rounded border border-line bg-raised text-ink-faint">
                            <x-ui.icon name="paperclip" class="size-5" />
                        </span>
                    @endif

                    <div class="min-w-0 flex-1">
                        <a href="{{ $handout->url() }}" class="block truncate font-medium text-ink hover:text-ember">{{ $handout->name }}</a>
                        <p class="text-xs text-ink-faint">
                            {{ $tiles->count() }} {{ Str::plural('file', $tiles->count()) }}
                            @if ($canManage)
                                &middot; {{ $handout->visibility->label() }}
                            @endif
                        </p>
                    </div>

                    @if ($canManage)
                        <div class="flex shrink-0 items-center gap-1">
                            @if ($handout->visibility === \App\Enums\Visibility::Dm)
                                <x-ui.button size="sm" icon="eye" wire:click="reveal('{{ $handout->id }}')">Show the party</x-ui.button>
                            @else
                                <x-ui.button size="sm" variant="secondary" icon="eye-off" wire:click="takeBack('{{ $handout->id }}')">Take it back</x-ui.button>
                            @endif
                        </div>
                    @endif
                </li>
            @endforeach
        </ul>

        <x-ui.modal name="handout-panel-file" max-width="max-w-5xl">
            <img :src="src" :alt="caption" class="max-h-[75vh] w-full rounded-md object-contain">
            <p class="mt-3 text-center text-xs text-ink-faint" x-text="caption"></p>
        </x-ui.modal>
    @endif
</div>
