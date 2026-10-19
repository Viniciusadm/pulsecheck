import { http } from './client.ts'
import type { DataResponse, Incident, IncidentNote } from './types.ts'

export async function acknowledgeIncident(id: number) {
  const response = await http.post<DataResponse<Incident>>(`/api/incidents/${id}/acknowledge`)

  return response.data.data
}

export async function listIncidentNotes(id: number) {
  const response = await http.get<DataResponse<IncidentNote[]>>(`/api/incidents/${id}/notes`)

  return response.data.data
}

export async function createIncidentNote(id: number, body: string) {
  const response = await http.post<DataResponse<IncidentNote>>(`/api/incidents/${id}/notes`, { body })

  return response.data.data
}

export async function deleteIncidentNote(incidentId: number, noteId: number) {
  await http.delete(`/api/incidents/${incidentId}/notes/${noteId}`)
}
