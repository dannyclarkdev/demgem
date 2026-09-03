<div>
    <nav class="mb-4 flex flex-wrap items-center gap-1.5 text-sm text-ink-faint" aria-label="Breadcrumb">
        <a href="{{ route('entities.index', [$campaign, $entity->type->slug()]) }}" class="hover:text-ink">{{ $entity->type->plural() }}</a>
        @foreach ($ancestors as $ancestor)
            <x-ui.icon name="chevron-right" class="size-3.5" />
            <a href="{{ $ancestor->url() }}" class="hover:text-ink">{{ $ancestor->name }}</a>
        @endforeach
        <x-ui.icon name="chevron-right" class="size-3.5" />
        <span class="text-ink-muted">{{ $entity->name }}</span>
    </nav>

    <x-ui.page-header :title="$entity->name" :eyebrow="$entity->type->label().($entity->is_pc ? ' · Player character' : '')">
        @if ($questStatus)
            <x-ui.badge :variant="$questStatus->badgeVariant()" :icon="$questStatus->icon()">{{ $questStatus->label() }}</x-ui.badge>
        @endif
        @if ($role->isDm())
            <x-ui.badge :variant="$entity->visibility === \App\Enums\Visibility::Dm ? 'dm' : 'neutral'" :icon="$entity->visibility === \App\Enums\Visibility::Dm ? 'eye-off' : 'eye'">{{ $entity->visibility->label() }}</x-ui.badge>
        @endif
        @can('update', $entity)
            <x-ui.button :href="$entity->editUrl()" variant="secondary" size="sm" icon="edit">Edit</x-ui.button>
        @endcan
        @can('delete', $entity)
            <x-ui.button variant="ghost" size="sm" icon="trash" wire:click="delete" wire:confirm="Delete {{ $entity->name }}? Children move up one level.">Delete</x-ui.button>
        @endcan
    </x-ui.page-header>

    <div class="grid gap-6 xl:grid-cols-[1fr_18rem]">
        <div class="space-y-6">
            <x-ui.card>
                @if ($bodyHtml !== '')
                    <div class="prose-entity">{!! $bodyHtml !!}</div>
                @else
                    <p class="text-sm text-ink-faint">Nothing written yet.</p>
                @endif
            </x-ui.card>

            @if ($entity->isQuest())
                <x-ui.card title="Objectives">
                    <livewire:quests.objectives
                        :campaign="$campaign"
                        :quest="$entity"
                        :wire:key="'objectives-'.$entity->id"
                    />
                </x-ui.card>

                @if ($rewardsHtml !== '')
                    <x-ui.card title="Rewards">
                        <div class="prose-entity">{!! $rewardsHtml !!}</div>
                    </x-ui.card>
                @endif
            @endif

            @if ($dmNotesHtml !== null)
                <x-ui.card class="border-dm/30">
                    <x-slot:header><x-ui.badge variant="dm" icon="eye-off">GM only</x-ui.badge></x-slot:header>
                    <h2 class="font-display text-base font-semibold text-dm">GM notes</h2>
                    @if ($dmNotesHtml !== '')
                        <div class="prose-entity mt-3">{!! $dmNotesHtml !!}</div>
                    @else
                        <p class="mt-2 text-sm text-ink-faint">No GM notes.</p>
                    @endif
                </x-ui.card>
            @endif
        </div>

        <aside class="space-y-5">
            @if ($entity->imageUrl())
                <a href="{{ $entity->imageUrl() }}" target="_blank" rel="noopener"><img src="{{ $entity->imageUrl() }}" alt="{{ $entity->name }}" class="w-full rounded-lg border border-line object-cover"></a>
            @endif

            @if ($entity->is_pc)
                <div>
                    <p class="eyebrow mb-2">Played by</p>
                    @if ($entity->player)
                        <div class="flex items-center gap-2 text-sm"><x-ui.avatar :name="$entity->player->name" size="sm" /> {{ $entity->player->name }}</div>
                    @else
                        <p class="text-sm text-ink-faint">Unassigned</p>
                    @endif
                </div>
            @endif

            {{-- A hidden giver renders nothing at all, not a placeholder. --}}
            @if ($giver)
                <div>
                    <p class="eyebrow mb-2">Given by</p>
                    <a href="{{ $giver->url() }}" class="flex items-center gap-2 text-sm text-ink-muted hover:text-ink">
                        <x-ui.icon :name="$giver->type->icon()" class="size-3.5 text-ink-faint" />
                        {{ $giver->name }}
                    </a>
                </div>
            @endif

            @if ($entity->tags->isNotEmpty())
                <div>
                    <p class="eyebrow mb-2">Tags</p>
                    <div class="flex flex-wrap gap-1.5">
                        @foreach ($entity->tags as $tag)
                            <a href="{{ route('entities.index', [$campaign, $entity->type->slug(), 'tag' => $tag->slug]) }}"><x-ui.badge icon="tag">{{ $tag->name }}</x-ui.badge></a>
                        @endforeach
                    </div>
                </div>
            @endif

            @if ($children->isNotEmpty())
                <div>
                    <p class="eyebrow mb-2">Inside {{ $entity->name }}</p>
                    <ul class="space-y-1">
                        @foreach ($children as $child)
                            <li><a href="{{ $child->url() }}" class="flex items-center gap-2 text-sm text-ink-muted hover:text-ink"><x-ui.icon :name="$child->type->icon()" class="size-3.5 text-ink-faint" /> {{ $child->name }}</a></li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if ($role->isDm() && $entity->visibility === \App\Enums\Visibility::Selected)
                <div>
                    <p class="eyebrow mb-2">Visible to</p>
                    @if ($entity->viewers->isEmpty())
                        <p class="text-sm text-ink-faint">No players selected. Only GMs see this.</p>
                    @else
                        <ul class="space-y-1">
                            @foreach ($entity->viewers as $viewerUser)
                                <li class="flex items-center gap-2 text-sm text-ink-muted"><x-ui.avatar :name="$viewerUser->name" size="sm" /> {{ $viewerUser->name }}</li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            @endif

            @if ($backlinks->isNotEmpty())
                <div>
                    <p class="eyebrow mb-2">Mentioned in</p>
                    <ul class="space-y-1">
                        @foreach ($backlinks as $backlink)
                            <li><a href="{{ $backlink->url() }}" class="flex items-center gap-2 text-sm text-ink-muted hover:text-ink"><x-ui.icon :name="$backlink->type->icon()" class="size-3.5 text-ink-faint" /> {{ $backlink->name }}</a></li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if ($sessions->isNotEmpty())
                <div>
                    <p class="eyebrow mb-2">Appears in sessions</p>
                    <ul class="space-y-1">
                        @foreach ($sessions as $session)
                            <li>
                                <a href="{{ $session->url() }}" class="flex items-center gap-2 text-sm text-ink-muted hover:text-ink">
                                    <x-ui.icon name="calendar" class="size-3.5 shrink-0 text-ink-faint" />
                                    <span class="truncate">{{ $session->label() }}</span>
                                    @if ($session->title)
                                        <span class="truncate text-ink-faint">{{ $session->title }}</span>
                                    @endif
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="text-xs text-ink-faint">
                Updated {{ $entity->updated_at?->diffForHumans() }}
            </div>
        </aside>
    </div>
</div>
