<?php

namespace App\Http\Controllers;

use App\Support\UserGuide\Guide;
use Illuminate\Contracts\View\View;

/** The user guide for the people who work in the ERP every day: how it is laid out, how to find things and how each job is done. */
class UserGuideController extends Controller
{
    public function __invoke(): View
    {
        return view('docs.user-guide', ['nav' => Guide::nav(), 'roles' => Guide::roles()]);
    }
}
