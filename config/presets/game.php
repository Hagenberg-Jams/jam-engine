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
];
