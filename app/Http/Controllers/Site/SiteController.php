<?php

namespace App\Http\Controllers\Site;

use App\Domain\Website\Actions\GetPublicCatalogue;
use App\Domain\Website\Actions\GetSiteProfile;
use App\Domain\Website\Models\Listing;
use App\Enums\ListingKind;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Response;

/** The public pages. They only read: prices, stock and the farm's details come from the ERP's own actions. */
class SiteController extends Controller
{
    public function __construct(
        private readonly GetSiteProfile $profile,
        private readonly GetPublicCatalogue $catalogue,
    ) {}

    public function home(): View
    {
        $offer = ($this->catalogue)();

        return view('site.home', ['site' => ($this->profile)(), 'offer' => $offer->groupBy(fn (array $row) => $row['listing']->kind->value)]);
    }

    public function about(): View
    {
        return view('site.about', ['site' => ($this->profile)()]);
    }

    public function operations(): View
    {
        return view('site.operations', ['site' => ($this->profile)()]);
    }

    public function sustainability(): View
    {
        return view('site.sustainability', ['site' => ($this->profile)()]);
    }

    public function products(): View
    {
        return view('site.products', [
            'site' => ($this->profile)(),
            'groups' => ($this->catalogue)()->groupBy(fn (array $row) => $row['listing']->kind->value),
        ]);
    }

    public function section(string $kind): View
    {
        $kind = ListingKind::tryFrom($kind);
        abort_unless($kind && $kind !== ListingKind::Service, 404);

        return view('site.section', [
            'site' => ($this->profile)(),
            'kind' => $kind,
            'section' => config("website.sections.{$kind->value}"),
            'rows' => ($this->catalogue)($kind),
        ]);
    }

    public function product(string $slug): View
    {
        $row = $this->catalogue->find($slug) ?? abort(404);

        return view('site.product', ['site' => ($this->profile)(), 'row' => $row]);
    }

    public function contact(): View
    {
        return view('site.contact', ['site' => ($this->profile)()]);
    }

    public function sitemap(): Response
    {
        $pages = collect(['site.home', 'site.about', 'site.operations', 'site.products', 'site.sustainability', 'site.contact'])->map(fn (string $name) => ['url' => route($name), 'at' => null]);
        $sections = collect(array_keys(config('website.sections')))->map(fn (string $kind) => ['url' => route('site.section', $kind), 'at' => null]);
        $listings = Listing::where('is_published', true)->orderBy('id')->get()->map(fn (Listing $l) => ['url' => route('site.product', $l->slug), 'at' => $l->updated_at]);

        return response()->view('site.sitemap', ['urls' => $pages->concat($sections)->concat($listings)])->header('Content-Type', 'application/xml');
    }

    public function robots(): Response
    {
        $lines = ['User-agent: *', 'Disallow: /admin', 'Disallow: /api/', 'Sitemap: '.route('site.sitemap')];

        return response(implode("\n", $lines)."\n")->header('Content-Type', 'text/plain');
    }
}
