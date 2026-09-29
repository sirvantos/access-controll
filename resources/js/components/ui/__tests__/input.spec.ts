import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import { Input } from '../input';

describe('Input', () => {
    it('renders a native input with data-testid, a focus-visible ring, and no pill radius', () => {
        const wrapper = mount(Input, {
            attrs: {
                'data-testid': 'email',
                type: 'email',
            },
        });
        const className = wrapper.classes().join(' ');

        expect(wrapper.element).toBeInstanceOf(HTMLInputElement);
        expect(wrapper.attributes('data-testid')).toBe('email');
        expect(wrapper.attributes('type')).toBe('email');
        expect(className).toContain('focus-visible');
        expect(className).toContain('ring-ring');
        expect(className).toContain('focus-visible:ring-2');
        expect(className).not.toContain('rounded-full');
    });
});
