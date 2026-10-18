import { useSyncExternalStore } from 'react'
import { getLocale, subscribeToLocale, type Locale } from './index.ts'

export function useLocale(): Locale {
  return useSyncExternalStore(subscribeToLocale, getLocale)
}
