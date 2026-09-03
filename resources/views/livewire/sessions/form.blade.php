<div>
    <x-ui.page-header
        :title="$isEdit ? 'Edit '.$session->label() : 'New session'"
        :eyebrow="$campaign->name"
        :description="$isEdit ? null : 'Number, title, and date. Prep comes next.'"
    >
        <x-ui.button :href="$isEdit ? $session->url() : route('sessions.index', $campaign)" variant="ghost" size="sm" icon="arrow-left">Cancel</x-ui.button>
    </x-ui.page-header>

    <form wire:submit="save" class="max-w-2xl space-y-6">
        <x-ui.card>
            <div class="grid gap-5 sm:grid-cols-[8rem_1fr]">
                <x-ui.input label="Number" name="number" type="number" min="0" max="9999" wire:model="number" hint="Session 0 is fine." />
                <x-ui.input label="Title" name="title" wire:model="title" placeholder="The Ashfall Road" hint="Optional. The number stands in when it is blank." />
            </div>

            <div class="mt-5 grid gap-5 sm:grid-cols-2">
                <x-ui.input
                    label="Date and time"
                    name="scheduled_at"
                    type="datetime-local"
                    wire:model="scheduled_at"
                    :hint="'Times are in '.$timezone.', the campaign timezone.'"
                />
                <x-ui.select label="Status" name="status" wire:model="status">
                    @foreach ($statuses as $option)
                        <option value="{{ $option->value }}">{{ $option->label() }}</option>
                    @endforeach
                </x-ui.select>
            </div>

            <div class="mt-5">
                <x-ui.select label="Who can see this session" name="visibility" wire:model.live="visibility">
                    @foreach ($visibilities as $option)
                        <option value="{{ $option->value }}">{{ $option->label() }}</option>
                    @endforeach
                </x-ui.select>
                <p class="mt-1.5 text-xs text-ink-faint">
                    @if ($visibility === \App\Enums\Visibility::Dm->value)
                        A draft. The party sees no trace of it until you switch this over.
                    @else
                        The party sees the number, title, date, and status. Prep, scenes, secrets, and notes stay yours.
                    @endif
                </p>
            </div>
        </x-ui.card>

        <div class="flex items-center gap-2">
            <x-ui.button type="submit">{{ $isEdit ? 'Save session' : 'Create session' }}</x-ui.button>
            <x-ui.button :href="$isEdit ? $session->url() : route('sessions.index', $campaign)" variant="ghost">Cancel</x-ui.button>
        </div>
    </form>
</div>
