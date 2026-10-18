import { isAxiosError } from 'axios'
import { fetchCsrfCookie, http } from './client.ts'
import type { DataResponse, User } from './types.ts'

export type LoginInput = { email: string; password: string }

export type ResetPasswordInput = {
  token: string
  email: string
  password: string
  password_confirmation: string
}

export async function fetchCurrentUser(): Promise<User | null> {
  try {
    const response = await http.get<DataResponse<User>>('/api/user')

    return response.data.data
  } catch (error) {
    if (isAxiosError(error) && error.response?.status === 401) {
      return null
    }

    throw error
  }
}

export async function login(input: LoginInput): Promise<User> {
  await fetchCsrfCookie()
  const response = await http.post<DataResponse<User>>('/api/auth/login', input)

  return response.data.data
}

export async function logout(): Promise<void> {
  await http.post('/api/auth/logout')
}

export async function requestPasswordReset(email: string): Promise<void> {
  await fetchCsrfCookie()
  await http.post('/api/auth/forgot-password', { email })
}

export async function resetPassword(input: ResetPasswordInput): Promise<void> {
  await fetchCsrfCookie()
  await http.post('/api/auth/reset-password', input)
}
