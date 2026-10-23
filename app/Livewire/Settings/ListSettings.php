<?php

namespace App\Livewire\Settings;

use App\Livewire\Concerns\ConfirmsChanges;
use App\Livewire\Concerns\RequiresAdmin;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Shared behaviour for the simple settings lists (sports, skill levels,
 * age groups, meeting points, add-ons, vouchers): add, edit inline, delete
 * when not in use (US-30) and, where it matters, reorder.
 *
 * Each subclass defines the model, its form fields and validation rules.
 * Record ids from the browser are always looked up through the model's
 * school scope, so another school's records can't be touched.
 */
abstract class ListSettings extends Component
{
    use ConfirmsChanges, RequiresAdmin;

    /** Fields of the "add" form. */
    public array $new = [];

    /** Id of the row being edited inline, if any. */
    public ?int $editingId = null;

    /** Fields of the row being edited. */
    public array $edit = [];

    /** @return class-string<Model> */
    abstract protected function model(): string;

    /** Empty form values. */
    abstract protected function blank(): array;

    /** Validation rules for one form; $prefix is "new" or "edit". */
    abstract protected function fieldRules(string $prefix, ?Model $record = null): array;

    /** Field labels for error messages, without prefix. */
    abstract protected function labels(): array;

    /** Model attributes => form values. */
    abstract protected function toForm(Model $record): array;

    /** Validated form values => model attributes. */
    protected function toAttributes(array $form): array
    {
        return $form;
    }

    protected function order(Builder $query): Builder
    {
        return $query->orderBy('name');
    }

    /** Whether rows have a manual order with up/down buttons. */
    protected function sortable(): bool
    {
        return false;
    }

    public function mount(): void
    {
        $this->new = $this->blank();
    }

    #[Computed]
    public function records(): Collection
    {
        return $this->order($this->model()::query())->get();
    }

    public function create(): void
    {
        $data = $this->validateForm('new');
        $attributes = $this->toAttributes($data);

        if ($this->sortable()) {
            $attributes['position'] = (int) $this->model()::query()->max('position') + 1;
        }

        $this->model()::query()->create($attributes);

        $this->new = $this->blank();
        $this->refresh(__('Added.'));
    }

    public function startEditing(int $id): void
    {
        $this->editingId = $id;
        $this->edit = $this->toForm($this->find($id));
        $this->resetErrorBag();
    }

    public function cancelEditing(): void
    {
        $this->reset('editingId', 'edit');
        $this->resetErrorBag();
    }

    public function update(): void
    {
        $record = $this->find((int) $this->editingId);
        $data = $this->validateForm('edit', $record);

        $record->update($this->toAttributes($data));

        $this->cancelEditing();
        $this->refresh();
    }

    public function delete(int $id): void
    {
        $record = $this->find($id);

        try {
            $record->deleteIfUnused();
        } catch (ValidationException $e) {
            $this->refresh(null);
            throw $e;
        }

        $this->refresh(__('Deleted.'));
    }

    public function move(int $id, string $direction): void
    {
        if (! $this->sortable()) {
            return;
        }

        $ids = $this->records->pluck('id')->values();
        $index = $ids->search($id);
        $target = $direction === 'up' ? $index - 1 : $index + 1;

        if ($index === false || $target < 0 || $target >= $ids->count()) {
            return;
        }

        $reordered = $ids->all();
        [$reordered[$index], $reordered[$target]] = [$reordered[$target], $reordered[$index]];

        foreach ($reordered as $position => $recordId) {
            $this->model()::query()->whereKey($recordId)->update(['position' => $position]);
        }

        $this->refresh();
    }

    protected function find(int $id): Model
    {
        return $this->model()::query()->findOrFail($id);
    }

    protected function validateForm(string $prefix, ?Model $record = null): array
    {
        $rules = [];
        foreach ($this->fieldRules($prefix, $record) as $field => $fieldRules) {
            $rules["{$prefix}.{$field}"] = $fieldRules;
        }

        $attributes = [];
        foreach ($this->labels() as $field => $label) {
            $attributes["{$prefix}.{$field}"] = $label;
        }

        return $this->validate($rules, attributes: $attributes)[$prefix];
    }

    protected function refresh(?string $message = ''): void
    {
        unset($this->records);
        if ($message !== null) {
            $this->saved($message === '' ? null : $message);
        }
    }
}
