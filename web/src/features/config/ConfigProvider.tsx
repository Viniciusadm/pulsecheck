import { useQuery } from '@tanstack/react-query'
import { Fragment, useEffect, useState, type ReactNode } from 'react'
import { getAppConfig } from '../../api/config.ts'
import { queryKeys } from '../../api/queryKeys.ts'
import type { AppConfig } from '../../api/types.ts'
import { ErrorState } from '../../components/ui/ErrorState.tsx'
import { FullPageSpinner } from '../../components/ui/FullPageSpinner.tsx'
import { setLocale, t } from '../../i18n/index.ts'
import { setDisplayTimeZone } from '../../lib/format.ts'
import { ConfigContext, instanceName } from './ConfigContext.ts'

function instanceSignature(config: AppConfig): string {
  return `${config.instance.locale}|${config.instance.timezone}`
}

function useApplyInstanceSettings(config: AppConfig | undefined) {
  const [appliedSignature, setAppliedSignature] = useState<string | null>(null)

  if (config && appliedSignature !== instanceSignature(config)) {
    setAppliedSignature(instanceSignature(config))
    setLocale(config.instance.locale, { notify: false })
    setDisplayTimeZone(config.instance.timezone)
  }
}

export function ConfigProvider({ children }: { children: ReactNode }) {
  const { data: config, isError, refetch } = useQuery({ queryKey: queryKeys.config, queryFn: getAppConfig, staleTime: Infinity })

  useApplyInstanceSettings(config)

  const title = config ? instanceName(config, t('app.name')) : null

  useEffect(() => {
    if (title !== null) {
      document.title = title
    }
  }, [title])

  if (!config) {
    return isError ? (
      <div className="mx-auto flex min-h-screen max-w-md items-center px-4">
        <ErrorState onRetry={() => void refetch()} />
      </div>
    ) : (
      <FullPageSpinner />
    )
  }

  return (
    <ConfigContext.Provider value={config}>
      <Fragment key={instanceSignature(config)}>{children}</Fragment>
    </ConfigContext.Provider>
  )
}
