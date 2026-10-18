import { createContext, useContext } from 'react'
import type { User } from '../../api/types.ts'

export type AuthContextValue = {
  user: User | null
  isLoading: boolean
  setUser: (user: User | null) => void
}

export const AuthContext = createContext<AuthContextValue | null>(null)

export const currentUserQueryKey = ['current-user'] as const

export function useAuth(): AuthContextValue {
  const context = useContext(AuthContext)

  if (context === null) {
    throw new Error('useAuth must be used inside AuthProvider')
  }

  return context
}
