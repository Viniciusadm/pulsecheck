import { http } from './client.ts'
import type { DashboardSummary, DataResponse, Incident } from './types.ts'

export async function getDashboardSummary() {
  const response = await http.get<DataResponse<DashboardSummary>>('/api/dashboard/summary')

  return response.data.data
}

export async function listRecentIncidents(limit = 5) {
  const response = await http.get<DataResponse<Incident[]>>('/api/incidents', { params: { limit } })

  return response.data.data
}
