@props(['value', 'label', 'icon' => 'users', 'tone' => ''])
<div class="card stat {{ $tone }}">
    <div class="ico">@icon($icon)</div>
    <div><div class="v">{{ $value }}</div><div class="l">{{ $label }}</div></div>
</div>
