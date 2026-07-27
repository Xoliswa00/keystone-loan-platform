import { defineConfig, devices } from '@playwright/test';
import 'dotenv/config';

// A trailing slash is required: without one, `new URL('login', baseURL)`
// replaces APP_URL's last path segment ("public") instead of appending to
// it — turning "/Loan/public" + "login" into "/Loan/login" (404), not
// "/Loan/public/login". Doesn't touch the real APP_URL in .env, which
// other parts of the app depend on staying exactly as configured.
const rawBaseURL = process.env.APP_URL ?? 'http://localhost/Loan/public';
const baseURL = rawBaseURL.endsWith('/') ? rawBaseURL : `${rawBaseURL}/`;

/**
 * E2E tests live in ./e2e, separate from Laravel's ./tests (PHPUnit/Pest).
 * The app is served by Apache/XAMPP at APP_URL — no webServer block here
 * since Laravel's DB layer expects the app to run the way it's already
 * configured (Apache + MySQL), not a throwaway `php artisan serve`.
 */
export default defineConfig({
    testDir: './e2e',
    fullyParallel: true,
    forbidOnly: !!process.env.CI,
    retries: process.env.CI ? 2 : 0,
    reporter: 'list',
    use: {
        baseURL,
        trace: 'on-first-retry',
    },
    projects: [
        {
            name: 'chromium',
            use: { ...devices['Desktop Chrome'] },
        },
    ],
});
