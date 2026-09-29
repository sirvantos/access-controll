import { createRouter, createWebHistory, type Router, type RouterHistory } from 'vue-router';
import { setCompanyNotSelectedHandler, setUnauthorizedHandler } from '../api/client';
import type { Role } from '../api/types';
import { useCurrentUser } from '../composables/useCurrentUser';
import { useSelectedCompany } from '../composables/useSelectedCompany';
import AcceptInvitationPage from '../pages/AcceptInvitationPage.vue';
import CompaniesPage from '../pages/CompaniesPage.vue';
import CompanyProfilePage from '../pages/CompanyProfilePage.vue';
import CompanyUsersPage from '../pages/CompanyUsersPage.vue';
import ForgotPasswordPage from '../pages/ForgotPasswordPage.vue';
import HomePage from '../pages/HomePage.vue';
import ResetPasswordPage from '../pages/ResetPasswordPage.vue';
import SignInPage from '../pages/SignInPage.vue';

declare module 'vue-router' {
    interface RouteMeta {
        guest?: boolean;
        roles?: Role[];
    }
}

export function createAppRouter(history: RouterHistory = createWebHistory()): Router {
    const router = createRouter({
        history,
        routes: [
            {
                path: '/sign-in',
                component: SignInPage,
                meta: { guest: true },
            },
            {
                path: '/forgot-password',
                component: ForgotPasswordPage,
                meta: { guest: true },
            },
            {
                path: '/reset-password/:token',
                component: ResetPasswordPage,
                meta: { guest: true },
            },
            {
                path: '/invitation/:token',
                component: AcceptInvitationPage,
                meta: { guest: true },
            },
            {
                path: '/',
                component: HomePage,
            },
            {
                path: '/company/users',
                component: CompanyUsersPage,
                meta: { roles: ['company_admin', 'super_admin'] },
            },
            {
                path: '/company',
                component: CompanyProfilePage,
                meta: { roles: ['company_admin', 'viewer', 'super_admin'] },
            },
            {
                path: '/companies',
                component: CompaniesPage,
                meta: { roles: ['super_admin'] },
            },
        ],
    });

    const { currentUser, load, clear } = useCurrentUser();
    const { hasUsableSelection, markSelectionRejected } = useSelectedCompany();
    let resolved = false;
    let resolving = false;

    router.beforeEach(async (to) => {
        if (!resolved) {
            resolved = true;
            resolving = true;

            try {
                await load();
            } finally {
                resolving = false;
            }
        }

        const user = currentUser.value;

        if (user === null && to.meta.guest !== true) {
            return { path: '/sign-in' };
        }

        if (user !== null && to.meta.guest === true) {
            return { path: '/' };
        }

        const roles = to.meta.roles;

        if (user !== null && roles !== undefined && !roles.includes(user.role)) {
            return { path: '/', state: { deniedRole: true } };
        }

        if (
            user !== null &&
            user.role === 'super_admin' &&
            (to.path === '/company' || to.path === '/company/users') &&
            !hasUsableSelection.value
        ) {
            return { path: '/companies' };
        }

        return true;
    });

    setUnauthorizedHandler(() => {
        clear();

        if (resolving || router.currentRoute.value.meta.guest === true) {
            return;
        }

        void router.push({ path: '/sign-in' });
    });

    setCompanyNotSelectedHandler(() => {
        markSelectionRejected();

        if (router.currentRoute.value.path === '/companies') {
            return;
        }

        void router.push({ path: '/companies' });
    });

    return router;
}

export const router = createAppRouter();
