<?php

namespace App\Policies;

use App\Enums\Module;

/** Listings are the offer shown to the public; they can be unpublished or deleted by anyone who can delete in the module. */
class WebsiteListingPolicy extends ModulePolicy
{
    protected function module(): Module
    {
        return Module::Website;
    }
}
