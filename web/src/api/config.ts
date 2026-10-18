import { http } from './client.ts'
import type { AppConfig, DataResponse } from './types.ts'

export async function getAppConfig() {
  const response = await http.get<DataResponse<AppConfig>>('/api/config')

  return response.data.data
}
