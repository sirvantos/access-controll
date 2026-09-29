import { mount } from '@vue/test-utils';
import { defineComponent } from 'vue';
import { describe, expect, it } from 'vitest';
import { NativeSelect } from '../native-select';

describe('NativeSelect', () => {
    it('renders a native select with options, data-testid on the select, and a focus-visible ring', () => {
        const Harness = defineComponent({
            components: { NativeSelect },
            template: `
                <NativeSelect data-testid="invite-role">
                    <option value="company_admin">Admin</option>
                    <option value="viewer">Viewer</option>
                </NativeSelect>
            `,
        });
        const wrapper = mount(Harness);
        const select = wrapper.get('[data-testid="invite-role"]');
        const className = select.classes().join(' ');

        expect(select.element).toBeInstanceOf(HTMLSelectElement);
        expect(select.findAll('option')).toHaveLength(2);
        expect(select.element.tagName).toBe('SELECT');
        expect(className).toContain('focus-visible');
        expect(className).toContain('ring-ring');
        expect(className).toContain('focus-visible:ring-2');
        expect(className).not.toContain('rounded-full');
    });
});
