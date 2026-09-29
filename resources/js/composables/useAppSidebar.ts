import { onMounted, ref, type Ref } from 'vue';

const MD_QUERY = '(min-width: 768px)';

const open = ref(false);
let media: MediaQueryList | null = null;
let started = false;

function syncToViewport(): void {
    if (media === null) {
        return;
    }

    open.value = media.matches;
}

function onMediaChange(): void {
    syncToViewport();
}

function ensureStarted(): void {
    if (started || typeof window === 'undefined') {
        return;
    }

    started = true;

    if (typeof window.matchMedia !== 'function') {
        open.value = true;

        return;
    }

    media = window.matchMedia(MD_QUERY);
    syncToViewport();
    media.addEventListener('change', onMediaChange);
}

export function useAppSidebar(): {
    open: Ref<boolean>;
    toggle: () => void;
    close: () => void;
    openSidebar: () => void;
} {
    onMounted(() => {
        ensureStarted();
    });

    return {
        open,
        toggle,
        close,
        openSidebar,
    };
}

export function toggle(): void {
    ensureStarted();
    open.value = !open.value;
}

export function close(): void {
    open.value = false;
}

export function openSidebar(): void {
    open.value = true;
}

export function resetAppSidebarForTests(): void {
    open.value = false;
    if (media !== null) {
        media.removeEventListener('change', onMediaChange);
        media = null;
    }
    started = false;
}
