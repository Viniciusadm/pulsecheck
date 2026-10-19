import { http } from './client.ts'
import type { DataResponse, MailSettings, MailSettingsInput } from './types.ts'

export async function getMailSettings() {
  const response = await http.get<DataResponse<MailSettings>>('/api/settings/mail')

  return response.data.data
}

export async function updateMailSettings(input: MailSettingsInput) {
  const response = await http.put<DataResponse<MailSettings>>('/api/settings/mail', input)

  return response.data.data
}

export async function deleteMailSettings() {
  await http.delete('/api/settings/mail')
}

export async function sendTestMail() {
  const response = await http.post<{ message: string }>('/api/settings/mail/test')

  return response.data.message
}
