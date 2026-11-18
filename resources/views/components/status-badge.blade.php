@props(['status', 'label' => null])
@php($cfg = config('erapor.status_rapor.'.$status, [$status, 'gray']))
<span class="badge {{ $cfg[1] }}">{{ $label ?? $cfg[0] }}</span>
