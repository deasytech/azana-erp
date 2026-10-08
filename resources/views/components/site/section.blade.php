@props(['tone' => 'white', 'id' => null, 'heading' => null])
<section @if ($id) id="{{ $id }}" @endif @if ($heading) aria-labelledby="{{ $id }}-h" @endif {{ $attributes->class(['section', "tone-{$tone}"]) }}>
    <div class="wrap">{{ $slot }}</div>
</section>
