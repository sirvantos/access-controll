import { describe, expect, it } from 'vitest';
import { nativeCheckboxClass } from '../checkbox';

describe('nativeCheckboxClass', () => {
    it('includes a focus-visible ring token and is not pill-shaped', () => {
        expect(nativeCheckboxClass).toContain('focus-visible');
        expect(nativeCheckboxClass).toContain('ring-ring');
        expect(nativeCheckboxClass).toContain('focus-visible:ring-2');
        expect(nativeCheckboxClass).not.toContain('rounded-full');
    });
});
