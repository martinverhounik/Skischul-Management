@props(['label', 'name', 'type' => 'text', 'required' => false, 'hint' => null])
@php($id = $attributes->get('id', $name))
<div class="flex flex-col gap-1.5">
    <label for="{{ $id }}" class="label-caps">{{ $label }}@if ($required) <span aria-hidden="true">*</span>@endif</label>
    <input id="{{ $id }}" name="{{ $name }}" type="{{ $type }}"
           @if ($required) required @endif
           @error($name) aria-invalid="true" aria-describedby="{{ $id }}-error" @enderror
           {{ $attributes->except('id')->class([
               'h-11 rounded-lg border bg-white px-3 text-sm text-ink',
               'border-line' => ! $errors->has($name),
               'border-signal' => $errors->has($name),
           ]) }}>
    @if ($hint)
        <p class="text-xs text-muted">{{ $hint }}</p>
    @endif
    @error($name)
        <p id="{{ $id }}-error" class="text-sm text-signal">{{ $message }}</p>
    @enderror
</div>
