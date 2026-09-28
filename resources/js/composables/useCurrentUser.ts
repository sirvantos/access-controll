import { ref, type Ref } from 'vue';
import { fetchCurrentUser } from '../api/auth';
import { ApiError } from '../api/client';
import type { CurrentUser } from '../api/types';

const currentUser = ref<CurrentUser | null>(null);

export function useCurrentUser(): {
    currentUser: Ref<CurrentUser | null>;
    load: () => Promise<void>;
    clear: () => void;
} {
    async function load(): Promise<void> {
        try {
            currentUser.value = await fetchCurrentUser();
        } catch (error) {
            if (error instanceof ApiError && error.status === 401) {
                currentUser.value = null;

                return;
            }

            throw error;
        }
    }

    function clear(): void {
        currentUser.value = null;
    }

    return { currentUser, load, clear };
}
