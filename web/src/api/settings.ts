import { http } from './client.ts'
import type { DataResponse, NotificationSettings, NotificationSettingsInput } from './types.ts'

export async function getNotificationSettings() {
  const response = await http.get<DataResponse<NotificationSettings>>('/api/settings/notifications')

  return response.data.data
}

export async function updateNotificationSettings(input: NotificationSettingsInput) {
  const response = await http.put<DataResponse<NotificationSettings>>('/api/settings/notifications', input)

  return response.data.data
}

export async function removeTelegram() {
  const response = await http.delete<DataResponse<NotificationSettings>>('/api/settings/notifications/telegram')

  return response.data.data
}

export async function sendTelegramTest() {
  const response = await http.post<{ message: string }>('/api/settings/notifications/telegram/test')

  return response.data.message
}
