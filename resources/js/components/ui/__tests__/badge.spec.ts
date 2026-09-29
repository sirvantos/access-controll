import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import { Badge } from '../badge';

describe('Badge', () => {
    it('uses the success token only on the success variant', () => {
        const success = mount(Badge, {
            props: { variant: 'success' },
            slots: { default: 'Active' },
        });
        const secondary = mount(Badge, {
            props: { variant: 'secondary' },
            slots: { default: 'Deactivated' },
        });
        const outline = mount(Badge, {
            props: { variant: 'outline' },
            slots: { default: 'Revoked' },
        });

        const successClass = success.classes().join(' ');
        const secondaryClass = secondary.classes().join(' ');
        const outlineClass = outline.classes().join(' ');

        expect(successClass).toContain('bg-success');
        expect(secondaryClass).not.toContain('bg-success');
        expect(secondaryClass).not.toContain('text-success');
        expect(outlineClass).not.toContain('bg-success');
        expect(outlineClass).not.toContain('text-success');
        expect(successClass).not.toContain('rounded-full');
    });
});
