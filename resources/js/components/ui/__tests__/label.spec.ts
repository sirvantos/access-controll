import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import { defineComponent, h } from 'vue';
import { Label } from '../label';

describe('Label', () => {
    it('renders a label that associates with a control', () => {
        const Harness = defineComponent({
            setup() {
                return () =>
                    h('div', [
                        h(Label, { for: 'email' }, () => 'Email'),
                        h('input', { id: 'email' }),
                    ]);
            },
        });

        const wrapper = mount(Harness);
        const label = wrapper.get('label');
        const input = wrapper.get('input');

        expect(label.element.tagName).toBe('LABEL');
        expect(label.attributes('for')).toBe('email');
        expect(input.attributes('id')).toBe(label.attributes('for'));
    });
});
