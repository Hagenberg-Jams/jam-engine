import { expect, test } from '@playwright/test';

const RELEASE = Date.parse('2026-11-20T18:00:00+01:00');

function trackYoutube(page) {
    const requests = [];
    page.on('request', (request) => {
        if (/youtube|ytimg|googlevideo/.test(request.url())) {
            requests.push(request.url());
        }
    });
    return requests;
}

async function mockTopic(page, { before = true } = {}) {
    await page.route('**/api/topic', async (route) => {
        const now = await page.evaluate(() => Date.now());
        if (now < RELEASE) {
            await route.fulfill({ status: 403, json: { released: false, now } });
        } else {
            await route.fulfill({ status: 200, json: { released: true, now, topic: 'Surprise Topic', video: 'revealVideo1' } });
        }
    });
}

test.describe('videos and consent', () => {
    test('loads nothing from YouTube before consent, on a page with several videos', async ({ page }) => {
        const requests = trackYoutube(page);
        await page.goto('/animation/2025/short-film.html');

        await expect(page.locator('.youtube-cookie-placeholder').first()).toBeVisible();
        await expect(page.locator('.youtube-video-container').first()).toBeHidden();
        await expect(page.locator('#cookie-banner')).toBeVisible();
        await page.waitForTimeout(300);

        expect(requests).toEqual([]);
    });

    test('keeps blocking YouTube after a rejection', async ({ page }) => {
        const requests = trackYoutube(page);
        await page.goto('/animation/2025/short-film.html');
        await page.locator('#cookie-reject').click();

        await expect(page.locator('#cookie-banner')).toBeHidden();
        await expect(page.locator('.youtube-cookie-placeholder').first()).toBeVisible();
        expect(requests).toEqual([]);
    });

    test('accepting once enables all videos, and each plays its own ID', async ({ page }) => {
        await page.route(/youtube|ytimg/, (route) => route.fulfill({ status: 200, body: '' }));
        await page.goto('/animation/2025/short-film.html');
        await page.locator('.youtube-accept-cookies').first().click();

        await expect(page.locator('.youtube-video-container')).toHaveCount(2);
        await expect(page.locator('.youtube-video-container').nth(1)).toBeVisible();

        await page.locator('.youtube-placeholder').nth(1).click();
        await expect(page.locator('iframe').nth(1)).toHaveAttribute('src', /youtube-nocookie\.com\/embed\/bbbbbbbbbbb/);
        await expect(page.locator('iframe').nth(0)).not.toHaveAttribute('src', /.+/);
    });

    test('remembers the consent on the next visit', async ({ page }) => {
        await page.route(/youtube|ytimg/, (route) => route.fulfill({ status: 200, body: '' }));
        await page.goto('/animation/2025/short-film.html');
        await page.locator('#cookie-accept').click();
        await page.reload();

        await expect(page.locator('#cookie-banner')).toBeHidden();
        await expect(page.locator('.youtube-video-container').first()).toBeVisible();
    });
});

test.describe('topic release', () => {
    test('counts down and reveals the topic at zero without a reload', async ({ page }) => {
        await page.clock.install({ time: RELEASE - 3000 });
        await mockTopic(page);
        await page.goto('/animation/index.html');

        await expect(page.locator('#topic-countdown')).toBeVisible();
        await expect(page.locator('#topic-revealed')).toBeHidden();
        await expect(page.locator('[data-unit="seconds"]')).toHaveText(/0[1-3]/);
        await expect(page.locator('body')).not.toContainText('Surprise Topic');

        await page.clock.runFor(4000);

        await expect(page.locator('#topic-text')).toHaveText('Surprise Topic');
        await expect(page.locator('#topic-countdown')).toBeHidden();
    });

    test('reveals immediately on a page built before the release but opened after it', async ({ page }) => {
        await page.clock.install({ time: RELEASE + 60000 });
        await mockTopic(page);
        await page.goto('/animation/index.html');

        await expect(page.locator('#topic-text')).toHaveText('Surprise Topic');
    });

    test('corrects a wrong local clock using the server time', async ({ page }) => {
        await page.clock.install({ time: RELEASE + 3600000 });
        await page.route('**/api/topic', (route) => route.fulfill({ status: 403, json: { released: false, now: RELEASE - 3600000 } }));
        await page.goto('/animation/index.html');

        await expect(page.locator('#topic-countdown')).toBeVisible();
        await page.clock.runFor(1500);
        await expect(page.locator('[data-unit="minutes"]')).toHaveText(/59/);
        await expect(page.locator('#topic-revealed')).toBeHidden();
    });

    test('the reveal video respects the consent', async ({ page }) => {
        const requests = trackYoutube(page);
        await page.clock.install({ time: RELEASE + 1000 });
        await mockTopic(page);
        await page.goto('/animation/index.html');

        await expect(page.locator('#topic-video')).toBeVisible();
        await expect(page.locator('#topic-video .youtube-cookie-placeholder')).toBeVisible();
        expect(requests).toEqual([]);

        await page.route(/youtube|ytimg/, (route) => route.fulfill({ status: 200, body: '' }));
        await page.locator('#topic-video .youtube-accept-cookies').click();
        await page.locator('#topic-video .youtube-placeholder').click();
        await expect(page.locator('#topic-video iframe')).toHaveAttribute('src', /embed\/revealVideo1/);
    });
});

test.describe('game site', () => {
    test('filters by category and by search', async ({ page }) => {
        await page.goto('/game/2025.html');
        await expect(page.locator('.project-card')).toHaveCount(1);
        await expect(page.locator('.project-card')).toBeVisible();

        await page.locator('.filter-btn', { hasText: 'Singleplayer' }).click();
        await expect(page.locator('.project-card')).toBeHidden();

        await page.locator('.filter-btn', { hasText: 'All Games' }).click();
        await expect(page.locator('.project-card')).toBeVisible();

        await page.locator('#project-search-input').fill('nothing like this');
        await expect(page.locator('.project-card')).toBeHidden();

        await page.locator('#project-search-clear').click();
        await expect(page.locator('.project-card')).toBeVisible();
    });

    test('the theme toggle switches to dark mode and remembers it', async ({ page }) => {
        await page.goto('/game/index.html');
        const before = await page.evaluate(() => document.documentElement.classList.contains('dark'));

        await page.evaluate(() => window.toggleTheme());
        const after = await page.evaluate(() => document.documentElement.classList.contains('dark'));
        expect(after).toBe(!before);

        await page.reload();
        expect(await page.evaluate(() => document.documentElement.classList.contains('dark'))).toBe(after);
    });

    test('the intro video also waits for consent', async ({ page }) => {
        const requests = trackYoutube(page);
        await page.goto('/game/index.html');
        await expect(page.locator('#youtube-video-section .youtube-cookie-placeholder')).toBeVisible();
        expect(requests).toEqual([]);
    });
});
