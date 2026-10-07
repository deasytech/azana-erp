<?php

namespace App\Http\Controllers;

use App\Support\ApiDocs\ApiReference;
use Illuminate\Contracts\View\View;

/** The public reference for the mobile API, for the people building the app. It needs no sign-in: it describes the API, it does not use it. */
class ApiDocsController extends Controller
{
    public function __invoke(): View
    {
        return view('docs.api-reference', ['groups' => ApiReference::groups(), 'base' => url('/api/v1')]);
    }
}
