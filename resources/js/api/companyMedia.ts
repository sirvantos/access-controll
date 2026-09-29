import { apiErrorFrom, attachCompanyContextHeader, notifyApiFailure } from './client';

function mediaUrl(publicId: string): string {
    return `/api/v1/company/media/${publicId}`;
}

export async function fetchCompanyMedia(publicId: string): Promise<ArrayBuffer> {
    const headers = new Headers();
    attachCompanyContextHeader(headers);

    const response = await fetch(mediaUrl(publicId), {
        credentials: 'same-origin',
        headers,
    });

    if (!response.ok) {
        const error = await apiErrorFrom(response);
        notifyApiFailure(error);
        throw error;
    }

    return response.arrayBuffer();
}
