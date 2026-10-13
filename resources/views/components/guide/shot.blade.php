@props(['name', 'caption', 'alt' => null])
{{-- A screenshot of the real app. Until the picture file exists nothing is drawn, so the guide never shows a broken image. --}}
@if (is_file(public_path('images/user-guide/'.$name)))
  <figure class="shot">
    <div class="shot-bar" aria-hidden="true"><i></i><i></i><i></i></div>
    <a href="{{ asset('images/user-guide/'.$name) }}" class="shot-zoom" data-zoom><img src="{{ asset('images/user-guide/'.$name) }}" alt="{{ $alt ?? $caption }}" loading="lazy"></a>
    <figcaption>{{ $caption }}</figcaption>
  </figure>
@endif
