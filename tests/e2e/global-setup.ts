import { request } from '@playwright/test';

const BASE_URL = process.env.PLAYWRIGHT_BASE_URL ?? 'http://127.0.0.1:8080';

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

export default async function globalSetup() {
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
}
