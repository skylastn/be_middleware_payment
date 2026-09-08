import React, { useState } from 'react';
import { IconCheck, IconCopy } from './icons';

export function CopyButton({ text, label }: { text: string; label?: string }): React.JSX.Element {
    const [copied, setCopied] = useState<boolean>(false);

    const copy = (e: React.MouseEvent) => {
        e.stopPropagation();
        if (!text) return;
        navigator.clipboard.writeText(text);
        setCopied(true);
        setTimeout(() => setCopied(false), 1600);
    };

    return (
        <button type="button" className="copy-btn" onClick={copy} title={copied ? 'Copied!' : 'Copy to clipboard'}>
            {copied ? <IconCheck /> : <IconCopy />}
            {label && <span style={{ fontSize: '11px', marginLeft: '4px' }}>{copied ? 'Copied!' : label}</span>}
        </button>
    );
}
