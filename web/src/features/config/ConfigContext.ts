import { createContext, useContext } from 'react'
import type { AppConfig } from '../../api/types.ts'

export const ConfigContext = createContext<AppConfig | null>(null)

export function useAppConfig(): AppConfig {
  const config = useContext(ConfigContext)

  if (config === null) {
    throw new Error('useAppConfig must be used inside ConfigProvider')
  }

  return config
}

export function instanceName(config: AppConfig, fallback: string): string {
  return config.instance.name.trim() || fallback
}
