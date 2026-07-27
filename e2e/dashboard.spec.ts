import { test, expect } from '@playwright/test';

/**
 * Uses a dedicated throwaway account (E2E_TEST_EMAIL/PASSWORD in .env) —
 * never a real staff or client login. See database/seeders or the account
 * created directly for this purpose; safe to reset/recreate at any time.
 */
test('client can log in and see their dashboard', async ({ page }) => {
    const email = process.env.E2E_TEST_EMAIL;
    const password = process.env.E2E_TEST_PASSWORD;
    test.skip(!email || !password, 'E2E_TEST_EMAIL/E2E_TEST_PASSWORD not set in .env');

    await page.goto('login');
    await page.locator('#email').fill(email!);
    await page.locator('#password').fill(password!);
    await page.getByRole('button', { name: 'Sign In' }).click();

    await expect(page).toHaveURL(/dashboard/);
    await expect(page.getByText(/Good morning|Good afternoon|Good evening/)).toBeVisible();
    await expect(page.getByRole('link', { name: 'New Loan Application' })).toBeVisible();
});
