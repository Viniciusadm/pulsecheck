import { http } from './client.ts'
import type { AuditLogEntry, PaginatedResponse } from './types.ts'

export type AuditLogFilters = { page: number; subject_type?: string; user_id?: number }

export async function listAuditLog(filters: AuditLogFilters): Promise<PaginatedResponse<AuditLogEntry>> {
  const response = await http.get<PaginatedResponse<AuditLogEntry>>('/api/audit-log', { params: filters })

  return response.data
}
