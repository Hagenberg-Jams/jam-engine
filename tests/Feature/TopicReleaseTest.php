<?php

declare(strict_types=1);

const SECRET_TOPIC = 'SECRET-TOPIC-XYZ';
const SECRET_VIDEO = 'secretVideo1';
const SECRETS = ['JAM_TOPIC' => SECRET_TOPIC, 'JAM_TOPIC_VIDEO' => SECRET_VIDEO];
const BEFORE = '2026-11-20T17:59:59+01:00';
const AFTER = '2026-11-20T18:00:01+01:00';

it('never deploys the secrets before the release', function (): void {
    $site = buildSite('animation', SECRETS, BEFORE);

    expect(deployedFiles($site))->not->toBeEmpty()
        ->and(deployedContains($site, SECRET_TOPIC))->toBeFalse()
        ->and(deployedContains($site, SECRET_VIDEO))->toBeFalse();
});

it('shows the countdown before the release', function (): void {
    $html = page(buildSite('animation', SECRETS, BEFORE), 'index.html');
    $release = (new DateTimeImmutable('2026-11-20T18:00:00+01:00'))->getTimestamp() * 1000;

    expect($html)->toContain('id="topic-release"')
        ->toContain("data-release=\"{$release}\"")
        ->toContain('data-revealed="false"')
        ->toContain('20 November 2026 at 18:00');
});

it('removes a topic from the data before the release, even if validation were skipped', function (): void {
    $site = fixtureCopy('animation');
    file_put_contents("{$site}/_data/jams/2026.yaml", "title: '2026'\ntopic: 'LEAK'\ntopic_video: 'leakVideo'\ntopic_release: '2026-11-20T18:00:00+01:00'\n");
    file_put_contents("{$site}/probe.php", <<<'PHP'
        <?php
        require getenv('JAM_ENGINE_ROOT') . '/vendor/autoload.php';
        $app = require __DIR__ . '/app/bootstrap.php';
        $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
        echo json_encode(HagenbergJams\JamEngine\JamData::getJam(2026));
        PHP);

    $probe = new Symfony\Component\Process\Process(
        ['php', '-d', 'display_errors=0', 'probe.php'],
        $site,
        ['JAM_ENGINE_ROOT' => dirname(__DIR__, 2), 'JAM_TEST_NOW' => BEFORE, 'JAM_TOPIC' => SECRET_TOPIC],
    );
    $probe->mustRun();
    $jam = json_decode($probe->getOutput(), true);

    expect($jam['title'])->toBe('2026')
        ->and($jam)->not->toHaveKey('topic')
        ->and($jam)->not->toHaveKey('topic_video');
});

it('reports a topic in the data before the release', function (): void {
    $site = fixtureCopy('animation');
    file_put_contents("{$site}/_data/jams/2026.yaml", "title: '2026'\ntopic: 'LEAK'\ntopic_release: '2026-11-20T18:00:00+01:00'\n");

    $process = runHyde($site, 'jam:validate', now: BEFORE);

    expect($process->getExitCode())->toBe(1)
        ->and($process->getOutput() . $process->getErrorOutput())->toContain('remove topic');
});

it('stops the build when the data contains the topic too early', function (): void {
    $site = fixtureCopy('animation');
    file_put_contents("{$site}/_data/jams/2026.yaml", "title: '2026'\ntopic: 'LEAK-TOPIC'\nstartdate: '2026-11-21T09:00:00+01:00'\ntopic_release: '2026-11-20T18:00:00+01:00'\n");

    $process = runHyde($site, 'build --no-interaction', now: BEFORE);

    expect($process->getExitCode())->toBe(1)
        ->and($process->getErrorOutput())->toContain('remove topic')
        ->and(is_file("{$site}/_site/index.html"))->toBeFalse();
});

it('accepts the topic in the data after the release', function (): void {
    $site = fixtureCopy('animation');
    file_put_contents("{$site}/_data/jams/2026.yaml", "title: '2026'\ntopic: 'Public Now'\nstartdate: '2026-11-21T09:00:00+01:00'\nenddate: '2026-11-22T21:00:00+01:00'\ntopic_release: '2026-11-20T18:00:00+01:00'\n");

    expect(runHyde($site, 'jam:validate', now: AFTER)->getExitCode())->toBe(0);
});

it('renders the topic into the page in the first build after the release', function (): void {
    $site = buildSite('animation', SECRETS, AFTER);

    expect(page($site, 'index.html'))->toContain(SECRET_TOPIC)->toContain('data-revealed="true"')->toContain(SECRET_VIDEO)
        ->and(page($site, '2026.html'))->toContain(SECRET_TOPIC);
});

it('does not apply the secret to older jams', function (): void {
    expect(page(buildSite('animation', SECRETS, AFTER), '2025.html'))
        ->toContain('Earlier Topic')
        ->not->toContain(SECRET_TOPIC);
});

it('hides the block after the jam has ended', function (): void {
    expect(page(buildSite('animation', SECRETS, '2026-11-23T12:00:00+01:00'), 'index.html'))->not->toContain('id="topic-release"');
});

it('generates the function configuration from the jam data', function (): void {
    $site = buildSite('animation', SECRETS, BEFORE);
    $config = json_decode((string) file_get_contents("{$site}/functions/api/_topic-config.json"), true);

    expect($config['year'])->toBe(2026)
        ->and((new DateTimeImmutable($config['release']))->getTimestamp())->toBe((new DateTimeImmutable('2026-11-20T18:00:00+01:00'))->getTimestamp())
        ->and(file_exists("{$site}/functions/api/topic.js"))->toBeTrue()
        ->and(file_exists("{$site}/functions/api/_topic-logic.js"))->toBeTrue();
});
