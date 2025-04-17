export interface Command {
    nextExecutionDate: string | null;
    manualExecutionDate: string | null;
    id: number;
    name: string;
    scriptName: string;
    lastExecutionDate: string | null;
    lastStatus: string | null;
    recurrence: string;
    startTime: string;
    endTime: string | null;
    active: boolean;
    size: string;
    total_logs: number;
    details: {
        execution: string;
        api: string;
        period: string;
    };
    interval?: number;
    attemptMax: number;
    status: string | null;
    statusScheduler: 'success' | 'error' | 'warning';
    statusSendEmail: boolean;
    statusCommnand: 'success' | 'error' | null;
}

export interface CommandFormData {
    id: number;
    name: string;
    scriptName: string;
    recurrence: string;
    startTime: string | null;
    endTime: string | null;
    active: boolean;
    interval?: number;
    attemptMax: number;
    statusSendEmail: boolean;
}

export interface CommandOutput {
    output: string;
    errorOutput: string;
    status: string;
}

export interface CommandsResponse {
    commands: Command[];
    schedulerCommand: Command | null;
    versions: {
        manager_version: string;
        Praxedo_articles: string;
        [key: string]: string;
    };
}

export type CommandFormDataValue = string | number | boolean | null;

export interface CommandParameters {
    'start-date'?: string;
    'end-date'?: string;
    'start-time'?: string;
    'end-time'?: string;
    'skip-activities'?: boolean;
    'skip-timeslots'?: boolean;
} 