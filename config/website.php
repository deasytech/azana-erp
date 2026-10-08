<?php

return [
    // The host the public site answers on (www.azanafarms.com) and the host of the ERP (erp.azanafarms.com). Leave empty to serve both
    // from any host, for example on a developer machine. Same codebase, same database: only the address differs.
    'host' => env('WEBSITE_HOST'),
    'erp_host' => env('ERP_HOST'),

    // Printed on the logo itself, so it is known; shown in the footer. Empty to hide.
    'subsidiary_of' => "The Torch & Toucher's (TT&T) Farms Limited",

    // Photographs. Drop a file named as below (.jpg, .jpeg, .webp or .png) into public/images/site/ and it appears; until then a plain
    // brand panel is shown. Nothing here is a stock photo: use pictures of the farm itself.
    'images' => [
        'hero' => 'Wide shot of the farm or healthy pigs, landscape, at least 2000px wide',
        'about' => 'Farm workers with pigs, portrait or square',
        'livestock' => 'Pigs in a pen',
        'breeding' => 'Breeding stock or boars',
        'feed' => 'Feed mill or feed being prepared',
        'meat' => 'Packed pork or the processing room',
        'management' => 'Staff recording on a tablet or phone',
        'sustainability' => 'The farm and its surroundings',
    ],

    // Wording of the public pages. Only things the farm does as described in the ERP specification: no figures, certificates or places.
    'operations' => [
        ['icon' => 'heart', 'image' => 'livestock', 'title' => 'Livestock Production', 'text' => 'Pigs raised in managed groups from birth to market, with their health, growth and feed recorded along the way.'],
        ['icon' => 'dna', 'image' => 'breeding', 'title' => 'Breeding & Genetics', 'text' => 'Planned matings and recorded bloodlines, with boar semen collected, tested and released only after quality control.'],
        ['icon' => 'sprout', 'image' => 'feed', 'title' => 'Feed & Nutrition', 'text' => 'Feed made and managed on the farm, so what the pigs eat is known, measured and consistent.'],
        ['icon' => 'package', 'image' => 'meat', 'title' => 'Meat & Processing', 'text' => 'Slaughter, chilling and packing handled with care, and every cut traceable back to the animal.'],
        ['icon' => 'bar-chart', 'image' => 'management', 'title' => 'Farm Management', 'text' => 'One connected system for animals, stock and sales, giving clear visibility and control of the whole operation.'],
    ],

    'values' => [
        ['icon' => 'check', 'title' => 'Quality', 'text' => 'Quality is checked at each stage, from the feed we use to the product we release.'],
        ['icon' => 'leaf', 'title' => 'Responsible Farming', 'text' => 'We manage animals, feed and resources with the long term in mind.'],
        ['icon' => 'tag', 'title' => 'Traceability', 'text' => 'Records link what we sell back to the animal, the feed and the care behind it.'],
        ['icon' => 'monitor', 'title' => 'Innovation', 'text' => 'Modern tools replace guesswork, so decisions are made on facts.'],
        ['icon' => 'heart', 'title' => 'Animal Care', 'text' => 'Health routines, biosecurity and careful handling are part of daily work.'],
        ['icon' => 'shield', 'title' => 'Reliability', 'text' => 'Clear records and structured routines make us dependable to deal with.'],
    ],

    'technology' => [
        ['icon' => 'bar-chart', 'text' => 'Farm management that keeps every part of the operation in one place'],
        ['icon' => 'heart', 'text' => 'Livestock monitoring, from health checks to growth'],
        ['icon' => 'tag', 'text' => 'Traceability from animal to product'],
        ['icon' => 'sprout', 'text' => 'Feed management and raw-material control'],
        ['icon' => 'package', 'text' => 'Inventory that reflects what is actually on the farm'],
        ['icon' => 'trending', 'text' => 'Production visibility for the people running the farm'],
        ['icon' => 'monitor', 'text' => 'Better operational decisions, backed by records'],
    ],

    'sustainability' => [
        ['icon' => 'heart', 'title' => 'Responsible livestock management', 'text' => 'Structured routines for the health, handling and welfare of every animal.'],
        ['icon' => 'trending', 'title' => 'Efficient production', 'text' => 'Measuring feed and growth so inputs are used where they count.'],
        ['icon' => 'droplet', 'title' => 'Resource management', 'text' => 'Tracking stock and consumption, so resources are planned rather than wasted.'],
        ['icon' => 'refresh', 'title' => 'Less waste', 'text' => 'Better records mean fewer losses, from feed store to cold room.'],
        ['icon' => 'leaf', 'title' => 'Sustainable development', 'text' => 'Building a farming business meant to grow steadily and last.'],
    ],

    // Introductions on the section pages. Wording only: prices, stock and rules always come from the ERP.
    'sections' => [
        'pigs' => [
            'title' => 'Live pigs',
            'intro' => 'Weaners, growers and finishers raised on our own feed, and breeding stock from recorded lines. Tell us what you need and we will confirm what is ready and when.',
        ],
        'semen' => [
            'title' => 'Boar semen',
            'intro' => 'Doses collected, tested and released in our own laboratory. Only batches that have passed quality control are offered, and doses are short-lived, so order ahead.',
        ],
        'meat' => [
            'title' => 'Pork and meat products',
            'intro' => 'Fresh pork cuts from our own pigs, slaughtered, chilled and packed on the farm and traceable back to the animal.',
        ],
    ],
];
