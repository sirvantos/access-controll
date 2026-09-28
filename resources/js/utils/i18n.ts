import en from '../locales/en.json';
import ru from '../locales/ru.json';

type MessageTree = {
    [key: string]: string | MessageTree;
};

const catalogs: Record<'en' | 'ru', MessageTree> = { en, ru };

export function t(key: string, params?: Record<string, string | number>): string {
    const template = lookup(catalogs[locale()], key);

    if (template === undefined) {
        return key;
    }

    return interpolate(template, params);
}

function locale(): 'en' | 'ru' {
    const lang = document.documentElement.lang.trim().toLowerCase();

    return lang === 'ru' ? 'ru' : 'en';
}

function lookup(messages: MessageTree, key: string): string | undefined {
    const value = key.split('.').reduce<unknown>((node, part) => {
        if (typeof node !== 'object' || node === null || !Object.hasOwn(node, part)) {
            return undefined;
        }

        return (node as MessageTree)[part];
    }, messages);

    return typeof value === 'string' ? value : undefined;
}

function interpolate(template: string, params?: Record<string, string | number>): string {
    if (params === undefined) {
        return template;
    }

    return Object.entries(params).reduce(
        (text, [name, value]) => text.replaceAll(`{${name}}`, String(value)),
        template,
    );
}
