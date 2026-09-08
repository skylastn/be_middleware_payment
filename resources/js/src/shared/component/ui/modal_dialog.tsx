import React from 'react';
import { IconX } from './icons';

export interface ModalDialogProps {
    isOpen: boolean;
    title: string;
    description?: string;
    children?: React.ReactNode;
    confirmText?: string;
    cancelText?: string;
    confirmTone?: 'primary' | 'danger' | 'success';
    loading?: boolean;
    maxWidth?: string;
    onConfirm: () => void;
    onClose: () => void;
}

export function ModalDialog({
    isOpen,
    title,
    description,
    children,
    confirmText = 'Confirm',
    cancelText = 'Cancel',
    confirmTone = 'primary',
    loading = false,
    maxWidth,
    onConfirm,
    onClose,
}: ModalDialogProps): React.JSX.Element | null {
    if (!isOpen) return null;

    return (
        <div className="modal-backdrop" onClick={onClose}>
            <div className="modal-card" style={maxWidth ? { maxWidth } : undefined} onClick={(e) => e.stopPropagation()}>
                <div className="modal-header">
                    <div className="modal-title-wrap">
                        <h3 className="modal-title">{title}</h3>
                        {description && <p className="modal-description">{description}</p>}
                    </div>
                    <button
                        type="button"
                        className="button ghost-btn"
                        onClick={onClose}
                        disabled={loading}
                        style={{ padding: '6px' }}
                    >
                        <IconX />
                    </button>
                </div>

                {children && <div className="modal-body">{children}</div>}

                <div className="modal-footer">
                    <button
                        type="button"
                        className="button"
                        onClick={onClose}
                        disabled={loading}
                    >
                        {cancelText}
                    </button>
                    <button
                        type="button"
                        className={`button ${confirmTone === 'primary' ? 'primary' : confirmTone === 'danger' ? 'danger' : 'primary'}`}
                        onClick={onConfirm}
                        disabled={loading}
                    >
                        {loading ? 'Processing...' : confirmText}
                    </button>
                </div>
            </div>
        </div>
    );
}
