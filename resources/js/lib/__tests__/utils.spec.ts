import { describe, expect, it } from 'vitest';
import { cn } from '../utils';

describe('cn', () => {
    it('merges class names', () => {
        expect(cn('flex', 'items-center')).toBe('flex items-center');
    });

    it('lets later Tailwind utilities win on conflict', () => {
        expect(cn('p-2', 'p-4')).toBe('p-4');
        expect(cn('text-slate-500', 'text-slate-900')).toBe('text-slate-900');
    });
});
