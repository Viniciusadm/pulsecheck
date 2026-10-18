export type HeaderRow = { id: number; name: string; value: string }

export function headerRowsToRecord(rows: HeaderRow[]): Record<string, string> {
  return Object.fromEntries(rows.filter((row) => row.name.trim() !== '').map((row) => [row.name.trim(), row.value]))
}
