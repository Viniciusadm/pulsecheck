import { http } from './client.ts'
import type { DataResponse, InstanceSettings } from './types.ts'

export async function updateInstance(input: InstanceSettings) {
  const response = await http.put<DataResponse<InstanceSettings>>('/api/instance', input)

  return response.data.data
}
