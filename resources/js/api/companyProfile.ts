import { listTimeZones } from './adminCompanies';
import type { WorkingDaySettings } from './adminCompanies';
import { apiRequest } from './client';
import type { DataEnvelope } from './types';

export { listTimeZones };
export type { WorkingDaySettings };

export type CompanyProfile = {
    id: number;
    name: string;
    time_zone: string;
    bin: string | null;
    contact_person: string | null;
    phone: string | null;
    email: string | null;
};

export type UpdateCompanyProfileInput = {
    name: string;
    time_zone: string;
    bin: string;
    contact_person: string;
    phone: string;
    email: string;
};

export async function showCompanyProfile(): Promise<DataEnvelope<CompanyProfile>> {
    return apiRequest<DataEnvelope<CompanyProfile>>('/api/v1/company');
}

export async function updateCompanyProfile(
    input: UpdateCompanyProfileInput,
): Promise<DataEnvelope<CompanyProfile>> {
    return apiRequest<DataEnvelope<CompanyProfile>>('/api/v1/company', {
        method: 'PATCH',
        body: JSON.stringify(input),
    });
}

export async function showWorkingDaySettings(): Promise<DataEnvelope<WorkingDaySettings>> {
    return apiRequest<DataEnvelope<WorkingDaySettings>>('/api/v1/company/working-day-settings');
}

export async function updateWorkingDaySettings(
    input: WorkingDaySettings,
): Promise<DataEnvelope<WorkingDaySettings>> {
    return apiRequest<DataEnvelope<WorkingDaySettings>>('/api/v1/company/working-day-settings', {
        method: 'PATCH',
        body: JSON.stringify(input),
    });
}
