import { apiRequest } from './client';
import type { DataEnvelope } from './types';

export type SelectedCompany = {
    id: number;
    name: string;
};

export async function selectCompany(companyId: number): Promise<DataEnvelope<SelectedCompany>> {
    return apiRequest<DataEnvelope<SelectedCompany>>('/api/v1/admin/selected-company', {
        method: 'POST',
        body: JSON.stringify({ company_id: companyId }),
    });
}

export async function clearSelectedCompany(): Promise<{ ok: true }> {
    return apiRequest<{ ok: true }>('/api/v1/admin/selected-company', {
        method: 'DELETE',
    });
}
