import { http } from './client.ts'
import type { DataResponse, User, UserInput } from './types.ts'

export async function listUsers() {
  const response = await http.get<DataResponse<User[]>>('/api/users')

  return response.data.data
}

export async function createUser(input: UserInput) {
  const response = await http.post<DataResponse<User>>('/api/users', input)

  return response.data.data
}

export async function updateUser(id: number, input: UserInput) {
  const response = await http.put<DataResponse<User>>(`/api/users/${id}`, input)

  return response.data.data
}

export async function deleteUser(id: number) {
  await http.delete(`/api/users/${id}`)
}
