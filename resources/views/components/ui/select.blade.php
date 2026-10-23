@props(['label', 'name', 'options' => [], 'required' => false])
@php($id = $attributes->get('id', $name))
<div class="flex flex-col gap-1.5">
    <label for="{{ $id }}" class="label-caps">{{ $label }}@if ($required) <span aria-hidden="true">*</span>@endif</label>
    <select id="{{ $id }}" name="{{ $name }}"
            @if ($required) required @endif
            @error($name) aria-invalid="true" aria-describedby="{{ $id }}-error" @enderror
            {{ $attributes->except('id')->class([
                'h-11 rounded-lg border bg-white px-3 text-sm text-ink',
                'border-line' => ! $errors->has($name),
                'border-signal' => $errors->has($name),
            ]) }}>
        @foreach ($options as $value => $text)
            <option value="{{ $value }}">{{ $text }}</option>
        @endforeach
    </select>
    @error($name)
        <p id="{{ $id }}-error" class="text-sm text-signal">{{ $message }}</p>
    @enderror
</div>
