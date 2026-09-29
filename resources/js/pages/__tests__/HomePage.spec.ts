import { flushPromises, mount } from '@vue/test-utils';
import { afterEach, describe, expect, it } from 'vitest';
import { createMemoryHistory, createRouter } from 'vue-router';
import type { CurrentUser } from '../../api/types';
import { t } from '../../utils/i18n';

const viewer: CurrentUser = {
    id: 4,
    email: 'viewer@acme.test',
    role: 'viewer',
    company_id: 3,
};

afterEach(async () => {
    document.documentElement.lang = 'en';
    const { useCurrentUser } = await import('../../composables/useCurrentUser');
    useCurrentUser().clear();
});

describe('HomePage', () => {
    it('shows themed home placeholder copy from the locale key', async () => {
        const { useCurrentUser } = await import('../../composables/useCurrentUser');
        const { default: HomePage } = await import('../HomePage.vue');
        useCurrentUser().currentUser.value = viewer;

        const router = createRouter({
            history: createMemoryHistory(),
            routes: [
                { path: '/', component: HomePage },
                { path: '/companies', component: { template: '<div />' } },
                { path: '/company/users', component: { template: '<div />' } },
            ],
        });
        const wrapper = mount(HomePage, { global: { plugins: [router] } });
        await router.isReady();
        await flushPromises();

        const placeholder = wrapper.get('[data-testid="home-placeholder"]');

        expect(placeholder.text()).toBe(t('home.placeholder'));
        expect(placeholder.classes()).toContain('text-foreground');
        expect(router.currentRoute.value.path).not.toBe('/companies');
        expect(router.currentRoute.value.path).not.toBe('/company/users');
    });
});
