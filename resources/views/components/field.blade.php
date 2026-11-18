@props(['name', 'label', 'type' => 'text', 'value' => null, 'required' => false, 'hint' => null, 'full' => false, 'attrs' => []])
<div class="field {{ $full ? 'full' : '' }}">
    <label for="f-{{ $name }}">{{ $label }}@if($required) <span class="req">*</span>@endif</label>
    @if($type === 'textarea')
        <textarea class="input @error($name) is-invalid @enderror" id="f-{{ $name }}" name="{{ $name }}" {{ $attributes->except(['class']) }}>{{ old($name, $value) }}</textarea>
    @elseif($type === 'select')
        <select class="input @error($name) is-invalid @enderror" id="f-{{ $name }}" name="{{ $name }}" {{ $attributes->except(['class']) }}>{{ $slot }}</select>
    @else
        <input class="input @error($name) is-invalid @enderror" id="f-{{ $name }}" type="{{ $type }}" name="{{ $name }}" value="{{ old($name, $value) }}" {{ $attributes->except(['class']) }}>
    @endif
    @error($name)<div class="err">{{ $message }}</div>@enderror
    @if($hint)<div class="hint">{{ $hint }}</div>@endif
</div>
