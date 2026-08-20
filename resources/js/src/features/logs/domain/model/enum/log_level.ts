export type LogLevel =
    | 'ALL'
    | 'EMERGENCY'
    | 'ALERT'
    | 'CRITICAL'
    | 'ERROR'
    | 'WARNING'
    | 'NOTICE'
    | 'INFO'
    | 'DEBUG';

export const ALL_LOG_LEVELS: string[] = [
    'EMERGENCY',
    'ALERT',
    'CRITICAL',
    'ERROR',
    'WARNING',
    'NOTICE',
    'INFO',
    'DEBUG',
];

export const LOG_LEVEL_OPTIONS: Array<{ label: string; value: string }> = [
    { label: 'All Levels', value: 'ALL' },
    { label: 'Emergency', value: 'EMERGENCY' },
    { label: 'Alert', value: 'ALERT' },
    { label: 'Critical', value: 'CRITICAL' },
    { label: 'Error', value: 'ERROR' },
    { label: 'Warning', value: 'WARNING' },
    { label: 'Notice', value: 'NOTICE' },
    { label: 'Info', value: 'INFO' },
    { label: 'Debug', value: 'DEBUG' },
];
