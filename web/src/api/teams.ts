import { http } from './client.ts'
import type { DataResponse, Team, TeamInput } from './types.ts'

export async function listTeams() {
  const response = await http.get<DataResponse<Team[]>>('/api/teams')

  return response.data.data
}

export async function getTeam(id: number) {
  const response = await http.get<DataResponse<Team>>(`/api/teams/${id}`)

  return response.data.data
}

export async function createTeam(input: TeamInput) {
  const response = await http.post<DataResponse<Team>>('/api/teams', input)

  return response.data.data
}

export async function updateTeam(id: number, input: TeamInput) {
  const response = await http.put<DataResponse<Team>>(`/api/teams/${id}`, input)

  return response.data.data
}

export async function deleteTeam(id: number) {
  await http.delete(`/api/teams/${id}`)
}
