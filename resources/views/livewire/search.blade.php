<div>
    <x-ui.page-header title="Search" :eyebrow="$campaign->name" />

    <form method="get" action="{{ route('search', $campaign) }}" class="mb-6">
        <div class="relative max-w-xl">
            <x-ui.icon name="search" class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-ink-faint" />
            <input type="search" name="q" wire:model.live.debounce.400ms="query" value="{{ $term }}" placeholder="Search names and text" class="ui-input h-11 pl-9 text-base" autofocus aria-label="Search the campaign">
        </div>
    </form>

    @if ($term === '')
        <x-ui.empty-state title="Search the campaign" description="Names and body text of everything you can see. GM notes are never searched." icon="search" />
    @elseif ($total === 0)
        <x-ui.empty-state title="No results for &ldquo;{{ $term }}&rdquo;" description="Try a shorter word, or check the spelling." icon="search" />
    @else
        <div class="space-y-6">
            @foreach ($groups as $typeValue => $entities)
                @php $type = \App\Enums\EntityType::from($typeValue); @endphp
                <section>
                    <p class="eyebrow mb-2">{{ $type->plural() }} <span class="font-mono text-ink-faint">{{ $entities->count() }}</span></p>
                    <x-ui.card :padding="false">
                        <ul class="divide-y divide-line">
                            @foreach ($entities as $entity)
                                <li>
                                    <a href="{{ $entity->url() }}" class="flex items-center gap-3 px-5 py-2.5 transition hover:bg-raised/50">
                                        <x-ui.icon :name="$type->icon()" class="size-4 text-ink-faint" />
                                        <span class="min-w-0 flex-1 truncate font-medium">{{ $entity->name }}</span>
                                        @foreach ($entity->tags->take(2) as $tag)
                                            <x-ui.badge>{{ $tag->name }}</x-ui.badge>
                                        @endforeach
                                        <x-ui.icon name="chevron-right" class="size-4 text-ink-faint" />
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    </x-ui.card>
                </section>
            @endforeach
        </div>
    @endif
</div>
