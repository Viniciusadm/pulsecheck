import { http } from './client.ts'
import type { AlertPreview, DataResponse, MessageFormat, NotificationChannel, NotificationChannelInput, NotificationEvent } from './types.ts'

export async function listNotificationChannels() {
  const response = await http.get<DataResponse<NotificationChannel[]>>('/api/notification-channels')

  return response.data.data
}

export async function createNotificationChannel(input: NotificationChannelInput) {
  const response = await http.post<DataResponse<NotificationChannel>>('/api/notification-channels', input)

  return response.data.data
}

export async function updateNotificationChannel(id: number, input: NotificationChannelInput) {
  const response = await http.put<DataResponse<NotificationChannel>>(`/api/notification-channels/${id}`, input)

  return response.data.data
}

export async function deleteNotificationChannel(id: number) {
  await http.delete(`/api/notification-channels/${id}`)
}

export async function getAlertPreview(event: NotificationEvent, messageFormat: MessageFormat) {
  const response = await http.get<DataResponse<AlertPreview>>('/api/notification-channels/preview', {
    params: { event, message_format: messageFormat },
  })

  return response.data.data
}

export async function sendNotificationChannelTest(id: number) {
  const response = await http.post<{ message: string }>(`/api/notification-channels/${id}/test`)

  return response.data.message
}
