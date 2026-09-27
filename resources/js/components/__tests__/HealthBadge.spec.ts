import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import HealthBadge from '../HealthBadge.vue';

describe('HealthBadge', () => {
    it('shows the status', () => {
        const wrapper = mount(HealthBadge, { props: { status: 'ok' } });

        expect(wrapper.get('[data-testid="health-status"]').text()).toBe('ok');
    });
});
