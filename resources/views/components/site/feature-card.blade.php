@props(['item'])
<article class="card feature reveal">
    <span class="chip"><x-site.icon :name="$item['icon']" /></span>
    <h3>{{ $item['title'] }}</h3>
    <p>{{ $item['text'] }}</p>
</article>
