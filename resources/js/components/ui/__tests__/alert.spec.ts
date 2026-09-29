import { mount } from '@vue/test-utils';
import { defineComponent } from 'vue';
import { describe, expect, it } from 'vitest';
import { Alert, AlertDescription } from '../alert';

const DestructiveAlert = defineComponent({
    components: { Alert, AlertDescription },
    template: `
        <Alert variant="destructive">
            <AlertDescription>Invalid invitation.</AlertDescription>
        </Alert>
    `,
});

describe('Alert', () => {
    it('uses the danger token for the destructive variant and shows the description', () => {
        const wrapper = mount(DestructiveAlert);

        const className = wrapper.classes().join(' ');

        expect(className).toContain('text-destructive');
        expect(className).not.toContain('text-success');
        expect(wrapper.find('[data-slot="alert-description"]').text()).toBe('Invalid invitation.');
    });
});
