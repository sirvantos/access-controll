import { readFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';
import { describe, expect, it } from 'vitest';

const here = dirname(fileURLToPath(import.meta.url));
const jsRoot = join(here, '..');
const css = readFileSync(join(here, '../../css/app.css'), 'utf8');

const inScopeSfcs = [
    'App.vue',
    'components/AppHeader.vue',
    'pages/SignInPage.vue',
    'pages/ForgotPasswordPage.vue',
    'pages/ResetPasswordPage.vue',
    'pages/AcceptInvitationPage.vue',
    'pages/HomePage.vue',
    'pages/CompaniesPage.vue',
    'pages/CompanyUsersPage.vue',
    'pages/CompanyProfilePage.vue',
];

describe('theme forbidden treatments', () => {
    it('does not use pill controls, purple gradients, or zinc-900 primary buttons on in-scope screens', () => {
        const sources = [
            css,
            ...inScopeSfcs.map((relative) => readFileSync(join(jsRoot, relative), 'utf8')),
        ];

        for (const source of sources) {
            expect(source).not.toMatch(/\brounded-full\b/);
            expect(source).not.toMatch(/\bbg-zinc-900\b/);
            expect(source).not.toMatch(/\b(?:from|via|to|bg)-purple(?:-\d+)?\b/);
            expect(source).not.toMatch(/\bbg-gradient-(?:to|from)/);
        }
    });

    it('does not set Instrument Sans, Inter, Roboto, or Arial as the --font-sans primary', () => {
        const declaration = css.match(/--font-sans:\s*([^;]+);/);

        expect(declaration).not.toBeNull();

        const value = declaration?.[1]?.trim() ?? '';

        expect(value).toMatch(/^['"]IBM Plex Sans['"]\s*,\s*sans-serif$/);
        expect(value).not.toContain('Instrument Sans');
        expect(value).not.toMatch(/\bInter\b/);
        expect(value).not.toMatch(/\bRoboto\b/);
        expect(value).not.toMatch(/\bArial\b/);
    });

    it('does not apply a dark theme by default', () => {
        const sources = [
            css,
            ...inScopeSfcs.map((relative) => readFileSync(join(jsRoot, relative), 'utf8')),
        ];

        for (const source of sources) {
            expect(source).not.toMatch(/class=["']dark["']/);
            expect(source).not.toMatch(/\.dark\s*\{/);
            expect(source).not.toContain('@custom-variant dark');
            expect(source).not.toMatch(/(?:^|[\s{;])dark:/);
        }
    });
});
