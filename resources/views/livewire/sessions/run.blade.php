<div class="text-base">
    <x-ui.page-header :title="'Running '.$session->label()" :eyebrow="$session->displayTitle()">
        <x-ui.badge :variant="$session->status->badgeVariant()" :icon="$session->status->icon()">{{ $session->status->label() }}</x-ui.badge>
        @if ($session->status !== \App\Enums\SessionStatus::Played)
            <x-ui.button size="sm" icon="check" wire:click="setStatus('played')">Mark played</x-ui.button>
        @else
            <x-ui.button variant="ghost" size="sm" icon="arrow-left" wire:click="setStatus('planned')">Back to planned</x-ui.button>
        @endif
        <x-ui.button :href="route('sessions.prep', [$campaign, $session->number])" variant="secondary" size="sm" icon="zap">Prep</x-ui.button>
        <x-ui.button :href="$session->url()" variant="ghost" size="sm">Session</x-ui.button>
    </x-ui.page-header>

    <x-ui.drawer name="tools" title="Dice" icon="dice">
        <livewire:dice.tray :campaign="$campaign" :session="$session" :wire:key="'dice-'.$session->id" />
    </x-ui.drawer>

    <div class="grid gap-6 lg:grid-cols-[minmax(0,2fr)_minmax(0,1fr)]">
        <div class="space-y-6">
            @if ($strongStartHtml !== '')
                <x-ui.card title="Strong start">
                    <div class="prose-entity">{!! $strongStartHtml !!}</div>
                </x-ui.card>
            @endif

            {{-- Combat is the main event when combat is happening, so the tracker takes the
                 main column rather than the aside, which is already full. --}}
            @if ($encounters->isEmpty())
                <x-ui.card>
                    <div class="flex flex-wrap items-center gap-3">
                        <x-ui.icon name="swords" class="size-5 text-ink-faint" />
                        <p class="min-w-0 flex-1 text-sm text-ink-muted">No fight running. Start one and the turn order opens here.</p>
                        <x-ui.button size="sm" icon="plus" wire:click="startEncounter">Start an encounter</x-ui.button>
                    </div>
                </x-ui.card>
            @else
                @foreach ($encounters as $encounter)
                    <x-ui.card :padding="false" wire:key="run-encounter-{{ $encounter->id }}">
                        <x-slot:header>
                            <a href="{{ $encounter->url() }}" class="text-xs text-ink-faint hover:text-ink">Open on its own page</a>
                        </x-slot:header>
                        <h2 class="border-b border-line px-5 py-3 font-display text-base font-semibold">{{ $encounter->name }}</h2>
                        <livewire:encounters.tracker :campaign="$campaign" :encounter="$encounter" :wire:key="'tracker-'.$encounter->id" />
                    </x-ui.card>
                @endforeach

                <div class="text-right">
                    <x-ui.button variant="ghost" size="sm" icon="plus" wire:click="startEncounter">Another encounter</x-ui.button>
                </div>
            @endif

            <x-ui.card>
                <livewire:sessions.live-notes :campaign="$campaign" :session="$session" :wire:key="'live-notes-'.$session->id" />
            </x-ui.card>

            @if ($activeQuests->isNotEmpty())
                <x-ui.card title="Active quests" :padding="false">
                    <x-slot:header>
                        <span class="text-xs text-ink-faint">Ticking one here records {{ $session->label() }}</span>
                    </x-slot:header>
                    <ul class="divide-y divide-line">
                        @foreach ($activeQuests as $quest)
                            <li class="px-5 py-4">
                                <a href="{{ $quest->url() }}" class="flex items-center gap-2 font-medium text-ink hover:text-ember">
                                    <x-ui.icon name="target" class="size-4 shrink-0 text-ember" />
                                    {{ $quest->name }}
                                </a>
                                <div class="mt-2">
                                    <livewire:quests.objectives
                                        :campaign="$campaign"
                                        :quest="$quest"
                                        :session="$session"
                                        :compact="true"
                                        :wire:key="'run-objectives-'.$quest->id"
                                    />
                                </div>
                            </li>
                        @endforeach
                    </ul>
                </x-ui.card>
            @endif

            <x-ui.card title="Scenes" :padding="false">
                @if ($scenes->isEmpty())
                    <p class="px-5 py-4 text-ink-faint">No scenes prepped. Improvise; the party will anyway.</p>
                @else
                    <ol class="divide-y divide-line">
                        @foreach ($scenes as $index => $scene)
                            <li class="px-5 py-4">
                                <p class="font-medium text-ink">
                                    <span class="mr-1.5 font-mono text-sm text-ink-faint">{{ $index + 1 }}</span>
                                    {{ $scene->title }}
                                </p>
                                @if ($sceneNotesHtml[$scene->id] !== '')
                                    <div class="prose-entity mt-2">{!! $sceneNotesHtml[$scene->id] !!}</div>
                                @endif
                            </li>
                        @endforeach
                    </ol>
                @endif
            </x-ui.card>

            @if ($dmNotesHtml !== '')
                <x-ui.card class="border-dm/30">
                    <x-slot:header><x-ui.badge variant="dm" icon="eye-off">GM only</x-ui.badge></x-slot:header>
                    <h2 class="font-display text-base font-semibold text-dm">GM notes</h2>
                    <div class="prose-entity mt-3">{!! $dmNotesHtml !!}</div>
                </x-ui.card>
            @endif
        </div>

        <aside class="space-y-6">
            <x-ui.card title="Secrets and clues" :padding="false">
                <x-slot:header>
                    <span class="text-xs text-ink-faint">{{ $readySecrets->count() }} left</span>
                </x-slot:header>

                @if ($readySecrets->isEmpty())
                    <p class="px-5 py-4 text-sm text-ink-faint">Nothing left to give away tonight.</p>
                @else
                    <ul class="divide-y divide-line">
                        @foreach ($readySecrets as $secret)
                            <li wire:key="run-secret-{{ $secret->id }}" class="px-5 py-3">
                                <div class="prose-entity">{!! $secretHtml[$secret->id] !!}</div>
                                <x-ui.button class="mt-2 w-full" variant="secondary" wire:click="revealSecret('{{ $secret->id }}')" icon="key">Reveal</x-ui.button>
                            </li>
                        @endforeach
                    </ul>
                @endif

                @if ($revealedSecrets->isNotEmpty())
                    <div class="border-t border-line">
                        <p class="px-5 pt-3 pb-1 text-xs font-medium tracking-wide text-ink-muted uppercase">Revealed tonight</p>
                        <ul class="divide-y divide-line">
                            @foreach ($revealedSecrets as $secret)
                                <li wire:key="run-revealed-{{ $secret->id }}" class="flex items-start gap-2 px-5 py-2.5 opacity-70">
                                    <x-ui.icon name="check" class="mt-1 size-4 shrink-0 text-success" />
                                    <div class="prose-entity min-w-0 flex-1 text-sm">{!! $secretHtml[$secret->id] !!}</div>
                                    <x-ui.button variant="ghost" size="sm" wire:click="unrevealSecret('{{ $secret->id }}')">Undo</x-ui.button>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </x-ui.card>

            @foreach ($prepRoles as $prepRole)
                @if ($buckets[$prepRole->value]->isNotEmpty())
                    <x-ui.card :title="$prepRole->plural()" :padding="false" wire:key="run-bucket-{{ $prepRole->value }}">
                        <ul class="divide-y divide-line">
                            @foreach ($buckets[$prepRole->value] as $entity)
                                <li class="px-5 py-2.5">
                                    <a href="{{ $entity->url() }}" target="_blank" class="flex items-center gap-3 text-ink hover:text-ember">
                                        @if ($entity->imageUrl('thumb'))
                                            <img src="{{ $entity->imageUrl('thumb') }}" alt="" class="size-8 shrink-0 rounded-md object-cover">
                                        @else
                                            <span class="inline-flex size-8 shrink-0 items-center justify-center rounded-md bg-raised text-ink-muted">
                                                <x-ui.icon :name="$entity->type->icon()" class="size-4" />
                                            </span>
                                        @endif
                                        <span class="min-w-0 flex-1 truncate">{{ $entity->name }}</span>
                                        <x-ui.icon name="external" class="size-4 shrink-0 text-ink-faint" />
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    </x-ui.card>
                @endif
            @endforeach

            @if ($party->isNotEmpty())
                <x-ui.card title="The party" :padding="false">
                    <ul class="divide-y divide-line">
                        @foreach ($party as $pc)
                            <li class="px-5 py-2.5">
                                <a href="{{ $pc->url() }}" target="_blank" class="block">
                                    <p class="truncate font-medium text-ink">{{ $pc->name }}</p>
                                    <p class="truncate text-sm text-ink-faint">{{ $pc->player?->name ?? 'No player assigned' }}</p>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </x-ui.card>
            @endif
        </aside>
    </div>
</div>
