import { http } from './client.ts'
import type { ApiToken, ApiTokenInput, DataResponse, TwoFactorSetup, TwoFactorStatus, User } from './types.ts'

export type ProfileInput = { name: string; email: string; current_password?: string }

export type PasswordInput = { current_password: string; password: string; password_confirmation: string }

export async function updateProfile(input: ProfileInput): Promise<User> {
  const response = await http.put<DataResponse<User>>('/api/me/profile', input)

  return response.data.data
}

export async function updatePassword(input: PasswordInput): Promise<void> {
  await http.put('/api/me/password', input)
}

export async function getTwoFactorStatus(): Promise<TwoFactorStatus> {
  const response = await http.get<DataResponse<TwoFactorStatus>>('/api/me/two-factor')

  return response.data.data
}

export async function startTwoFactorSetup(currentPassword: string): Promise<TwoFactorSetup> {
  const response = await http.post<DataResponse<TwoFactorSetup>>('/api/me/two-factor', { current_password: currentPassword })

  return response.data.data
}

export async function confirmTwoFactor(code: string): Promise<string[]> {
  const response = await http.post<DataResponse<{ recovery_codes: string[] }>>('/api/me/two-factor/confirm', { code })

  return response.data.data.recovery_codes
}

export async function disableTwoFactor(currentPassword: string): Promise<void> {
  await http.delete('/api/me/two-factor', { data: { current_password: currentPassword } })
}

export async function regenerateRecoveryCodes(currentPassword: string): Promise<string[]> {
  const response = await http.post<DataResponse<{ recovery_codes: string[] }>>('/api/me/two-factor/recovery-codes', { current_password: currentPassword })

  return response.data.data.recovery_codes
}

export async function listApiTokens(): Promise<ApiToken[]> {
  const response = await http.get<DataResponse<ApiToken[]>>('/api/me/tokens')

  return response.data.data
}

export async function createApiToken(input: ApiTokenInput): Promise<{ token: ApiToken; plainTextToken: string }> {
  const response = await http.post<DataResponse<ApiToken> & { plain_text_token: string }>('/api/me/tokens', input)

  return { token: response.data.data, plainTextToken: response.data.plain_text_token }
}

export async function revokeApiToken(id: number): Promise<void> {
  await http.delete(`/api/me/tokens/${id}`)
}
