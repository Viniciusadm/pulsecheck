import { http } from './client.ts'
import type { DataResponse, PublicStatusPage, StatusPage, StatusPageInput } from './types.ts'

export async function listStatusPages() {
  const response = await http.get<DataResponse<StatusPage[]>>('/api/status-pages')

  return response.data.data
}

export async function createStatusPage(input: StatusPageInput) {
  const response = await http.post<DataResponse<StatusPage>>('/api/status-pages', input)

  return response.data.data
}

export async function updateStatusPage(id: number, input: StatusPageInput) {
  const response = await http.put<DataResponse<StatusPage>>(`/api/status-pages/${id}`, input)

  return response.data.data
}

export async function deleteStatusPage(id: number) {
  await http.delete(`/api/status-pages/${id}`)
}

export async function getPublicStatusPage(slug: string) {
  const response = await http.get<DataResponse<PublicStatusPage>>(`/api/public/status-pages/${encodeURIComponent(slug)}`)

  return response.data.data
}
