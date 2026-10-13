@props(['items'])
{{-- A menu path such as: Sales › Invoices --}}
<span class="path" aria-label="Menu path">
  @foreach ((array) $items as $item)
    <span class="path-item">{{ $item }}</span>@if (! $loop->last)<span class="path-sep" aria-hidden="true">&rsaquo;</span>@endif
  @endforeach
</span>
