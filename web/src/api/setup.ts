import { fetchCsrfCookie, http } from './client.ts'
import type { DataResponse, SetupInput, User } from './types.ts'

export async function completeSetup(input: SetupInput): Promise<User> {
  await fetchCsrfCookie()
  const response = await http.post<DataResponse<User>>('/api/setup', input)

  return response.data.data
}
