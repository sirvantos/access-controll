import { computed, ref, type ComputedRef, type Ref } from 'vue';

export const SELECTED_COMPANY_STORAGE_KEY = 'tenancy.selected_company';

export type SelectedCompany = {
    id: number;
    name: string;
};

const selectedCompany = ref<SelectedCompany | null>(readStoredSelection());
const selectionRejected = ref(false);

export function useSelectedCompany(): {
    selectedCompany: Ref<SelectedCompany | null>;
    hasUsableSelection: ComputedRef<boolean>;
    setSelectedCompany: (value: SelectedCompany) => void;
    clearSelectedCompany: () => void;
    markSelectionRejected: () => void;
} {
    function setSelectedCompany(value: SelectedCompany): void {
        sessionStorage.setItem(SELECTED_COMPANY_STORAGE_KEY, JSON.stringify(value));
        selectedCompany.value = value;
        selectionRejected.value = false;
    }

    function clearSelectedCompany(): void {
        sessionStorage.removeItem(SELECTED_COMPANY_STORAGE_KEY);
        selectedCompany.value = null;
        selectionRejected.value = false;
    }

    function markSelectionRejected(): void {
        selectionRejected.value = true;
    }

    const hasUsableSelection = computed(
        () => selectedCompany.value !== null && !selectionRejected.value,
    );

    return {
        selectedCompany,
        hasUsableSelection,
        setSelectedCompany,
        clearSelectedCompany,
        markSelectionRejected,
    };
}

function readStoredSelection(): SelectedCompany | null {
    const raw = sessionStorage.getItem(SELECTED_COMPANY_STORAGE_KEY);

    if (raw === null) {
        return null;
    }

    try {
        const parsed: unknown = JSON.parse(raw);

        if (
            typeof parsed === 'object' &&
            parsed !== null &&
            'id' in parsed &&
            'name' in parsed &&
            typeof parsed.id === 'number' &&
            typeof parsed.name === 'string'
        ) {
            return { id: parsed.id, name: parsed.name };
        }
    } catch {
        return null;
    }

    return null;
}
