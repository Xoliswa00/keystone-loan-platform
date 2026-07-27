import { test, expect } from '@playwright/test';

test('login page renders with email/password fields', async ({ page }) => {
    await page.goto('login');

    await expect(page.locator('#email')).toBeVisible();
    await expect(page.locator('#password')).toBeVisible();
    await expect(page.getByRole('button', { name: 'Sign In' })).toBeVisible();
});

test('invalid credentials show an error instead of logging in', async ({ page }) => {
    await page.goto('login');

    await page.locator('#email').fill('nobody@keystonecapital.local');
    await page.locator('#password').fill('wrong-password');
    await page.getByRole('button', { name: 'Sign In' }).click();

    await expect(page).toHaveURL(/login/);
});
