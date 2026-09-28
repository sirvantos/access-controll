import { afterEach, describe, expect, it } from 'vitest';
import en from '../../locales/en.json';
import ru from '../../locales/ru.json';
import { t } from '../i18n';

afterEach(() => {
    document.documentElement.lang = '';
});

describe('t', () => {
    it('looks up a key', () => {
        document.documentElement.lang = 'en';

        expect(t('app.name')).toBe(en.app.name);
    });

    it('replaces parameters', () => {
        document.documentElement.lang = 'en';

        const name = placeholderName(en.errors.generic);
        const value = 'export';

        expect(t('errors.generic', { [name]: value })).toBe(
            en.errors.generic.replaceAll(`{${name}}`, value),
        );
    });

    it('chooses the locale from the html lang attribute', () => {
        document.documentElement.lang = 'ru';

        const name = placeholderName(ru.errors.generic);
        const value = 'export';

        expect(t('app.name')).toBe(ru.app.name);
        expect(t('errors.generic', { [name]: value })).toBe(
            ru.errors.generic.replaceAll(`{${name}}`, value),
        );
    });

    it('falls back to english for an unknown locale', () => {
        document.documentElement.lang = 'de';

        expect(t('app.name')).toBe(en.app.name);
    });

    it('returns the key when it is missing', () => {
        document.documentElement.lang = 'en';

        expect(t('missing.key')).toBe('missing.key');
    });

    it('keeps the same key set in english and russian', () => {
        expect(flatten(en).sort()).toEqual(flatten(ru).sort());
    });
});

function placeholderName(template: string): string {
    const name = template.match(/\{(\w+)\}/)?.[1];

    if (name === undefined) {
        throw new Error('generic error has no placeholder');
    }

    return name;
}

function flatten(value: unknown, prefix = ''): string[] {
    if (typeof value !== 'object' || value === null) {
        return [prefix];
    }

    return Object.entries(value).flatMap(([key, child]) => {
        const next = prefix === '' ? key : `${prefix}.${key}`;

        if (typeof child === 'object' && child !== null) {
            return flatten(child, next);
        }

        return [next];
    });
}
