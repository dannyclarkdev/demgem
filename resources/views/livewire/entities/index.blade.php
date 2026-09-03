@php
    $canCreate = auth()->user()?->can('create', [\App\Models\Entity::class, $campaign]) ?? false;
@endphp
<div
    @if ($canCreate)
        @keydown.window="if ($event.key === 'n' && !['INPUT', 'TEXTAREA', 'SELECT'].includes(document.activeElement.tagName)) { $event.preventDefault(); window.location = @js(route('entities.create', [$campaign, $type->slug()])) }"
    @endif
>
    <x-ui.page-header :title="$type->plural()" :eyebrow="$campaign->name" :description="$type->description()">
        @can('create', [\App\Models\Entity::class, $campaign])
            <x-ui.button :href="route('entities.create', [$campaign, $type->slug()])" icon="plus">New {{ strtolower($type->label()) }}</x-ui.button>
        @endcan
    </x-ui.page-header>

    <div class="mb-4 flex flex-wrap items-center gap-2">
        <div class="relative min-w-56 flex-1 sm:max-w-xs">
            <x-ui.icon name="search" class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-ink-faint" />
            <input type="search" wire:model.live.debounce.300ms="search" placeholder="Filter {{ strtolower($type->plural()) }}" class="ui-input pl-9" aria-label="Filter by name">
        </div>

        @if ($role->isDm())
            <select wire:model.live="visibility" class="ui-input w-auto" aria-label="Filter by visibility">
                <option value="">Any visibility</option>
                @foreach ($visibilities as $option)
                    <option value="{{ $option->value }}">{{ $option->label() }}</option>
                @endforeach
            </select>
        @endif

        @if ($isQuest)
            <div class="flex flex-wrap items-center gap-1.5">
                @foreach ($questStatuses as $option)
                    <button
                        type="button"
                        wire:click="$set('questStatus', '{{ $questStatus === $option->value ? '' : $option->value }}')"
                        class="inline-flex items-center gap-1 rounded-full border px-2.5 py-0.5 text-xs font-medium transition {{ $questStatus === $option->value ? 'border-ember bg-ember/15 text-ember' : 'border-line text-ink-muted hover:border-line-strong hover:text-ink' }}"
                    >
                        <x-ui.icon :name="$option->icon()" class="size-3" />
                        {{ $option->label() }}
                    </button>
                @endforeach
            </div>
        @endif

        @if ($tags->isNotEmpty())
            <div class="flex flex-wrap items-center gap-1.5">
                @foreach ($tags as $option)
                    <button
                        type="button"
                        wire:click="$set('tag', '{{ $tag === $option->slug ? '' : $option->slug }}')"
                        class="inline-flex items-center gap-1 rounded-full border px-2.5 py-0.5 text-xs font-medium transition {{ $tag === $option->slug ? 'border-ember bg-ember/15 text-ember' : 'border-line text-ink-muted hover:border-line-strong hover:text-ink' }}"
                    >
                        <x-ui.icon name="tag" class="size-3" />
                        {{ $option->name }}
                        <span class="text-ink-faint">{{ $option->entities_count }}</span>
                    </button>
                @endforeach
            </div>
        @endif
    </div>

    @if ($entities->isEmpty())
        @if ($search !== '' || $tag !== '' || $visibility !== '' || $questStatus !== '')
            <x-ui.empty-state title="Nothing matches" description="Clear the filters to see every {{ strtolower($type->label()) }} you can view." :icon="$type->icon()">
                <x-ui.button variant="secondary" size="sm" wire:click="$set('search', ''); $set('tag', ''); $set('visibility', ''); $set('questStatus', '')">Clear filters</x-ui.button>
            </x-ui.empty-state>
        @else
            <x-ui.empty-state title="No {{ strtolower($type->plural()) }} yet" :description="$role->isDm() ? $type->description() : 'The GM has not revealed any '.strtolower($type->plural()).' yet.'" :icon="$type->icon()">
                @can('create', [\App\Models\Entity::class, $campaign])
                    <x-ui.button :href="route('entities.create', [$campaign, $type->slug()])" icon="plus">New {{ strtolower($type->label()) }}</x-ui.button>
                @endcan
            </x-ui.empty-state>
        @endif
    @else
        <x-ui.card :padding="false">
            <ul class="divide-y divide-line">
                @foreach ($entities as $entity)
                    <li wire:key="entity-{{ $entity->id }}">
                        <a href="{{ $entity->url() }}" class="flex items-center gap-3 px-5 py-3 transition hover:bg-raised/50">
                            @if ($entity->imageUrl('thumb'))
                                <img src="{{ $entity->imageUrl('thumb') }}" alt="" class="size-8 shrink-0 rounded-md object-cover">
                            @else
                                <span class="inline-flex size-8 shrink-0 items-center justify-center rounded-md bg-raised text-ink-muted">
                                    <x-ui.icon :name="$type->icon()" class="size-4" />
                                </span>
                            @endif
                            <div class="min-w-0 flex-1">
                                <p class="truncate font-medium text-ink">
                                    {{ $entity->name }}
                                    @if ($entity->is_pc)
                                        <span class="ml-1 text-xs font-normal text-ink-faint">PC{{ $entity->player ? ' · '.$entity->player->name : '' }}</span>
                                    @endif
                                </p>
                                @if ($entity->parent && $entity->parent->isVisibleTo($viewer, $role))
                                    <p class="truncate text-xs text-ink-faint">in {{ $entity->parent->name }}</p>
                                @endif
                            </div>
                            @if ($isQuest)
                                @php ($progress = $entity->objectiveProgress())
                                @if ($progress['total'] > 0)
                                    <x-ui.progress :value="$progress['done']" :max="$progress['total']" class="hidden w-28 sm:flex" />
                                @endif
                                @if ($entity->questStatus())
                                    <x-ui.badge :variant="$entity->questStatus()->badgeVariant()" :icon="$entity->questStatus()->icon()">{{ $entity->questStatus()->label() }}</x-ui.badge>
                                @endif
                            @else
                                <div class="hidden items-center gap-1.5 sm:flex">
                                    @foreach ($entity->tags->take(3) as $entityTag)
                                        <x-ui.badge>{{ $entityTag->name }}</x-ui.badge>
                                    @endforeach
                                </div>
                            @endif
                            @if ($role->isDm())
                                <x-ui.badge :variant="$entity->visibility === \App\Enums\Visibility::Dm ? 'dm' : 'neutral'" :icon="$entity->visibility === \App\Enums\Visibility::Dm ? 'eye-off' : 'eye'">{{ $entity->visibility->label() }}</x-ui.badge>
                            @endif
                            <x-ui.icon name="chevron-right" class="size-4 text-ink-faint" />
                        </a>
                    </li>
                @endforeach
            </ul>
        </x-ui.card>

        @if ($entities->hasPages())
            <div class="mt-4">{{ $entities->links() }}</div>
        @endif
    @endif
</div>
