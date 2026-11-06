<?php

/*
|--------------------------------------------------------------------------
| Jam Engine defaults
|--------------------------------------------------------------------------
|
| The final configuration is merged in this order: these defaults, the preset
| named by `type` (config/presets/), then the site's config/jam.php. Associative
| arrays are merged, and lists replace each other. `awards` is replaced as a whole.
|
*/

return [
    'type' => 'generic',

    'website' => '',
    'email' => '',
    'phone_number' => '',
    'timezone' => 'Europe/Vienna',

    'company' => [
        'name' => '',
        'url' => '',
        'address' => '',
    ],

    // Media file names (stored under _media/). Without a logo, the site name is shown as text.
    'branding' => [
        'logo_dark' => '',
        'logo_light' => '',
    ],

    'social' => [
        'instagram' => '',
        'discord' => '',
        'youtube' => '',
        'github' => '',
    ],

    'latest_jam' => '',

    'voting' => [
        'active' => false,
        'url' => '',
        'deadline' => '',
    ],

    'registration' => [
        'next_jam' => '',
        'active' => false,
        // An external registration page. Leave it empty to use the form on this site.
        'url' => '',
        'deadline' => '',

        // The registration form on this site at /register, see the README of the engine.
        // It needs Cloudflare D1, Turnstile, Access and the Email Service.
        'form' => [
            'enabled' => false,
            'team_size' => [1, 6],
            // Seats on site. null means no limit.
            'onsite_capacity' => null,
            'fields' => [
                // With onsite_capacity: asks what happens if the team does not fit. Everyone can always choose to take part remotely.
                'participation' => true,
                'discord' => false,
                'programs' => false,
                'comment' => true,
            ],
            'programs' => [],
            // Each person chooses one entry, for example the study programme.
            'programs_label' => 'Study programme',
            'nickname_label' => 'Nickname',
            // Organisers who get a mail for each new or changed registration.
            'notify' => [],
            // Sender address, for example noreply@example.org. Its domain needs the Cloudflare Email Service.
            'from' => '',
            // Days after the end of the jam until the registrations are deleted.
            'retention_days' => 90,
            'turnstile_site_key' => '',
            'access' => [
                'team' => '',
                'aud' => '',
            ],
            'texts' => [
                'intro' => '',
                'confirmed' => '',
                'declined' => '',
            ],
        ],
    ],

    'downloads' => [
        // When set, download links use {base_url}/{path}/{year}/{file}.
        'base_url' => null,
        'path' => 'games',
    ],

    'labels' => [
        'project' => 'Project',
        'projects' => 'Projects',
        'project_lower' => 'project',
        'projects_lower' => 'projects',
        'jam' => 'Jam',
        'jams' => 'Jams',
        'about_project' => 'About the Project',
        'gallery' => 'Images',
        'image' => 'Image',
        'video' => 'Video',
        'card_cta' => 'Details',
        'latest_cta' => 'View the projects of',
        'vote_cta' => 'Vote for the projects of',
        'meta_verb' => 'See',
        'search_placeholder' => 'Search name or team…',
    ],

    'project' => [
        // Rendered in this order. Available: info, header-image, video, description, controls, team, gallery, downloads
        'sections' => ['header-image', 'description', 'team', 'gallery'],
        // Badges on project cards. Available: players, controls, platforms
        'card_meta' => [],
        // Each filter has a `label` and a CSS `selector` matched against the project cards.
        'filters' => [],
    ],

    // Keys as used by `winner:` in the project data, in display order.
    'awards' => [],

    'features' => [
        'people' => true,
        'rules' => true,
        'print_sheets' => false,
        'downloads' => false,
        'upcoming_jam' => true,
        'topic_release' => true,
    ],
];
