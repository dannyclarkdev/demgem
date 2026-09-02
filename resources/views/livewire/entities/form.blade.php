<div class="mx-auto max-w-4xl">
    <x-ui.page-header :title="$isEdit ? 'Edit '.$entity->name : 'New '.strtolower($type->label())" :eyebrow="$type->plural()" />

    <form wire:submit="save" class="grid gap-6 lg:grid-cols-[1fr_18rem]">
        <div class="space-y-6">
            <x-ui.card>
                <div class="space-y-5">
                    <x-ui.input label="Name" name="name" wire:model="name" required autofocus />
                    <x-ui.markdown-editor label="Body" name="body" wire:model="body" rows="16" :autocomplete-url="$autocompleteUrl" preview-action="previewBody" :preview="$bodyPreview" hint="Markdown. Type [[ or @ to link an entity. Use [[type:Name]] when two entities share a name." />
                    <x-ui.input label="Tags" name="tags" wire:model="tags" placeholder="ally, harbor, rumor" hint="Comma separated." />
                </div>
            </x-ui.card>

            @if ($canEditDmFields)
                <x-ui.card class="border-dm/30">
                    <x-slot:header><x-ui.badge variant="dm" icon="eye-off">GM only</x-ui.badge></x-slot:header>
                    <x-ui.markdown-editor label="GM notes" name="dm_notes" wire:model="dm_notes" rows="8" :autocomplete-url="$autocompleteUrl" preview-action="previewDmNotes" :preview="$dmNotesPreview" hint="Players never see this field." />
                </x-ui.card>
            @endif
        </div>

        <aside class="space-y-5">
            <x-ui.card title="Image">
                <div class="space-y-3" x-data="{ removing: @entangle('removeImage') }">
                    @if ($isEdit && $entity->imageUrl())
                        <img src="{{ $entity->imageUrl('thumb') }}" alt="" class="aspect-square w-full rounded-md border border-line object-cover" :class="removing ? 'opacity-30' : ''">
                        <x-ui.checkbox label="Remove current image" name="removeImage" wire:model="removeImage" x-model="removing" />
                    @endif
                    @if ($image && $image->isPreviewable())
                        <img src="{{ $image->temporaryUrl() }}" alt="" class="aspect-square w-full rounded-md border border-ember/40 object-cover">
                    @endif
                    <x-ui.field label="{{ $isEdit && $entity->imageUrl() ? 'Replace image' : 'Upload image' }}" for="image" :error="$errors->first('image')" hint="PNG, JPG, or WebP up to 5 MB.">
                        <input type="file" id="image" wire:model="image" accept="image/*" class="block w-full text-sm text-ink-muted file:mr-3 file:rounded-md file:border file:border-line-strong file:bg-raised file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-ink hover:file:border-ink-faint">
                    </x-ui.field>
                    <p wire:loading wire:target="image" class="text-xs text-ink-faint">Uploading&hellip;</p>
                </div>
            </x-ui.card>

            @if ($canEditDmFields)
                <x-ui.card>
                    <div class="space-y-5">
                        <div x-data="{ visibility: @entangle('visibility') }">
                            <x-ui.select label="Visibility" name="visibility" wire:model="visibility" x-model="visibility">
                                @foreach ($visibilities as $option)
                                    <option value="{{ $option->value }}">{{ $option->label() }}</option>
                                @endforeach
                            </x-ui.select>
                            <p class="mt-1.5 text-xs text-ink-faint" x-text="{
                                @foreach ($visibilities as $option) '{{ $option->value }}': @js($option->description()), @endforeach
                            }[visibility]"></p>

                            <div x-show="visibility === 'selected'" x-cloak class="mt-3 space-y-1.5">
                                <p class="text-sm font-medium text-ink-muted">Players who can see this</p>
                                @forelse ($viewerOptions as $member)
                                    <label class="flex items-center gap-2 text-sm">
                                        <input type="checkbox" wire:model="viewer_ids" value="{{ $member->user_id }}" class="size-4 rounded border-line-strong bg-canvas text-ember focus:ring-ember/30">
                                        {{ $member->user->name }}
                                    </label>
                                @empty
                                    <p class="text-xs text-ink-faint">No players in the campaign yet.</p>
                                @endforelse
                                @error('viewer_ids.*')<p class="text-sm text-danger">{{ $message }}</p>@enderror
                            </div>
                        </div>

                        <x-ui.select label="Inside" name="parent_id" wire:model="parent_id" hint="Nest under another {{ strtolower($type->label()) }}.">
                            <option value="">None</option>
                            @foreach ($parentOptions as $option)
                                <option value="{{ $option->id }}">{{ $option->name }}</option>
                            @endforeach
                        </x-ui.select>

                        @if ($isCharacter)
                            <div x-data="{ pc: @entangle('is_pc') }" class="space-y-3">
                                <x-ui.checkbox label="Player character" name="is_pc" wire:model="is_pc" x-model="pc" />
                                <div x-show="pc" x-cloak>
                                    <x-ui.select label="Played by" name="player_user_id" wire:model="player_user_id" hint="That player can edit this character.">
                                        <option value="">Unassigned</option>
                                        @foreach ($memberOptions as $member)
                                            <option value="{{ $member->user_id }}">{{ $member->user->name }}</option>
                                        @endforeach
                                    </x-ui.select>
                                </div>
                            </div>
                        @endif
                    </div>
                </x-ui.card>
            @endif

            <div class="flex items-center justify-end gap-2">
                <x-ui.button :href="$isEdit ? $entity->url() : route('entities.index', [$campaign, $type->slug()])" variant="ghost">Cancel</x-ui.button>
                <x-ui.button type="submit" wire:loading.attr="disabled">{{ $isEdit ? 'Save' : 'Create' }}</x-ui.button>
            </div>
        </aside>
    </form>
</div>
