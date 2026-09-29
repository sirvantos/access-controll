import { mount } from '@vue/test-utils';
import { readFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';
import { afterEach, describe, expect, it } from 'vitest';
import { createMemoryHistory, createRouter } from 'vue-router';
import { defineComponent } from 'vue';
import type { CurrentUser } from '../api/types';
import { Button } from '../components/ui/button';
import { nativeCheckboxClass } from '../components/ui/checkbox';
import { Input } from '../components/ui/input';
import { NativeSelect } from '../components/ui/native-select';

const here = dirname(fileURLToPath(import.meta.url));
const jsRoot = join(here, '..');

const inScopeSfcs = [
    'App.vue',
    'components/AppHeader.vue',
    'components/AppSidebar.vue',
    'pages/SignInPage.vue',
    'pages/ForgotPasswordPage.vue',
    'pages/ResetPasswordPage.vue',
    'pages/AcceptInvitationPage.vue',
    'pages/HomePage.vue',
    'pages/CompaniesPage.vue',
    'pages/CompanyUsersPage.vue',
    'pages/CompanyProfilePage.vue',
];

const admin: CurrentUser = {
    id: 2,
    email: 'admin@acme.test',
    role: 'company_admin',
    company_id: 3,
};

afterEach(async () => {
    document.documentElement.lang = 'en';
    const { useCurrentUser } = await import('../composables/useCurrentUser');
    useCurrentUser().clear();
});

describe('focus contract', () => {
    it('includes focus-visible plus the ring token on Button default, destructive, and outline', () => {
        const defaultButton = mount(Button, { slots: { default: 'Save' } });
        const destructiveButton = mount(Button, {
            props: { variant: 'destructive' },
            slots: { default: 'Revoke' },
        });
        const outlineButton = mount(Button, {
            props: { variant: 'outline' },
            slots: { default: 'Select' },
        });

        assertFocusVisibleRing(defaultButton.classes().join(' '));
        assertFocusVisibleRing(destructiveButton.classes().join(' '));
        expect(destructiveButton.classes().join(' ')).toContain('bg-destructive');
        assertFocusVisibleRing(outlineButton.classes().join(' '));
    });

    it('includes focus-visible plus the ring token on Input and NativeSelect', () => {
        const input = mount(Input, { attrs: { 'data-testid': 'email' } });
        const Harness = defineComponent({
            components: { NativeSelect },
            template: `
                <NativeSelect data-testid="invite-role">
                    <option value="company_admin">Admin</option>
                </NativeSelect>
            `,
        });
        const select = mount(Harness).get('[data-testid="invite-role"]');

        assertFocusVisibleRing(input.classes().join(' '));
        assertFocusVisibleRing(select.classes().join(' '));
    });

    it('includes focus-visible plus the ring token on the native checkbox class string', () => {
        assertFocusVisibleRing(nativeCheckboxClass);
    });

    it('includes focus-visible plus the ring token on AppHeader controls and sidebar links', async () => {
        const { useCurrentUser } = await import('../composables/useCurrentUser');
        const { openSidebar } = await import('../composables/useAppSidebar');
        const { default: AppHeader } = await import('../components/AppHeader.vue');
        const { default: AppSidebar } = await import('../components/AppSidebar.vue');
        useCurrentUser().currentUser.value = admin;
        openSidebar();

        const router = createRouter({
            history: createMemoryHistory(),
            routes: [
                { path: '/', component: { template: '<div />' } },
                { path: '/sign-in', component: { template: '<div />' } },
                { path: '/company/users', component: { template: '<div />' } },
                { path: '/company', component: { template: '<div />' } },
            ],
        });
        const Shell = defineComponent({
            components: { AppHeader, AppSidebar },
            template: '<div class="flex"><AppSidebar /><AppHeader /></div>',
        });
        const wrapper = mount(Shell, { global: { plugins: [router] } });
        await router.isReady();

        assertFocusVisibleRing(
            wrapper.get('[data-testid="company-users-link"]').classes().join(' '),
        );
        assertFocusVisibleRing(
            wrapper.get('[data-testid="company-profile-link"]').classes().join(' '),
        );
        assertFocusVisibleRing(wrapper.get('[data-testid="sign-out"]').classes().join(' '));
        assertFocusVisibleRing(wrapper.get('[data-testid="sidebar-toggle"]').classes().join(' '));
    });

    it('does not leave bare outline-none on in-scope screens without a ring replacement', () => {
        for (const relative of inScopeSfcs) {
            const source = readFileSync(join(jsRoot, relative), 'utf8');

            for (const className of quotedStrings(source)) {
                if (!hasOutlineNone(className)) {
                    continue;
                }

                expect(className, relative).toMatch(/ring/);
                expect(className, relative).toContain('focus-visible');
            }
        }
    });
});

function assertFocusVisibleRing(className: string): void {
    expect(className).toContain('focus-visible');
    expect(className).toContain('ring-ring');
}

function hasOutlineNone(className: string): boolean {
    return /(?:^|[\s:])(?:focus:)?outline-none(?:\s|$)/.test(className);
}

function quotedStrings(source: string): string[] {
    const matches = source.matchAll(/(['"`])((?:\\.|(?!\1)[\s\S])*?)\1/g);

    return [...matches].map((match) => match[2] ?? '');
}
