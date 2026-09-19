// @vitest-environment node
import { afterEach, describe, expect, test, vi } from 'vitest';
import { execFileSync } from 'child_process';

vi.mock('child_process', () => ({ execFileSync: vi.fn() }));
vi.mock('@playwright/test', () => ({ request: { newContext: vi.fn() } }));

afterEach(() => {
    vi.unstubAllEnvs();
    vi.resetAllMocks();
    vi.resetModules();
});

describe('E2E setup log privacy', () => {
    test.each([1, 2])('hides credentials when Docker call %i fails', async (failedCall) => {
        const marker = 'synthetic-secret-for-log-regression';
        vi.stubEnv('JZSA_E2E_SKIP_SETUP', '0');
        vi.stubEnv('JZSA_E2E_CONNECTED_JWT', marker);
        vi.stubEnv('JZSA_E2E_DB_PASS', marker);
        const childError = Object.assign(new Error(`Command failed: docker -e JWT=${marker}`), {
            stdout: Buffer.from(marker),
            stderr: Buffer.from(marker),
        });
        let calls = 0;
        vi.mocked(execFileSync).mockImplementation(() => {
            if (++calls === failedCall) throw childError;
            return Buffer.from('');
        });

        const { default: setup } = await import('../e2e/global-setup');
        const error = await setup().catch((failure) => failure);
        expect(error).toBeInstanceOf(Error);
        expect(error.message).toContain(failedCall === 1 ? 'Failed to seed' : 'Failed to reset');
        expect(error.message).not.toContain(marker);
        expect(error.cause).toBeUndefined();
        expect(execFileSync).toHaveBeenCalledTimes(failedCall);
        for (const [, args, options] of vi.mocked(execFileSync).mock.calls) {
            expect(args.join(' ')).toContain(marker);
            expect(options.stdio).toEqual(['ignore', 'pipe', 'pipe']);
        }
    });
});
