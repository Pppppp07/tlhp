@props(['j'])
@php $j = $j instanceof \App\Enums\SumberLaporan ? $j->value : $j; @endphp
<span class="sumber sumber-{{ $j }}">{{ $j }}</span>
