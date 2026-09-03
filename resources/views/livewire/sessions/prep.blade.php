<div>
    <x-ui.page-header :title="$session->displayTitle().' — prep'" :eyebrow="$session->label()" description="Write the start, sketch the scenes, and pull in what you need at the table.">
        <x-ui.button :href="route('sessions.run', [$campaign, $session->number])" size="sm" icon="play">Run it</x-ui.button>
        <x-ui.button :href="$session->url()" variant="ghost" size="sm" icon="arrow-left">Session</x-ui.button>
    </x-ui.page-header>

    <div class="grid gap-6 lg:grid-cols-[1fr_18rem]">
        <div class="space-y-6">
            <x-ui.card title="Strong start">
                <form wire:submit="saveNotes" class="space-y-5">
                    <x-ui.markdown-editor
                        name="strong_start"
                        wire:model="strong_start"
                        rows="5"
                        :autocomplete-url="$autocompleteUrl"
                        preview-action="previewStrongStart"
                        :preview="$strongStartPreview"
                        hint="The first thing that happens. Open with it and the table wakes up."
                    />
                    <x-ui.markdown-editor
                        label="GM notes"
                        name="dm_notes"
                        wire:model="dm_notes"
                        rows="4"
                        :autocomplete-url="$autocompleteUrl"
                        preview-action="previewDmNotes"
                        :preview="$dmNotesPreview"
                        hint="Private to GMs, in this session and forever."
                    />
                    <div class="flex justify-end">
                        <x-ui.button type="submit">Save prep</x-ui.button>
                    </div>
                </form>
            </x-ui.card>

            <x-ui.card title="Scenes" :padding="false">
                <x-slot:header>
                    <span class="text-xs text-ink-faint">{{ $scenes->count() }} outlined</span>
                </x-slot:header>

                @if ($scenes->isEmpty())
                    <div class="px-5 py-6">
                        <p class="text-sm text-ink-faint">No scenes yet. Sketch what might happen; the party will pick their own order anyway.</p>
                    </div>
                @else
                    <ul wire:sort="reorderScenes" class="divide-y divide-line">
                        @foreach ($scenes as $index => $scene)
                            <li wire:key="scene-{{ $scene->id }}" wire:sort:item="{{ $scene->id }}" class="px-5 py-3">
                                @if ($editingSceneId === $scene->id)
                                    <form wire:submit="saveScene" class="space-y-4">
                                        <x-ui.input label="Scene title" name="sceneTitle" wire:model="sceneTitle" />
                                        <x-ui.markdown-editor
                                            label="Notes"
                                            name="sceneNotes"
                                            wire:model="sceneNotes"
                                            rows="6"
                                            :autocomplete-url="$autocompleteUrl"
                                            preview-action="previewSceneNotes"
                                            :preview="$sceneNotesPreview"
                                            hint="Link people and places with [[double brackets]]."
                                        />
                                        <div class="flex items-center gap-2">
                                            <x-ui.button type="submit" size="sm">Save scene</x-ui.button>
                                            <x-ui.button type="button" variant="ghost" size="sm" wire:click="cancelSceneEdit">Cancel</x-ui.button>
                                        </div>
                                    </form>
                                @else
                                    <div class="flex items-start gap-3">
                                        <button type="button" wire:sort:handle class="mt-0.5 cursor-grab text-ink-faint hover:text-ink-muted" aria-label="Drag to reorder">
                                            <x-ui.icon name="grip" class="size-4" />
                                        </button>
                                        <div class="min-w-0 flex-1">
                                            <p class="font-medium text-ink">
                                                <span class="mr-1.5 font-mono text-xs text-ink-faint">{{ $index + 1 }}</span>
                                                {{ $scene->title }}
                                            </p>
                                            @if ($sceneNotesHtml[$scene->id] !== '')
                                                <div class="prose-entity mt-2 text-sm">{!! $sceneNotesHtml[$scene->id] !!}</div>
                                            @endif
                                        </div>
                                        <div wire:sort:ignore class="flex shrink-0 items-center gap-0.5">
                                            <x-ui.button variant="ghost" size="icon" wire:click="moveScene('{{ $scene->id }}', -1)" :disabled="$loop->first" aria-label="Move up">
                                                <x-ui.icon name="arrow-up" class="size-4" />
                                            </x-ui.button>
                                            <x-ui.button variant="ghost" size="icon" wire:click="moveScene('{{ $scene->id }}', 1)" :disabled="$loop->last" aria-label="Move down">
                                                <x-ui.icon name="arrow-down" class="size-4" />
                                            </x-ui.button>
                                            <x-ui.button variant="ghost" size="icon" wire:click="editScene('{{ $scene->id }}')" aria-label="Edit scene">
                                                <x-ui.icon name="edit" class="size-4" />
                                            </x-ui.button>
                                            <x-ui.button variant="ghost" size="icon" wire:click="removeScene('{{ $scene->id }}')" wire:confirm="Delete the scene &ldquo;{{ $scene->title }}&rdquo;?" aria-label="Delete scene">
                                                <x-ui.icon name="trash" class="size-4" />
                                            </x-ui.button>
                                        </div>
                                    </div>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @endif

                <x-slot:footer>
                    <form wire:submit="addScene" class="flex items-end gap-2">
                        <div class="flex-1">
                            <x-ui.input label="Add a scene" name="newSceneTitle" wire:model="newSceneTitle" placeholder="The toll bridge" />
                        </div>
                        <x-ui.button type="submit" icon="plus">Add</x-ui.button>
                    </form>
                </x-slot:footer>
            </x-ui.card>

            <x-ui.card title="Secrets and clues" :padding="false">
                <x-slot:header>
                    <span class="text-xs text-ink-faint">{{ $secrets->count() }} ready · {{ $revealedSecrets->count() }} out</span>
                </x-slot:header>

                @if ($secrets->isEmpty())
                    <p class="px-5 py-4 text-sm text-ink-faint">Nothing prepared yet. Ten short truths the party can learn tonight, in any order, from any direction.</p>
                @else
                    <ul class="divide-y divide-line">
                        @foreach ($secrets as $secret)
                            <li wire:key="secret-{{ $secret->id }}" class="px-5 py-3">
                                @if ($editingSecretId === $secret->id)
                                    <form wire:submit="saveSecret" class="space-y-3">
                                        <x-ui.textarea label="Secret or clue" name="secretBody" wire:model="secretBody" rows="2" />
                                        <div class="flex items-center gap-2">
                                            <x-ui.button type="submit" size="sm">Save</x-ui.button>
                                            <x-ui.button type="button" variant="ghost" size="sm" wire:click="cancelSecretEdit">Cancel</x-ui.button>
                                        </div>
                                    </form>
                                @else
                                    <div class="flex items-start gap-3">
                                        <x-ui.icon name="key" class="mt-1 size-4 shrink-0 text-ink-faint" />
                                        <div class="prose-entity min-w-0 flex-1 text-sm">{!! $secretHtml[$secret->id] !!}</div>
                                        <div class="flex shrink-0 items-center gap-0.5">
                                            <x-ui.button variant="secondary" size="sm" wire:click="revealSecret('{{ $secret->id }}')">Reveal</x-ui.button>
                                            <x-ui.button variant="ghost" size="icon" wire:click="editSecret('{{ $secret->id }}')" aria-label="Edit secret">
                                                <x-ui.icon name="edit" class="size-4" />
                                            </x-ui.button>
                                            <x-ui.button variant="ghost" size="icon" wire:click="removeSecret('{{ $secret->id }}')" wire:confirm="Delete this secret?" aria-label="Delete secret">
                                                <x-ui.icon name="trash" class="size-4" />
                                            </x-ui.button>
                                        </div>
                                    </div>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @endif

                @if ($carriedSecrets->isNotEmpty())
                    <div class="border-t border-line bg-raised/30">
                        <p class="px-5 pt-3 pb-1 text-xs font-medium tracking-wide text-ink-muted uppercase">Carried over</p>
                        <p class="px-5 pb-2 text-xs text-ink-faint">Still unrevealed. Nothing is lost because the party missed it.</p>
                        <ul class="divide-y divide-line">
                            @foreach ($carriedSecrets as $secret)
                                <li wire:key="carried-{{ $secret->id }}" class="flex items-start gap-3 px-5 py-2.5">
                                    <x-ui.icon name="clock" class="mt-1 size-4 shrink-0 text-ink-faint" />
                                    <div class="prose-entity min-w-0 flex-1 text-sm">{!! $secretHtml[$secret->id] !!}</div>
                                    <div class="flex shrink-0 items-center gap-1">
                                        <x-ui.button variant="ghost" size="sm" wire:click="carrySecretForward('{{ $secret->id }}')">Pull in</x-ui.button>
                                        <x-ui.button variant="secondary" size="sm" wire:click="revealSecret('{{ $secret->id }}')">Reveal</x-ui.button>
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                @if ($revealedSecrets->isNotEmpty())
                    <div class="border-t border-line">
                        <p class="px-5 pt-3 pb-1 text-xs font-medium tracking-wide text-ink-muted uppercase">Revealed here</p>
                        <ul class="divide-y divide-line">
                            @foreach ($revealedSecrets as $secret)
                                <li wire:key="revealed-{{ $secret->id }}" class="flex items-start gap-3 px-5 py-2.5 opacity-70">
                                    <x-ui.icon name="check" class="mt-1 size-4 shrink-0 text-success" />
                                    <div class="prose-entity min-w-0 flex-1 text-sm">{!! $secretHtml[$secret->id] !!}</div>
                                    <x-ui.button variant="ghost" size="sm" wire:click="unrevealSecret('{{ $secret->id }}')">Undo</x-ui.button>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <x-slot:footer>
                    <form wire:submit="addSecret" class="flex items-end gap-2">
                        <div class="flex-1">
                            <x-ui.input label="Add a secret or clue" name="newSecretBody" wire:model="newSecretBody" placeholder="The duke's signet ring is a forgery" />
                        </div>
                        <x-ui.button type="submit" icon="plus">Add</x-ui.button>
                    </form>
                </x-slot:footer>
            </x-ui.card>

            <div class="grid gap-4 md:grid-cols-2">
                @foreach ($prepRoles as $prepRole)
                    <x-ui.card :title="$prepRole->plural()" :padding="false" wire:key="bucket-{{ $prepRole->value }}">
                        <x-slot:header>
                            <x-ui.button variant="ghost" size="sm" icon="plus" wire:click="openPicker('{{ $prepRole->value }}')">Add</x-ui.button>
                        </x-slot:header>

                        @if ($buckets[$prepRole->value]->isEmpty())
                            <p class="px-5 py-4 text-sm text-ink-faint">{{ $prepRole->description() }}</p>
                        @else
                            <ul class="divide-y divide-line">
                                @foreach ($buckets[$prepRole->value] as $entity)
                                    <li wire:key="prep-{{ $prepRole->value }}-{{ $entity->id }}" class="flex items-center gap-3 px-5 py-2.5">
                                        @if ($entity->imageUrl('thumb'))
                                            <img src="{{ $entity->imageUrl('thumb') }}" alt="" class="size-7 shrink-0 rounded-md object-cover">
                                        @else
                                            <span class="inline-flex size-7 shrink-0 items-center justify-center rounded-md bg-raised text-ink-muted">
                                                <x-ui.icon :name="$entity->type->icon()" class="size-3.5" />
                                            </span>
                                        @endif
                                        <a href="{{ $entity->url() }}" class="min-w-0 flex-1 truncate text-sm font-medium text-ink hover:text-ember">{{ $entity->name }}</a>
                                        <x-ui.button variant="ghost" size="icon" wire:click="detachEntity('{{ $entity->id }}', '{{ $prepRole->value }}')" aria-label="Remove {{ $entity->name }}">
                                            <x-ui.icon name="x" class="size-4" />
                                        </x-ui.button>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </x-ui.card>
                @endforeach
            </div>
        </div>

        <aside class="space-y-4">
            <x-ui.card title="Prep checklist">
                <ul class="space-y-2.5">
                    @foreach ($checklist as $step)
                        <li class="flex items-start gap-2.5">
                            <span class="mt-0.5 inline-flex size-4 shrink-0 items-center justify-center rounded-full border {{ $step['done'] ? 'border-success bg-success/15 text-success' : 'border-line text-ink-faint' }}">
                                @if ($step['done'])<x-ui.icon name="check" class="size-3" />@endif
                            </span>
                            <span class="min-w-0 flex-1">
                                <span class="block text-sm {{ $step['done'] ? 'text-ink' : 'text-ink-muted' }}">
                                    {{ $step['label'] }}
                                    @if ($step['count'] !== null)
                                        <span class="ml-1 font-mono text-xs text-ink-faint">{{ $step['count'] }}</span>
                                    @endif
                                </span>
                                <span class="block text-xs text-ink-faint">{{ $step['hint'] }}</span>
                            </span>
                        </li>
                    @endforeach
                </ul>
            </x-ui.card>

            <x-ui.card title="The party" :padding="false">
                @if ($party->isEmpty())
                    <p class="px-5 py-4 text-sm text-ink-faint">No player characters yet. Add characters and mark them as PCs.</p>
                @else
                    <ul class="divide-y divide-line">
                        @foreach ($party as $pc)
                            <li class="px-5 py-2.5">
                                <a href="{{ $pc->url() }}" class="block">
                                    <p class="truncate text-sm font-medium text-ink">{{ $pc->name }}</p>
                                    <p class="truncate text-xs text-ink-faint">{{ $pc->player?->name ?? 'No player assigned' }}</p>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-ui.card>
        </aside>
    </div>

    @if ($pickerRole !== '')
        @php($activeRole = \App\Enums\PrepRole::from($pickerRole))
        <div class="fixed inset-0 z-50 flex items-start justify-center p-4 pt-20">
            <div class="absolute inset-0 bg-black/70" wire:click="closePicker"></div>
            <div class="relative w-full max-w-lg rounded-xl border border-line bg-panel shadow-2xl shadow-black/40">
                <header class="flex items-center border-b border-line px-5 py-3">
                    <h2 class="font-display text-lg font-semibold">Add to {{ strtolower($activeRole->plural()) }}</h2>
                    <button type="button" class="ml-auto text-ink-faint hover:text-ink" wire:click="closePicker" aria-label="Close">
                        <x-ui.icon name="x" class="size-4" />
                    </button>
                </header>
                <div class="p-5">
                    <div class="relative">
                        <x-ui.icon name="search" class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-ink-faint" />
                        <input type="search" wire:model.live.debounce.250ms="pickerSearch" placeholder="Search the campaign" class="ui-input pl-9" autofocus aria-label="Search entities">
                    </div>

                    <ul class="mt-3 max-h-80 divide-y divide-line overflow-y-auto">
                        @forelse ($pickerResults as $option)
                            <li wire:key="pick-{{ $option->id }}">
                                <button type="button" wire:click="attachEntity('{{ $option->id }}')" class="flex w-full items-center gap-3 rounded-md px-2 py-2 text-left hover:bg-raised">
                                    <span class="inline-flex size-7 shrink-0 items-center justify-center rounded-md bg-raised text-ink-muted">
                                        <x-ui.icon :name="$option->type->icon()" class="size-3.5" />
                                    </span>
                                    <span class="min-w-0 flex-1 truncate text-sm text-ink">{{ $option->name }}</span>
                                    <span class="text-[11px] tracking-wide text-ink-faint uppercase">{{ $option->type->label() }}</span>
                                </button>
                            </li>
                        @empty
                            <li class="px-2 py-4 text-sm text-ink-faint">Nothing left to add. Create it in the wiki first.</li>
                        @endforelse
                    </ul>
                </div>
            </div>
        </div>
    @endif
</div>
