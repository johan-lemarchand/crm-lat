// Types pour les logs ODF
export interface StepDetails {
    name?: string;
    value?: string | number | boolean;
    status?: string;
    message?: string;
    error?: string;
    timestamp?: string;
    duration?: number;
    [key: string]: unknown;
}

export interface OdfStep {
    id: string;
    name: string;
    status: string;
    time: number | null;
    details: StepDetails;
}

export interface OdfExecution {
    id: number;
    status: string;
    executionTime: number | null;
    createdAt: string;
    step: string | OdfStep[] | null;
    steps: OdfStep[];
    stepStatus: number | null;
    stepsStatus?: number | null;
}

export interface OdfSession {
    id: number;
    sessionId: string;
    status: string | null;
    stepsStatus: string | null;
    executionTime: number | null;
    executionTimePause: number | null;
    createdAt: string;
    lastUpdatedAt: string;
    executionsCount: number;
    statusText: string;
    userName: string;
    executions: OdfExecution[];
}

export interface OdfLog {
    id: number;
    name: string;
    status: string;
    executionTime: number | null;
    executionTimePause: number | null;
    formattedExecutionTime?: string;
    formattedExecutionTimePause?: string;
    createdAt: string;
    executionsCount: number;
    sessionsCount: number;
    totalSteps?: number;
    totalErrors?: number;
    errorRate?: string;
    size?: {
        bytes: number;
        formatted: string;
    };
    sessions: OdfSession[];
}

export interface OdfLogsResponse {
    status: string;
    logs: OdfLog[];
    totalSize?: {
        bytes: number;
        formatted: string;
    };
} 