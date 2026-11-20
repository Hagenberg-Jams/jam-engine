<?php

return [
    'labels' => [
        'project' => 'Game',
        'projects' => 'Games',
        'project_lower' => 'game',
        'projects_lower' => 'games',
        'jam' => 'Game Jam',
        'jams' => 'Game Jams',
        'about_project' => 'About the Game',
        'gallery' => 'Screenshots',
        'image' => 'Screenshot',
        'card_cta' => 'Details and Download',
        'latest_cta' => 'View the games of',
        'vote_cta' => 'Vote for the games of',
        'meta_verb' => 'Play',
        'search_placeholder' => 'Search games or teams…',
    ],

    'project' => [
        'sections' => ['info', 'header-image', 'description', 'controls', 'team', 'gallery', 'downloads'],
        'card_meta' => ['players', 'controls', 'platforms'],
        'filters' => [
            ['label' => 'Singleplayer', 'selector' => '.singleplayer'],
            ['label' => 'Multiplayer', 'selector' => '.multiplayer'],
            ['label' => 'Keyboard', 'selector' => '.keyboard'],
            ['label' => 'Mouse', 'selector' => '.mouse'],
            ['label' => 'Gamepad', 'selector' => '.gamepad'],
            ['label' => 'Experimental', 'selector' => '.experimental'],
        ],
    ],

    'awards' => [
        'overall' => 'Overall Winner',
        'jury' => 'Jury Award',
        'people' => "People's Choice",
        'youngblood' => 'Young Blood',
        'risingstars' => 'Rising Stars',
        'masterminds' => 'Masterminds',
    ],

    'features' => [
        'print_sheets' => true,
        'downloads' => true,
    ],

    'submission' => [
        'kind' => 'game',
        'platforms' => ['Win', 'Mac', 'Linux', 'Web'],
        'extensions' => ['zip'],
        'screenshots' => ['min' => 4, 'max' => 8, 'width' => 1920, 'height' => 1080],
        'readme' => [
            'title' => 'Game name',
            'title_hint' => 'Replace "Game name" with the name of your game.',
            'sections' => [
                ['heading' => 'Team', 'required' => true, 'hint' => 'Your team name.'],
                ['heading' => 'Team Members', 'aliases' => ['Members'], 'required' => true, 'hint' => 'One person per line, with the name that should appear on the website.', 'list' => true],
                ['heading' => 'Description', 'required' => true, 'hint' => 'In English: what is the setting or story, and what must the player do to win?'],
                ['heading' => 'Controls', 'required' => true, 'hint' => 'One control per line, for example: - **Movement:** WASD', 'list' => true],
                ['heading' => 'How to Play', 'required' => false, 'hint' => 'Optional: anything else players need to know, for example how to start the game or play it in the browser. Delete this section if you do not need it.'],
            ],
        ],
    ],
];
