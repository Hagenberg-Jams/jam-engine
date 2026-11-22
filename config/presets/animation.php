<?php

return [
    'labels' => [
        'project' => 'Animation',
        'projects' => 'Animations',
        'project_lower' => 'animation',
        'projects_lower' => 'animations',
        'jam' => 'Animation Jam',
        'jams' => 'Animation Jams',
        'about_project' => 'About the Animation',
        'gallery' => 'Images',
        'image' => 'Image',
        'video' => 'Watch',
        'card_cta' => 'Watch',
        'latest_cta' => 'View the animations of',
        'vote_cta' => 'Vote for the animations of',
        'meta_verb' => 'Watch',
    ],

    'project' => [
        'sections' => ['video', 'description', 'team', 'gallery'],
    ],

    'submission' => [
        'kind' => 'video',
        'platforms' => [],
        'extensions' => ['mp4', 'mov'],
        'max_size_mb' => 10240,
        'screenshots' => ['min' => 3, 'max' => 8, 'width' => 1920, 'height' => 1080],
        'readme' => [
            'title' => 'Animation title',
            'title_hint' => 'Replace "Animation title" with the title of your animation.',
            'sections' => [
                ['heading' => 'Team', 'required' => true, 'hint' => 'Your team name.'],
                ['heading' => 'Team Members', 'aliases' => ['Members'], 'required' => true, 'hint' => 'One person per line, with the name that should appear on the website.', 'list' => true],
                ['heading' => 'Description', 'required' => true, 'hint' => 'In English: what is your animation about?'],
                ['heading' => 'Award Categories', 'required' => false, 'hint' => '1 Categorie in each line. Like Category 1, Category 2', 'list' => true]
                ['heading' => 'Credits', 'required' => false, 'hint' => 'Optional: music, sounds or other material you did not make yourselves, with its source and licence.'],
            ],
        ],
    ],
];
