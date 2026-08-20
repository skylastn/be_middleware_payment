import React from 'react';

export interface DataTableProps {
    columns: string[];
    children: React.ReactNode;
}

export function DataTable({ columns, children }: DataTableProps): React.JSX.Element {
    return (
        <div className="table-wrap">
            <table>
                <thead>
                    <tr>
                        {columns.map((column) => (
                            <th key={column}>{column}</th>
                        ))}
                    </tr>
                </thead>
                <tbody>{children}</tbody>
            </table>
        </div>
    );
}
