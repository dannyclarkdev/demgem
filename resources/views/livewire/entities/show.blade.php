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

    {{-- A map is the image, so it gets the whole width and the aside keeps out of
         its way. Every other type wears its picture in the sidebar. --}}
    @if ($entity->isMap())
        <div class="mb-6">
            <livewire:maps.viewer :campaign="$campaign" :map-id="$entity->id" :wire:key="'map-'.$entity->id" />
        </div>
    @endif

    {{-- A handout is the files, so they come before the prose rather than after it.
         An image opens full size in the kit modal; a PDF is a named row with a
         download, because a browser is better at PDFs than we are.

         hasGeneratedConversion() rather than a mime check: whether there is a tile to
         show is a fact about the file, and it stays true on a machine with no
         Ghostscript, where a PDF simply never got one. --}}
    @if ($entity->isHandout() && $files->isNotEmpty())
        <div class="mb-6" x-data="{ src: '', caption: '' }">
            <ul class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($files as $file)
                    <li wire:key="handout-file-{{ $file->id }}">
                        @if ($file->hasGeneratedConversion('tile'))
                            <button
                                type="button"
                                class="group block w-full overflow-hidden rounded-lg border border-line bg-panel"
                                @click="src = @js($file->getUrl()); caption = @js($file->file_name); $dispatch('open-modal', { name: 'handout-file' })"
                            >
                                <img src="{{ $file->getUrl('tile') }}" alt="{{ $file->file_name }}" class="aspect-4/3 w-full object-cover transition group-hover:opacity-90">
                                <span class="block truncate px-3 py-2 text-left text-xs text-ink-faint">{{ $file->file_name }}</span>
                            </button>
                        @else
                            <a
                                href="{{ $file->getUrl() }}"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="flex min-h-11 items-center gap-3 rounded-lg border border-line bg-panel px-4 py-3 hover:border-ink-faint"
                            >
                                <x-ui.icon name="file-text" class="size-5 shrink-0 text-ink-faint" />
                                <span class="min-w-0 flex-1">
                                    <span class="block truncate text-sm text-ink">{{ $file->file_name }}</span>
                                    <span class="block text-xs text-ink-faint">{{ number_format($file->size / 1024, 0) }} KB</span>
                                </span>
                                <x-ui.icon name="download" class="size-4 shrink-0 text-ink-faint" />
                            </a>
                        @endif
                    </li>
                @endforeach
            </ul>

            <x-ui.modal name="handout-file" max-width="max-w-5xl">
                <img :src="src" :alt="caption" class="max-h-[75vh] w-full rounded-md object-contain">
                <p class="mt-3 text-center text-xs text-ink-faint" x-text="caption"></p>
            </x-ui.modal>
        </div>
    @endif

    @if ($entity->hasCharacterRecord())
        <div class="mb-6 flex flex-wrap items-center gap-x-8 gap-y-4 rounded-lg border border-line bg-panel px-5 py-4">
            @if (filled($entity->character_class))
                <div>
                    <p class="eyebrow">Class</p>
                    <p class="mt-0.5 font-medium text-ink">{{ $entity->character_class }}</p>
                </div>
            @endif

            @if ($entity->level !== null)
                <div>
                    <p class="eyebrow">Level</p>
                    <p class="mt-0.5 font-medium text-ink">{{ $entity->level }}</p>
                </div>
            @endif

            @if ($entity->is_pc && $entity->player)
                <div>
                    <p class="eyebrow">Played by</p>
                    <p class="mt-0.5 font-medium text-ink">{{ $entity->player->name }}</p>
                </div>
            @endif

            @if (filled($entity->sheet_url))
                {{-- The one user-supplied URL in the app rendered as an href outside the
                     Markdown renderer. url:http,https at write time is what makes it safe. --}}
                <x-ui.button
                    :href="$entity->sheet_url"
                    target="_blank"
                    rel="noopener noreferrer nofollow"
                    variant="secondary"
                    size="sm"
                    icon="external"
                    class="ml-auto"
                >{{ $entity->sheetHost() ?? 'Character sheet' }}</x-ui.button>
            @endif
        </div>
    @endif

    <div class="grid gap-6 xl:grid-cols-[1fr_18rem]">
        <div class="space-y-6">
            <x-ui.card>
                @if ($bodyHtml !== '')
                    <div class="prose-entity">{!! $bodyHtml !!}</div>
                @else
                    <p class="text-sm text-ink-faint">Nothing written yet.</p>
                @endif
            </x-ui.card>

            @php ($customFields = $entity->customFields())
            @if ($customFields !== [])
                <x-ui.card title="Details">
                    <dl class="grid gap-x-8 gap-y-3 sm:grid-cols-2">
                        @foreach ($customFields as $field)
                            <div>
                                <dt class="eyebrow">{{ $field['key'] }}</dt>
                                <dd class="mt-0.5 text-ink">{{ $field['value'] }}</dd>
                            </div>
                        @endforeach
                    </dl>
                </x-ui.card>
            @endif

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
            @if ($entity->imageUrl() && ! $entity->isMap())
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

            {{-- The backlinks question, asked of pictures. Both of a pin's gates
                 applied in the query: a pin the party has not found does not tell
                 them the thing is on that map. --}}
            @if ($pinnedOn->isNotEmpty())
                <div>
                    <p class="eyebrow mb-2">Appears on</p>
                    <ul class="space-y-1">
                        @foreach ($pinnedOn as $pinMap)
                            <li>
                                <a href="{{ $pinMap->url() }}" class="flex items-center gap-2 text-sm text-ink-muted hover:text-ink">
                                    <x-ui.icon name="map" class="size-3.5 shrink-0 text-ink-faint" />
                                    <span class="truncate">{{ $pinMap->name }}</span>
                                </a>
                            </li>
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
