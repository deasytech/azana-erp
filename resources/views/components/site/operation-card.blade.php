@props(['op'])
<article class="card hover reveal">
    <x-site.image :name="$op['image']" :alt="$op['title']" />
    <div class="card-body">
        <span class="chip"><x-site.icon :name="$op['icon']" /></span>
        <h3>{{ $op['title'] }}</h3>
        <p>{{ $op['text'] }}</p>
    </div>
</article>
