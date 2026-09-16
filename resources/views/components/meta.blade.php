@props(['label'])
{{-- Satu keterangan bernama — padanan `Meta` prototipe. --}}
<div style="margin-bottom:14px">
  <div class="lbl" style="margin-bottom:3px">{{ $label }}</div>
  <div style="font-size:13px">{{ $slot }}</div>
</div>
