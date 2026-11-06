<?php

declare(strict_types=1);

/** Turns on the registration form of the animation fixture, instead of its external link. */
function withRegistrationForm(array $form = []): callable
{
    return function (string $site) use ($form): void {
        $config = (string) file_get_contents("{$site}/config/jam.php");
        $settings = var_export(array_replace_recursive([
            'enabled' => true,
            'team_size' => [1, 4],
            'onsite_capacity' => 20,
            'fields' => ['discord' => true, 'programs' => true],
            'programs' => ['Digital Arts', 'Media Technology'],
            'notify' => ['orga@example.test'],
            'from' => 'noreply@example.test',
            'turnstile_site_key' => '0xTESTKEY',
            'access' => ['team' => 'jams', 'aud' => 'aud-tag'],
        ], $form), true);
        $config = str_replace("'url' => 'https://example.test/register',", "'url' => '', 'form' => {$settings},", $config);
        file_put_contents("{$site}/config/jam.php", $config);
    };
}

it('builds the form, the edit page and the admin page with the functions', function (): void {
    $site = buildSite('animation', prepare: withRegistrationForm());
    $form = page($site, 'register.html');
    $config = json_decode((string) file_get_contents("{$site}/functions/api/_registration-config.json"), true);

    expect($form)->toContain('id="registration-form"')
        ->toContain('data-sitekey="0xTESTKEY"')
        ->toContain('Media Technology')
        ->toContain('There are 20 seats on site')
        ->toContain('data-max="4"')
        ->and(page($site, 'register/edit.html'))->toContain('data-mode="edit"')->toContain('noindex')
        ->and(page($site, 'admin/registrations.html'))->toContain('Registrations 2026')
        ->and(file_exists("{$site}/functions/api/registration/index.js"))->toBeTrue()
        ->and(file_exists("{$site}/functions/api/admin/_middleware.js"))->toBeTrue()
        ->and($config)->toMatchArray(['year' => 2026, 'capacity' => 20, 'teamSize' => [1, 4], 'siteUrl' => 'https://example.test'])
        ->and($config['purgeAfter'])->toStartWith('2027-02-20')
        ->and($config['fields'])->toMatchArray(['discord' => true, 'programs' => true, 'participation' => true]);
});

it('adds organisers from the secret to the ones in the config', function (): void {
    $site = buildSite('animation', env: ['JAM_REGISTRATION_NOTIFY' => " private@example.test; orga@example.test ,\nthird@example.test"], prepare: withRegistrationForm());
    $config = json_decode((string) file_get_contents("{$site}/functions/api/_registration-config.json"), true);

    expect($config['notify'])->toBe(['orga@example.test', 'private@example.test', 'third@example.test'])
        ->and(page($site, 'register.html'))->not->toContain('private@example.test');
});

it('links the announcement to the form and explains the registration in the privacy section', function (): void {
    $site = buildSite('animation', prepare: withRegistrationForm());
    $index = page($site, 'index.html');
    $imprint = page($site, 'imprint.html');

    expect($index)->toMatch('#href="[^"]*register\.html"#')->not->toContain('https://example.test/register')
        ->and($imprint)->toContain('id="registration"')->toContain('Cloudflare D1')->toContain('Discord usernames')->toContain('90 days');
});

it('passes the link check, which skips the function endpoints', function (): void {
    $site = buildSite('animation', prepare: withRegistrationForm());

    expect(runHyde($site, 'jam:check-links')->getExitCode())->toBe(0);
});

it('keeps the admin and edit pages out of the sitemap', function (): void {
    $sitemap = page(buildSite('animation', prepare: withRegistrationForm()), 'sitemap.xml');

    expect($sitemap)->toContain('/register')->not->toContain('admin/registrations')->not->toContain('register/edit');
});

it('shows the form as closed after the deadline', function (): void {
    $form = page(buildSite('animation', now: '2026-11-16T00:00:00+01:00', prepare: withRegistrationForm()), 'register.html');

    expect($form)->toMatch('#id="registration-closed" class="\s*rounded#')->toMatch('#id="registration-form" class="hidden#');
});

it('builds nothing of it while the form is off', function (): void {
    $site = buildSite('animation');

    expect(file_exists("{$site}/_site/register.html"))->toBeFalse()
        ->and(file_exists("{$site}/functions/api/_registration-config.json"))->toBeFalse()
        ->and(page($site, 'imprint.html'))->not->toContain('id="registration"');
});
