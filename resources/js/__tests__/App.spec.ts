import { flushPromises, mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';
import { createMemoryHistory, createRouter } from 'vue-router';
import App from '../App.vue';

vi.mock('../components/AppHeader.vue', () => ({
    default: {
        name: 'AppHeader',
        template: '<header data-testid="app-header" />',
    },
}));

vi.mock('../components/AppSidebar.vue', () => ({
    default: {
        name: 'AppSidebar',
        template: '<aside data-testid="app-sidebar" />',
    },
}));

async function mountApp(path: string) {
    const router = createRouter({
        history: createMemoryHistory(),
        routes: [
            { path: '/', component: { template: '<div>home</div>' } },
            {
                path: '/sign-in',
                component: { template: '<div>sign-in</div>' },
                meta: { guest: true },
            },
        ],
    });

    await router.push(path);
    await router.isReady();

    const wrapper = mount(App, {
        global: {
            plugins: [router],
        },
    });

    await flushPromises();

    return wrapper;
}

describe('App', () => {
    it('renders the header on a signed-in route and hides it on a guest route', async () => {
        const signedIn = await mountApp('/');
        const guest = await mountApp('/sign-in');

        expect(signedIn.find('[data-testid="app-header"]').exists()).toBe(true);
        expect(signedIn.find('[data-testid="app-sidebar"]').exists()).toBe(true);
        expect(guest.find('[data-testid="app-header"]').exists()).toBe(false);
        expect(guest.find('[data-testid="app-sidebar"]').exists()).toBe(false);
    });

    it('uses theme chrome tokens instead of zinc-on-white utilities', async () => {
        const wrapper = await mountApp('/');
        const rootClass = wrapper.element.className;
        const mainClass = wrapper.get('main').classes().join(' ');

        expect(rootClass).toContain('bg-background');
        expect(rootClass).toContain('text-foreground');
        expect(rootClass).not.toContain('bg-white');
        expect(rootClass).not.toContain('text-zinc-900');
        expect(mainClass).toContain('bg-card');
        expect(mainClass).toContain('border');
        expect(mainClass).toContain('rounded-[var(--radius)]');
        expect(mainClass).not.toMatch(/\bshadow-(md|lg|xl|2xl)\b/);
    });
});
