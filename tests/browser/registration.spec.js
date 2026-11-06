import { expect, test } from '@playwright/test';

test.beforeEach(async ({ page }) => {
    await page.route('https://challenges.cloudflare.com/**', (route) => route.abort());
});

test('registers a team with a nickname and shows the placement', async ({ page }) => {
    let sent = null;
    await page.route('**/api/registration', (route) => {
        if (route.request().method() === 'GET') {
            return route.fulfill({ json: { open: true, capacity: 10, seats_left: 0 } });
        }
        sent = route.request().postDataJSON();
        return route.fulfill({ status: 201, json: { placement: 'waitlist' } });
    });
    await page.goto('/game/register.html');

    await expect(page.locator('#seats-left')).toContainText('All seats on site are taken');
    await page.fill('#team_name', 'Night Owls');
    await page.fill('#contact_email', 'lead@example.org');
    await page.check('input[name=programs][value="Game Design"]');
    const people = page.locator('.member');
    await expect(people).toHaveCount(2);
    await people.nth(0).locator('[data-field=real_name]').fill('Ann Real');
    await people.nth(0).locator('[data-field=nickname]').fill('Annie');
    await people.nth(0).locator('[data-field=show_as][value=nick]').check();
    await people.nth(1).locator('[data-field=real_name]').fill('Bo Real');
    await page.locator('#add-member').click();
    await expect(people).toHaveCount(3);
    await expect(page.locator('#add-member')).toBeHidden();
    await people.nth(2).locator('.remove-member').click();
    await page.check('input[name=wish][value=onsite_only]');
    await page.check('input[name=consent_privacy]');
    await page.check('input[name=consent_names]');
    await page.locator('#registration-form button[type=submit]').click();

    await expect(page.locator('#registration-message')).toContainText('waiting list');
    await expect(page.locator('#registration-form')).toBeHidden();
    expect(sent).toMatchObject({
        team_name: 'Night Owls',
        programs: ['Game Design'],
        wish: 'onsite_only',
        consent_privacy: true,
        members: [{ real_name: 'Ann Real', nickname: 'Annie', show_as: 'nick' }, { real_name: 'Bo Real', show_as: 'real' }],
    });
});

test('explains validation errors from the server', async ({ page }) => {
    await page.route('**/api/registration', (route) => (route.request().method() === 'GET'
        ? route.fulfill({ json: { open: true, capacity: 10, seats_left: 5 } })
        : route.fulfill({ status: 422, json: { error: 'validation', fields: ['members.1.real_name', 'consent_names'] } })));
    await page.goto('/game/register.html');
    await page.locator('#registration-form button[type=submit]').click();

    await expect(page.locator('#registration-errors')).toContainText('Person 2: please enter the real name.');
    await expect(page.locator('#registration-errors')).toContainText('names may appear on the website');
    await expect(page.locator('#registration-form')).toBeVisible();
});

test('hides the form when the server says registration is closed', async ({ page }) => {
    await page.route('**/api/registration', (route) => route.fulfill({ json: { open: false, capacity: 10, seats_left: 5 } }));
    await page.goto('/game/register.html');

    await expect(page.locator('#registration-closed')).toBeVisible();
    await expect(page.locator('#registration-form')).toBeHidden();
});

test('loads, changes and cancels a registration with the link', async ({ page }) => {
    const registration = {
        team_name: 'Night Owls', contact_email: 'lead@example.org', programs: ['Software Engineering'], wish: 'remote',
        comment: '', placement: 'remote', status: 'confirmed',
        members: [{ real_name: 'Ann Real', nickname: 'Annie', show_as: 'nick', discord: 'ann' }],
    };
    const calls = [];
    await page.route('**/api/registration/self', (route) => {
        const method = route.request().method();
        calls.push({ method, auth: route.request().headers().authorization });
        if (method === 'GET') {
            return route.fulfill({ json: { registration, open: true } });
        }
        if (method === 'PUT') {
            return route.fulfill({ json: { registration: { ...registration, ...route.request().postDataJSON(), placement: 'remote' } } });
        }
        return route.fulfill({ json: { status: 'cancelled' } });
    });
    await page.goto('/game/register/edit.html#secret-token');

    await expect(page.locator('#team_name')).toHaveValue('Night Owls');
    await expect(page.locator('.member [data-field=nickname]')).toHaveValue('Annie');
    await expect(page.locator('input[name=wish][value=remote]')).toBeChecked();
    await expect(page.locator('input[name=programs][value="Software Engineering"]')).toBeChecked();
    await expect(page.locator('input[name=consent_privacy]')).toHaveCount(0);

    await page.fill('#team_name', 'Night Owls 2');
    await page.locator('#registration-form button[type=submit]').click();
    await expect(page.locator('#registration-message')).toContainText('Your changes are saved');

    await page.locator('#cancel-registration').click();
    await page.locator('#cancel-confirm').click();
    await expect(page.locator('#registration-message')).toContainText('cancelled');
    expect(calls.map((call) => call.method)).toEqual(['GET', 'PUT', 'DELETE']);
    expect(calls.every((call) => call.auth === 'Bearer secret-token')).toBe(true);
});

test('lists registrations for the organisers and confirms one', async ({ page }) => {
    const actions = [];
    const member = (real, nick, discord) => ({ real_name: real, nickname: nick, show_as: nick ? 'nick' : 'real', discord });
    const data = {
        year: 2025, capacity: 10, seats_taken: 4,
        registrations: [
            { id: 'a', team_name: 'Night Owls', contact_email: 'lead@example.org', programs: [], wish: 'onsite_only', placement: 'onsite',
                status: 'pending', people: 4, comment: 'Hi', created_at: 1, members: [member('Ann Real', 'Annie', 'ann')] },
            { id: 'b', team_name: 'Early Birds', contact_email: 'b@example.org', programs: [], wish: 'onsite_only', placement: 'waitlist',
                status: 'pending', people: 2, comment: '', created_at: 2, members: [member('Cy Real', '', '')] },
        ],
    };
    await page.route('**/api/admin/registrations', (route) => {
        if (route.request().method() === 'POST') {
            actions.push(route.request().postDataJSON());
            return route.fulfill({ json: { mailed: true } });
        }
        return route.fulfill({ json: data });
    });
    await page.goto('/game/admin/registrations.html');

    await expect(page.locator('#admin-list article')).toHaveCount(2);
    await expect(page.locator('#admin-list')).toContainText('Ann Real → shown as Annie · Discord: ann');
    await expect(page.locator('#admin-list')).toContainText('Next on the waiting list, and 6 seats are free.');
    await expect(page.locator('#admin-summary')).toContainText('waiting list');

    await page.locator('#admin-list article').first().getByRole('button', { name: 'Confirm' }).click();
    await expect.poll(() => actions.length).toBe(1);
    expect(actions[0]).toEqual({ action: 'confirm', id: 'a', notify: true });
});
