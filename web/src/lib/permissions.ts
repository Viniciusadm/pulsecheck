import type { User } from '../api/types.ts'

export function isAdmin(user: User | null): boolean {
  return user?.role === 'admin'
}

export function canManageResources(user: User | null): boolean {
  return user?.role === 'admin' || user?.role === 'manager'
}

export function canViewEverything(user: User | null): boolean {
  return isAdmin(user) || user?.can_view_all_monitors === true
}
