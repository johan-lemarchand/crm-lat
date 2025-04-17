export interface ProgressNotification {
    currentStep: string;
    progress: number;
    status: 'pending' | 'success' | 'error';
    messages: Array<{
        type: 'error' | 'success' | 'warning';
        message: string;
        title?: string;
        content?: string;
        status: string;
        showMailto?: boolean;
        isCreationError?: boolean;
    }>;
    details?: Array<{
        ligne: string;
        article: {
            code: string;
            designation: string;
            coupon: string | null;
            eligible: boolean;
        };
        quantite: number;
        serie: {
            numero: string;
            status: 'success' | 'error';
            message: string;
            message_api: string | null;
            manufacturerModel: string | null;
        };
        date_end_subs: string;
        partDescription: string;
        estimate_start_date: string;
    }>;
}