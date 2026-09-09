<?php

namespace App\Livewire\Entities;

use App\Actions\Entities\CreateEntityTemplate;
use App\Actions\Entities\DeleteEntityTemplate;
use App\Actions\Entities\UpdateEntityTemplate;
use App\Enums\EntityType;
use App\Livewire\Concerns\InteractsWithCampaign;
use App\Models\Campaign;
use App\Models\EntityTemplate;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithPagination;

class Templates extends Component
{
    use InteractsWithCampaign, WithPagination;

    #[Locked]
    public ?string $editingId = null;

    public string $name = '';

    public string $type = 'character';

    public string $body = '';

    public function mount(Campaign $campaign): void
    {
        $this->enterCampaign($campaign);
        $this->authorize('viewAny', [EntityTemplate::class, $campaign]);
    }

    public function edit(string $templateId): void
    {
        $template = EntityTemplate::query()->find($templateId) ?? abort(404);
        $this->authorize('update', $template);
        $this->editingId = $template->id;
        $this->name = $template->name;
        $this->type = $template->type->value;
        $this->body = $template->body ?? '';
        $this->resetValidation();
    }

    public function cancel(): void
    {
        $this->reset('editingId', 'name', 'type', 'body');
        $this->resetValidation();
    }

    public function save(CreateEntityTemplate $create, UpdateEntityTemplate $update): void
    {
        $this->authorize('create', [EntityTemplate::class, $this->campaign]);
        $this->name = trim($this->name);
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:120'],
            'type' => ['required', Rule::enum(EntityType::class)],
            'body' => ['nullable', 'string', 'max:100000'],
        ]);

        if ($this->editingId !== null) {
            $template = EntityTemplate::query()->find($this->editingId) ?? abort(404);
            $this->authorize('update', $template);
            $update->handle($template, $validated);
        } else {
            $create->handle($this->campaign, EntityType::from($validated['type']), $validated['name'], $validated['body']);
        }

        $this->cancel();
        $this->resetPage();
        session()->flash('status', 'Template saved.');
    }

    public function delete(string $templateId, DeleteEntityTemplate $delete): void
    {
        $template = EntityTemplate::query()->find($templateId) ?? abort(404);
        $this->authorize('delete', $template);
        $delete->handle($template);

        if ($this->editingId === $templateId) {
            $this->cancel();
        }

        $this->resetPage();
    }

    public function render(): View
    {
        $this->authorize('viewAny', [EntityTemplate::class, $this->campaign]);

        return view('livewire.entities.templates', [
            'templates' => EntityTemplate::query()->orderBy('type')->orderBy('name')->orderBy('id')
                ->paginate(25, ['id', 'type', 'name']),
            'types' => EntityType::cases(),
        ])->title('Entity templates');
    }
}
