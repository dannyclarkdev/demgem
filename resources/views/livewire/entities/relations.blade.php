<div>
    @if ($outgoing->isNotEmpty() || $incoming->isNotEmpty() || $canManage)
        <x-ui.card title="Relationships" :padding="false">
            @if ($outgoing->isEmpty() && $incoming->isEmpty())
                <p class="px-5 py-4 text-sm text-ink-faint">Nothing yet. Say what this page is to another one: ally of, twin of, hunted by.</p>
            @else
                <ul class="divide-y divide-line">
                    @foreach ($outgoing as $relation)
                        <li wire:key="relation-out-{{ $relation->id }}" class="flex items-center gap-3 px-5 py-2.5 text-sm">
                            <span class="shrink-0 text-ink-muted">{{ $relation->label }}</span>
                            <a href="{{ $relation->target->url() }}" class="min-w-0 flex-1 truncate font-medium text-ink hover:text-ember">{{ $relation->target->name }}</a>
                            @include('livewire.entities.partials.relation-controls', ['relation' => $relation])
                        </li>
                    @endforeach
                    @foreach ($incoming as $relation)
                        <li wire:key="relation-in-{{ $relation->id }}" class="flex items-center gap-3 px-5 py-2.5 text-sm">
                            @if ($relation->reverse_label !== null)
                                <span class="shrink-0 text-ink-muted">{{ $relation->reverse_label }}</span>
                                <a href="{{ $relation->source->url() }}" class="min-w-0 flex-1 truncate font-medium text-ink hover:text-ember">{{ $relation->source->name }}</a>
                            @else
                                <a href="{{ $relation->source->url() }}" class="min-w-0 truncate font-medium text-ink hover:text-ember">{{ $relation->source->name }}</a>
                                <span class="min-w-0 flex-1 truncate text-ink-muted">· {{ $relation->label }}</span>
                            @endif
                            @include('livewire.entities.partials.relation-controls', ['relation' => $relation])
                        </li>
                    @endforeach
                </ul>
            @endif

            @if ($canManage)
                <form wire:submit="relate" class="grid gap-3 border-t border-line px-5 py-4 sm:grid-cols-[minmax(0,1fr)_minmax(0,1fr)]">
                    <x-ui.input label="This page is" name="label" wire:model="label" placeholder="ally of" :maxlength="\App\Models\EntityRelation::MAX_LABEL_LENGTH" />
                    <x-ui.select label="Of" name="targetId" wire:model="targetId">
                        <option value="">Pick a page</option>
                        @foreach ($targetOptions as $option)
                            <option value="{{ $option->id }}">{{ $option->name }} ({{ $option->type->label() }})</option>
                        @endforeach
                    </x-ui.select>
                    <x-ui.input label="And from their side" name="reverseLabel" wire:model="reverseLabel" placeholder="allied with" hint="Optional. Without it their page reads this page's name and then the label." :maxlength="\App\Models\EntityRelation::MAX_LABEL_LENGTH" />
                    <div class="flex flex-wrap items-end justify-between gap-3">
                        <x-ui.checkbox label="Show the party" name="showParty" wire:model="showParty" />
                        <x-ui.button type="submit" variant="secondary" size="sm" icon="plus" wire:loading.attr="disabled">Relate</x-ui.button>
                    </div>
                </form>
            @endif
        </x-ui.card>
    @endif
</div>
