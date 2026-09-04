{{--
    The confirm screen. Its point is the "what will not come across" block: a GM who
    finds their images missing three weeks later has been told something they did not
    read, and a GM told before they press the button has made a decision.
--}}
<div class="mx-auto max-w-2xl">
    <x-ui.page-header
        title="Import a campaign"
        eyebrow="Library"
        description="Take the JSON file another demgem exported and build it here. It always makes a new campaign; nothing you already have is touched."
    >
        <x-ui.button :href="route('campaigns.index')" variant="ghost" size="sm" icon="arrow-left">Campaigns</x-ui.button>
    </x-ui.page-header>

    <x-ui.card>
        <x-ui.field
            label="Campaign file"
            for="file"
            :error="$errors->first('file')"
            hint="The JSON from a campaign's Export button, up to 25 MB."
        >
            <input
                type="file"
                id="file"
                wire:model="file"
                accept=".json,application/json"
                class="block w-full text-sm text-ink-muted file:mr-3 file:rounded-md file:border file:border-line-strong file:bg-raised file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-ink hover:file:border-ink-faint"
            >
        </x-ui.field>
        <p wire:loading wire:target="file" class="mt-2 text-xs text-ink-faint">Reading&hellip;</p>
    </x-ui.card>

    @if ($read && $problems !== [])
        <x-ui.alert variant="danger" class="mt-4">
            <p class="font-medium">That file cannot be imported.</p>
            <ul class="mt-2 list-disc space-y-1 pl-5 text-sm">
                @foreach ($problems as $message)
                    <li>{{ $message }}</li>
                @endforeach
            </ul>
        </x-ui.alert>
    @endif

    @if ($ready)
        <x-ui.card title="What will come across" class="mt-4">
            @if ($counts === [] || array_sum($counts) === 0)
                <p class="text-sm text-ink-faint">The file holds a campaign and nothing in it yet.</p>
            @else
                <ul class="grid gap-2 sm:grid-cols-2">
                    @foreach ($counts as $section => $rows)
                        <li class="flex items-baseline gap-2">
                            <span class="font-mono text-lg text-ink">{{ $rows }}</span>
                            <span class="text-sm text-ink-muted">{{ Str::of($section)->replace('_', ' ') }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-ui.card>

        @if ($losses !== [])
            <x-ui.card title="What will not" class="mt-4 border-dm/40">
                <ul class="space-y-4">
                    @foreach ($losses as $loss)
                        <li>
                            <p class="flex items-start gap-2 font-medium text-ink">
                                <x-ui.icon name="alert" class="mt-0.5 size-4 shrink-0 text-dm" />
                                {{ $loss['label'] }}
                            </p>
                            <p class="mt-1 pl-6 text-sm text-ink-muted">{{ $loss['detail'] }}</p>
                        </li>
                    @endforeach
                </ul>
            </x-ui.card>
        @endif

        <div class="mt-4 flex flex-wrap items-center gap-2">
            <x-ui.button wire:click="import" icon="plus">Import this campaign</x-ui.button>
            <x-ui.button wire:click="startOver" variant="ghost">Choose another file</x-ui.button>
            <span wire:loading wire:target="import" class="text-sm text-ink-faint">Building&hellip;</span>
        </div>
    @endif
</div>
