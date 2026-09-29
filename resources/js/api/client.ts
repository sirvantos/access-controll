import type { FieldErrors } from './types';
import { useCurrentUser } from '../composables/useCurrentUser';
import { useSelectedCompany } from '../composables/useSelectedCompany';

export const COMPANY_CONTEXT_HEADER = 'X-Company-Context';

const CSRF_COOKIE_PATH = '/sanctum/csrf-cookie';

const JSON_HEADERS = {
    Accept: 'application/json',
    'Content-Type': 'application/json',
} as const;

const MUTATING_METHODS = new Set(['POST', 'PUT', 'PATCH', 'DELETE']);

let csrfReady = false;

let onUnauthorized: (() => void) | null = null;

let onCompanyNotSelected: (() => void) | null = null;

export function setUnauthorizedHandler(handler: () => void): void {
    onUnauthorized = handler;
}

export function setCompanyNotSelectedHandler(handler: () => void): void {
    onCompanyNotSelected = handler;
}

export class ApiError extends Error {
    readonly status: number;

    readonly errorCode: string | null;

    readonly errors: FieldErrors | null;

    constructor(
        status: number,
        message: string,
        errorCode: string | null,
        errors: FieldErrors | null,
    ) {
        super(message);
        this.name = 'ApiError';
        this.status = status;
        this.errorCode = errorCode;
        this.errors = errors;
    }
}

export function isCompanyNotSelectedError(error: unknown): error is ApiError {
    return (
        error instanceof ApiError &&
        error.status === 409 &&
        error.errorCode === 'company_not_selected'
    );
}

export function notifyApiFailure(error: ApiError): void {
    if (error.status === 401) {
        onUnauthorized?.();
    }

    if (isCompanyNotSelectedError(error)) {
        onCompanyNotSelected?.();
    }
}

export async function apiRequest<T>(path: string, init: RequestInit = {}): Promise<T> {
    const method = (init.method ?? 'GET').toUpperCase();

    if (MUTATING_METHODS.has(method)) {
        await ensureCsrfCookie();
    }

    const headers = new Headers(init.headers);
    headers.set('Accept', JSON_HEADERS.Accept);
    headers.set('Content-Type', JSON_HEADERS['Content-Type']);
    attachCompanyContextHeader(headers);

    const token = xsrfToken();

    if (MUTATING_METHODS.has(method) && token !== null) {
        headers.set('X-XSRF-TOKEN', token);
    }

    const response = await fetch(path, {
        ...init,
        method,
        headers,
        credentials: 'same-origin',
    });

    if (!response.ok) {
        const error = await apiErrorFrom(response);
        notifyApiFailure(error);
        throw error;
    }

    if (response.status === 204) {
        return undefined as T;
    }

    return (await response.json()) as T;
}

async function ensureCsrfCookie(): Promise<void> {
    if (csrfReady) {
        return;
    }

    const response = await fetch(CSRF_COOKIE_PATH, {
        credentials: 'same-origin',
        headers: { Accept: JSON_HEADERS.Accept },
    });

    if (!response.ok && response.status !== 204) {
        throw await apiErrorFrom(response);
    }

    csrfReady = true;
}

function xsrfToken(): string | null {
    const cookie = document.cookie.split('; ').find((part) => part.startsWith('XSRF-TOKEN='));

    if (cookie === undefined) {
        return null;
    }

    return decodeURIComponent(cookie.slice('XSRF-TOKEN='.length));
}

export async function apiErrorFrom(response: Response): Promise<ApiError> {
    const body = await readBody(response);
    const message = typeof body.message === 'string' ? body.message : response.statusText;

    return new ApiError(response.status, message, errorCode(body), fieldErrors(body));
}

async function readBody(response: Response): Promise<Record<string, unknown>> {
    try {
        const body: unknown = await response.json();

        if (typeof body === 'object' && body !== null) {
            return body as Record<string, unknown>;
        }
    } catch {
        return {};
    }

    return {};
}

function errorCode(body: Record<string, unknown>): string | null {
    return typeof body.error_code === 'string' ? body.error_code : null;
}

function fieldErrors(body: Record<string, unknown>): FieldErrors | null {
    if (typeof body.errors !== 'object' || body.errors === null) {
        return null;
    }

    const errors: FieldErrors = {};

    for (const [field, messages] of Object.entries(body.errors)) {
        if (!Array.isArray(messages) || messages.some((message) => typeof message !== 'string')) {
            return null;
        }

        errors[field] = messages;
    }

    return errors;
}

export function attachCompanyContextHeader(headers: Headers): void {
    const { currentUser } = useCurrentUser();
    const { selectedCompany } = useSelectedCompany();

    if (currentUser.value?.role !== 'super_admin' || selectedCompany.value === null) {
        return;
    }

    headers.set(COMPANY_CONTEXT_HEADER, String(selectedCompany.value.id));
}
