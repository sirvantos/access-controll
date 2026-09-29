import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import { Button } from '../button';

describe('Button', () => {
    it('exposes default, destructive, and outline variants', () => {
        const defaultButton = mount(Button, { slots: { default: 'Save' } });
        const destructiveButton = mount(Button, {
            props: { variant: 'destructive' },
            slots: { default: 'Revoke' },
        });
        const outlineButton = mount(Button, {
            props: { variant: 'outline' },
            slots: { default: 'Select' },
        });

        expect(defaultButton.classes().join(' ')).toContain('bg-primary');
        expect(defaultButton.classes().join(' ')).toContain('text-primary-foreground');
        expect(destructiveButton.classes().join(' ')).toContain('bg-destructive');
        expect(outlineButton.classes().join(' ')).toContain('border');
        expect(outlineButton.classes().join(' ')).not.toContain('bg-primary');
    });

    it('uses a visible focus-visible ring token and is not pill-shaped', () => {
        const wrapper = mount(Button, {
            attrs: { 'data-testid': 'sign-in' },
            slots: { default: 'Sign in' },
        });
        const className = wrapper.classes().join(' ');

        expect(className).toContain('focus-visible');
        expect(className).toContain('ring-ring');
        expect(className).toContain('focus-visible:ring-2');
        expect(className).not.toContain('rounded-full');
        expect(wrapper.element.tagName).toBe('BUTTON');
        expect(wrapper.attributes('data-testid')).toBe('sign-in');
    });
});
