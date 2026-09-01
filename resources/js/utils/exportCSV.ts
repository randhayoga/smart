/**
 * Utility to export tabular data to a CSV file.
 *
 * @param headers - Array of header titles
 * @param rows - Array of row data arrays
 * @param filename - Base filename without extension or with .csv
 */
export function exportCSV(
  headers: string[],
  rows: (string | number | null | undefined)[][],
  filename: string = 'export'
): void {
  if (!rows || rows.length === 0) return;

  const escapeCSV = (val: any): string => {
    if (val === null || val === undefined) return '""';
    const str = String(val).replace(/"/g, '""');
    return `"${str}"`;
  };

  const headerLine = headers.map(escapeCSV).join(',');
  const rowLines = rows.map(row => row.map(escapeCSV).join(','));

  const csvContent = '\uFEFFsep=,\n' + headerLine + '\n' + rowLines.join('\n');

  const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
  const url = URL.createObjectURL(blob);
  const link = document.createElement('a');
  link.setAttribute('href', url);

  const cleanFilename = filename.endsWith('.csv') ? filename : `${filename}_${Date.now()}.csv`;
  link.setAttribute('download', cleanFilename);

  document.body.appendChild(link);
  link.click();
  document.body.removeChild(link);
  URL.revokeObjectURL(url);
}
