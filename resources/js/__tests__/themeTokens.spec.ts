import { readFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';
import { describe, expect, it } from 'vitest';

const here = dirname(fileURLToPath(import.meta.url));
const css = readFileSync(join(here, '../../css/app.css'), 'utf8');
const appTs = readFileSync(join(here, '../app.ts'), 'utf8');
const viteConfig = readFileSync(join(here, '../../../vite.config.ts'), 'utf8');

describe('theme typeface tokens', () => {
    it('sets --font-sans to IBM Plex Sans with a generic sans-serif fallback', () => {
        const declaration = css.match(/--font-sans:\s*([^;]+);/);

        expect(declaration).not.toBeNull();

        const value = declaration?.[1]?.trim() ?? '';

        expect(value).toMatch(/^['"]IBM Plex Sans['"]\s*,\s*sans-serif$/);
        expect(value).not.toContain('Instrument Sans');
        expect(value).not.toMatch(/\bInter\b/);
        expect(value).not.toMatch(/\bRoboto\b/);
        expect(value).not.toMatch(/\bArial\b/);
        expect(value).not.toContain('system-ui');
        expect(value).not.toContain('ui-sans-serif');
    });

    it('loads latin and cyrillic IBM Plex Sans weights 400, 500, and 600', () => {
        for (const subset of ['latin', 'cyrillic']) {
            for (const weight of [400, 500, 600]) {
                expect(appTs).toContain(`@fontsource/ibm-plex-sans/${subset}-${weight}.css`);
            }
        }
    });

    it('does not load Instrument Sans from Vite', () => {
        expect(viteConfig).not.toContain('Instrument Sans');
        expect(viteConfig).not.toContain('bunny(');
    });
});

describe('theme semantic tokens', () => {
    it('imports Tailwind CSS v4 and tw-animate-css', () => {
        expect(css).toMatch(/@import\s+['"]tailwindcss['"]/);
        expect(css).toMatch(/@import\s+['"]tw-animate-css['"]/);
    });

    it('aliases semantic tokens to the Tailwind palette from constraints.md', () => {
        expect(css).toMatch(/--background:\s*var\(--color-slate-50\)/);
        expect(css).toMatch(/--card:\s*var\(--color-white\)/);
        expect(css).toMatch(/--foreground:\s*var\(--color-slate-900\)/);
        expect(css).toMatch(/--muted-foreground:\s*var\(--color-slate-500\)/);
        expect(css).toMatch(/--primary:\s*var\(--color-blue-600\)/);
        expect(css).toMatch(/--ring:\s*var\(--color-blue-600\)/);
        expect(css).toMatch(/--destructive:\s*var\(--color-red-600\)/);
        expect(css).toMatch(/--success:\s*var\(--color-emerald-600\)/);
    });

    it('sets control radius to 0.5rem and does not keep the CLI 0.625rem default', () => {
        const declaration = css.match(/--radius:\s*([^;]+);/);

        expect(declaration).not.toBeNull();
        expect(declaration?.[1]?.trim()).toBe('0.5rem');
        expect(css).not.toMatch(/--radius:\s*0\.625rem/);
    });

    it('does not apply a dark theme by default', () => {
        expect(css).not.toMatch(/\.dark\s*\{/);
        expect(css).not.toContain('@custom-variant dark');
        expect(css).not.toMatch(/(?:^|[\s{;])dark:/);
    });
});
