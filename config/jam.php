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

    // Uploads at the end of a jam at /submit, see the README of the engine.
    // It needs a Cloudflare R2 bucket, Turnstile and the secret JAM_SUBMISSION_CODE.
    'submission' => [
        'enabled' => false,
        // Uploads after this time are marked as late. Empty: the end of the latest jam.
        'deadline' => '',
        // From this time on, nothing can be uploaded any more. Empty: open while enabled is true.
        'closes' => '',
        // 'game': a ZIP file for each platform. 'video': one video file. Set by the preset.
        'kind' => 'game',
        'platforms' => ['Win', 'Mac', 'Linux', 'Web'],
        'extensions' => ['zip'],
        'max_size_mb' => 4096,
        // Needed with the first upload of a team. Later uploads may leave them out.
        'screenshots' => ['min' => 4, 'max' => 8, 'width' => 1920, 'height' => 1080],
        // The README.md: a title as the first heading, then the sections. Hints in the template are HTML comments.
        'readme' => [
            'title' => 'Project name',
            'title_hint' => 'Replace "Project name" with the name of your project.',
            'sections' => [
                ['heading' => 'Team', 'required' => true, 'hint' => 'Your team name.'],
                ['heading' => 'Team Members', 'aliases' => ['Members'], 'required' => true, 'hint' => 'One person per line, with the name that should appear on the website.', 'list' => true],
                ['heading' => 'Description', 'required' => true, 'hint' => 'A short description in English.'],
            ],
        ],
        // Empty: the settings of the registration form.
        'turnstile_site_key' => '',
        'notify' => [],
        'from' => '',
        // Cloudflare Access for /admin/submissions, like the admin page of the registration form.
        'access' => [
            'team' => '',
            'aud' => '',
        ],
        'texts' => [
            'intro' => '',
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
