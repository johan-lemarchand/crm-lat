export interface CommandFormData {
    name: string;
    scriptName: string;
    recurrence: string;
    interval: number | null;
    attemptMax: number;
    startTime: string | null;
    endTime: string | null;
    active: boolean;
    statusSendEmail: boolean;
}

export interface CommandFormProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
}

export interface CommandResponse {
    message: string;
    command: {
        id: number;
        name: string;
        scriptName: string;
        startTime: string;
        endTime: string | null;
        recurrence: string;
        active: boolean;
        interval: number;
        lastExecutionDate: string | null;
        lastStatus: string | null;
    };
}

export interface Command {
    id: number;
    name: string;
    scriptName: string;
    recurrence: string;
    interval: number;
    attemptMax: number;
    lastExecutionDate: string | null;
    lastStatus: string | null;
    startTime: string | null;
    endTime: string | null;
    active: boolean;
    size: string;
    total_logs: number;
    details: {
        execution: string;
        api: string;
        period: string;
    };
    statusSendEmail: boolean;
}
