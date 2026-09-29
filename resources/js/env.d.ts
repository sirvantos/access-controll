/// <reference types="vite/client" />

declare module '*.vue' {
    import type { DefineComponent } from 'vue';

    const component: DefineComponent<object, object, unknown>;
    export default component;
}

declare module 'node:fs' {
    export function readFileSync(path: string, encoding: string): string;
}

declare module 'node:path' {
    export function dirname(path: string): string;
    export function join(...paths: string[]): string;
}

declare module 'node:url' {
    export function fileURLToPath(url: string): string;
}
