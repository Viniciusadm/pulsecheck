import { useSyncExternalStore } from 'react'

export type ThemePreference = 'system' | 'light' | 'dark'

export type Theme = 'light' | 'dark'

export const THEME_PREFERENCES: ThemePreference[] = ['system', 'light', 'dark']

const STORAGE_KEY = 'pulsecheck.theme'
const DARK_QUERY = '(prefers-color-scheme: dark)'
const listeners = new Set<() => void>()

function isThemePreference(value: unknown): value is ThemePreference {
  return value === 'system' || value === 'light' || value === 'dark'
}

function systemPrefersDark(): boolean {
  return typeof window !== 'undefined' && typeof window.matchMedia === 'function' && window.matchMedia(DARK_QUERY).matches
}

export function getThemePreference(): ThemePreference {
  try {
    const stored = window.localStorage.getItem(STORAGE_KEY)

    return isThemePreference(stored) ? stored : 'system'
  } catch {
    return 'system'
  }
}

export function resolveTheme(preference: ThemePreference): Theme {
  if (preference === 'system') {
    return systemPrefersDark() ? 'dark' : 'light'
  }

  return preference
}

export function applyTheme(preference: ThemePreference = getThemePreference()): void {
  document.documentElement.dataset.theme = resolveTheme(preference)
}

function storePreference(preference: ThemePreference): void {
  try {
    window.localStorage.setItem(STORAGE_KEY, preference)
  } catch {
    return
  }
}

export function setThemePreference(preference: ThemePreference): void {
  storePreference(preference)
  applyTheme(preference)
  listeners.forEach((listener) => listener())
}

function subscribe(listener: () => void): () => void {
  listeners.add(listener)

  return () => {
    listeners.delete(listener)
  }
}

export function useThemePreference(): ThemePreference {
  return useSyncExternalStore(subscribe, getThemePreference, () => 'system')
}

export function watchSystemTheme(): () => void {
  if (typeof window.matchMedia !== 'function') {
    return () => {}
  }

  const media = window.matchMedia(DARK_QUERY)
  const handleChange = () => {
    if (getThemePreference() === 'system') {
      applyTheme('system')
    }
  }

  media.addEventListener('change', handleChange)

  return () => media.removeEventListener('change', handleChange)
}
