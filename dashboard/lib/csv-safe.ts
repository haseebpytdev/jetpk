const FORMULA_PREFIX = /^[=+\-@]/;

/**
 * Neutralize spreadsheet formula injection by prefixing dangerous cell values.
 * Preserves proper CSV quoting for commas, quotes, line breaks and spaces.
 */
export function escapeCsvCell(value: string | number | null | undefined): string {
  if (value === null || value === undefined) {
    return "";
  }

  let text = String(value);

  if (FORMULA_PREFIX.test(text)) {
    text = `'${text}`;
  }

  if (/[",\r\n]/.test(text) || /^\s|\s$/.test(text)) {
    return `"${text.replace(/"/g, '""')}"`;
  }

  return text;
}

export function buildCsvRow(cells: (string | number | null | undefined)[]): string {
  return cells.map(escapeCsvCell).join(",");
}

export function buildCsvContent(headers: string[], rows: (string | number | null | undefined)[][]): string {
  const lines = [buildCsvRow(headers), ...rows.map((row) => buildCsvRow(row))];
  return lines.join("\r\n");
}
