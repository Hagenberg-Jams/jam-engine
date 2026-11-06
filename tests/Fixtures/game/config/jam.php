<?php

return [
    'type' => 'game',
    'website' => 'https://example.test',
    'email' => 'game@example.test',
    'latest_jam' => '2025',
    'registration' => [
        'active' => true,
        'form' => [
            'enabled' => true,
            'team_size' => [1, 3],
            'onsite_capacity' => 10,
            'fields' => ['discord' => true, 'programs' => true],
            'programs' => ['Game Design', 'Software Engineering'],
            'notify' => ['orga@example.test'],
            'from' => 'noreply@example.test',
            'turnstile_site_key' => '1x00000000000000000000AA',
            'access' => ['team' => 'jams', 'aud' => 'aud-tag'],
        ],
    ],
];
