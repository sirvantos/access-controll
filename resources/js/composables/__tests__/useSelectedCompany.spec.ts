import { afterEach, describe, expect, it, vi } from 'vitest';
import { SELECTED_COMPANY_STORAGE_KEY } from '../useSelectedCompany';

afterEach(() => {
    sessionStorage.clear();
    vi.resetModules();
});

describe('useSelectedCompany', () => {
    it('keeps two stored selections from mixing', async () => {
        const { useSelectedCompany } = await import('../useSelectedCompany');
        const first = useSelectedCompany();
        first.setSelectedCompany({ id: 3, name: 'Acme' });

        expect(first.selectedCompany.value).toEqual({ id: 3, name: 'Acme' });
        expect(sessionStorage.getItem(SELECTED_COMPANY_STORAGE_KEY)).toBe(
            JSON.stringify({ id: 3, name: 'Acme' }),
        );

        first.setSelectedCompany({ id: 4, name: 'Globex' });

        expect(first.selectedCompany.value).toEqual({ id: 4, name: 'Globex' });
        expect(first.selectedCompany.value?.name).not.toContain('Acme');
        expect(sessionStorage.getItem(SELECTED_COMPANY_STORAGE_KEY)).toBe(
            JSON.stringify({ id: 4, name: 'Globex' }),
        );
    });

    it('keeps stored company after a rejected selection and restores it on a new select', async () => {
        const { useSelectedCompany } = await import('../useSelectedCompany');
        const store = useSelectedCompany();
        store.setSelectedCompany({ id: 3, name: 'Acme' });
        store.markSelectionRejected();

        expect(store.hasUsableSelection.value).toBe(false);
        expect(store.selectedCompany.value).toEqual({ id: 3, name: 'Acme' });
        expect(sessionStorage.getItem(SELECTED_COMPANY_STORAGE_KEY)).toBe(
            JSON.stringify({ id: 3, name: 'Acme' }),
        );

        store.setSelectedCompany({ id: 4, name: 'Globex' });

        expect(store.hasUsableSelection.value).toBe(true);
        expect(store.selectedCompany.value).toEqual({ id: 4, name: 'Globex' });
    });
});
