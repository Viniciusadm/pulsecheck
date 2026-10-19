import { http } from './client.ts'
import type { DataResponse, MaintenanceWindow, MaintenanceWindowInput, PaginatedResponse } from './types.ts'

export async function listMaintenanceWindows(params: { monitor_id?: number; page?: number } = {}) {
  const response = await http.get<PaginatedResponse<MaintenanceWindow>>('/api/maintenance-windows', { params })

  return response.data
}

export async function createMaintenanceWindow(input: MaintenanceWindowInput) {
  const response = await http.post<DataResponse<MaintenanceWindow>>('/api/maintenance-windows', input)

  return response.data.data
}

export async function updateMaintenanceWindow(id: number, input: MaintenanceWindowInput) {
  const response = await http.put<DataResponse<MaintenanceWindow>>(`/api/maintenance-windows/${id}`, input)

  return response.data.data
}

export async function deleteMaintenanceWindow(id: number) {
  await http.delete(`/api/maintenance-windows/${id}`)
}
