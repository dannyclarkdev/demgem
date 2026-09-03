<div class="flex h-full flex-col">
    @if ($tables->isEmpty())
        <p class="p-4 text-sm text-ink-faint">
            No tables yet. <a href="{{ route('tables.index', $campaign) }}" class="text-ember hover:underline">Build one</a> and it turns up here.
        </p>
    @else
        <div class="flex flex-wrap gap-1.5 border-b border-line p-4">
            @foreach ($tables as $table)
                <button
                    type="button"
                    wire:click="roll('{{ $table->id }}')"
                    wire:key="roll-{{ $table->id }}"
                    class="inline-flex items-center gap-1.5 rounded-md border border-line bg-canvas px-2.5 py-1.5 text-sm text-ink hover:border-ember hover:text-ember"
                >
                    {{ $table->name }}
                    <span class="font-mono text-[11px] text-ink-faint">{{ $table->dieLabel() }}</span>
                </button>
            @endforeach
        </div>
    @endif

    <div class="min-h-0 flex-1 overflow-y-auto">
        @if ($history === [])
            <p class="p-4 text-sm text-ink-faint">Nothing rolled yet. Paste anything worth keeping into the live notes.</p>
        @else
            <ul class="divide-y divide-line">
                @foreach ($history as $index => $line)
                    <li wire:key="history-{{ $index }}" class="px-4 py-3">
                        <p class="flex items-baseline gap-2 text-xs text-ink-faint">
                            <span class="truncate">{{ $line['table'] }}</span>
                            @if ($line['roll'] !== null)
                                <span class="font-mono">{{ $line['roll'] }}</span>
                            @endif
                        </p>
                        @if ($line['note'])
                            <p class="mt-0.5 text-sm text-danger">{{ $line['note'] }}</p>
                        @else
                            <p class="prose-entity mt-0.5 text-[15px]">{!! $line['body'] !!}</p>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif
    </div>

    @if ($history !== [])
        <div class="border-t border-line px-4 py-2 text-right">
            <x-ui.button variant="ghost" size="sm" wire:click="clearHistory">Clear</x-ui.button>
        </div>
    @endif
</div>
