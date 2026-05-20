import { request } from '@playwright/test';
import { execFileSync } from 'child_process';

const BASE_URL = process.env.PLAYWRIGHT_BASE_URL ?? 'http://127.0.0.1:8080';
const SKIP_SETUP = process.env.JZSA_E2E_SKIP_SETUP === '1' || process.env.JZSA_E2E_SKIP_SETUP === 'true';
const SETUP_SCRIPT = '/var/www/html/wp-content/plugins/janzeman-shared-albums-for-google-photos/tests/e2e/setup-fixtures.php';

const E2E_SETUP_ENV_KEYS = [
    'JZSA_E2E_ALBUM_URL',
    'JZSA_E2E_ADMIN_USER',
    'JZSA_E2E_ADMIN_PASS',
    'JZSA_E2E_CONNECTED_USER',
    'JZSA_E2E_CONNECTED_PASS',
    'JZSA_E2E_CONNECTED_JWT',
    'JZSA_E2E_DISCONNECTED_USER',
    'JZSA_E2E_DISCONNECTED_PASS',
    'WP_ROOT',
];

const ADMIN_USER = process.env.JZSA_E2E_ADMIN_USER ?? 'dev';
const ADMIN_PASS = process.env.JZSA_E2E_ADMIN_PASS ?? 'test123';
const DISCONNECTED_USER = process.env.JZSA_E2E_DISCONNECTED_USER ?? 'testuser-noc';
const DISCONNECTED_PASS = process.env.JZSA_E2E_DISCONNECTED_PASS ?? 'testpass123';

interface FixtureSpec {
    slug: string;
    selector: string;
    expectedMin: number;
    description: string;
}

const FIXTURES: FixtureSpec[] = [
    {
        slug: 'lightbox-fixture',
        selector: 'class="jzsa-album',
        expectedMin: 6,
        description: '6 albums (lightbox/fullscreen tests)',
    },
    {
        slug: 'slideshow-fixture',
        selector: 'class="jzsa-album',
        expectedMin: 3,
        description: '3 albums (slideshow tests)',
    },
    {
        slug: 'gallery-fixture',
        selector: 'jzsa-gallery-album',
        expectedMin: 4,
        description: '4 gallery albums (gallery tests)',
    },
    {
        slug: 'mosaic-fixture',
        selector: 'jzsa-gallery-wrapper',
        expectedMin: 4,
        description: '4 mosaic wrappers (mosaic tests)',
    },
    {
        slug: 'info-fixture',
        selector: 'class="jzsa-album',
        expectedMin: 4,
        description: '4 albums (info-overlay and metadata tests)',
    },
    {
        slug: 'feature-fixture',
        selector: 'class="jzsa-album',
        expectedMin: 4,
        description: '4 albums (navigation/feature tests)',
    },
    {
        slug: 'video-fixture',
        selector: 'data-album-title="JZSA E2E Video Album"',
        expectedMin: 2,
        description: '2 cached video albums (video tests)',
    },
];

function runFixtureSetup(): void {
    if (SKIP_SETUP) {
        return;
    }

    const args = ['compose', 'exec', '-T'];
    for (const key of E2E_SETUP_ENV_KEYS) {
        const value = process.env[key];
        if (value !== undefined && value !== '') {
            args.push('-e', `${key}=${value}`);
        }
    }
    args.push('wordpress', 'php', SETUP_SCRIPT);

    try {
        execFileSync('docker', args, {
            cwd: process.cwd(),
            stdio: 'inherit',
            env: process.env,
        });
    } catch (error) {
        throw new Error(
            'Failed to seed deterministic e2e fixtures with docker compose.\n' +
            'Start the WordPress Docker stack, or set JZSA_E2E_SKIP_SETUP=1 when targeting an already prepared site.\n' +
            `Command: docker ${args.join(' ')}\n` +
            `Original error: ${error instanceof Error ? error.message : String(error)}`
        );
    }
}

async function verifyLogin(baseURL: string, username: string, password: string, label: string): Promise<void> {
    const ctx = await request.newContext({ baseURL });
    try {
        await ctx.post('/wp-login.php', {
            form: {
                log: username,
                pwd: password,
                'wp-submit': 'Log In',
                redirect_to: `${baseURL}/wp-admin/`,
                testcookie: '1',
            },
        });

        const admin = await ctx.get('/wp-admin/');
        const body = await admin.text();
        if (!admin.ok() || !body.includes('id="wpadminbar"')) {
            throw new Error(
                `Seeded ${label} user "${username}" could not log in to ${baseURL}/wp-admin/. ` +
                'Run tests/e2e/setup-fixtures.php or check the JZSA_E2E_* credentials.'
            );
        }
    } finally {
        await ctx.dispose();
    }
}

export default async function globalSetup() {
    runFixtureSetup();

    const ctx = await request.newContext({ baseURL: BASE_URL });

    // Verify WordPress is reachable first.
    try {
        await ctx.get('/');
    } catch {
        throw new Error(
            `Cannot reach WordPress at ${BASE_URL}.\n` +
            'Start the dev server before running e2e tests.'
        );
    }

    for (const fixture of FIXTURES) {
        let res: Awaited<ReturnType<typeof ctx.get>>;
        try {
            res = await ctx.get(`/?pagename=${fixture.slug}`);
        } catch {
            throw new Error(
                `Cannot reach WordPress at ${BASE_URL}.\n` +
                'Start the dev server before running e2e tests.'
            );
        }

        if (res.status() === 404) {
            throw new Error(
                `Fixture page not found: ${BASE_URL}/?pagename=${fixture.slug}\n\n` +
                `Create a published WordPress page with slug "${fixture.slug}" containing\n` +
                `${fixture.description}.\n` +
                'See tests/e2e/README.md for details.'
            );
        }

        if (!res.ok()) {
            throw new Error(`Fixture page "${fixture.slug}" returned unexpected HTTP ${res.status()}.`);
        }

        const body  = await res.text();
        const count = (body.match(new RegExp(fixture.selector, 'g')) ?? []).length;
        if (count < fixture.expectedMin) {
            throw new Error(
                `Fixture page "${fixture.slug}" exists but only ${count}/${fixture.expectedMin} ` +
                `elements matched.\nExpected: ${fixture.description}.\n` +
                'See tests/e2e/README.md for the required shortcode configuration.'
            );
        }
    }

    await ctx.dispose();

    await verifyLogin(BASE_URL, ADMIN_USER, ADMIN_PASS, 'admin');
    await verifyLogin(BASE_URL, DISCONNECTED_USER, DISCONNECTED_PASS, 'disconnected');
}
