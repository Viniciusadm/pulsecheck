import type { ReactNode } from 'react'

type Tone = 'error' | 'success' | 'info'

const toneClasses: Record<Tone, string> = {
  error: 'border-status-down/30 bg-status-down-soft text-red-800',
  success: 'border-status-up/30 bg-status-up-soft text-green-900',
  info: 'border-primary-200 bg-primary-50 text-primary-900',
}

export function Alert({ tone = 'info', children }: { tone?: Tone; children: ReactNode }) {
  return (
    <div role={tone === 'error' ? 'alert' : 'status'} className={`rounded-md border px-3 py-2.5 text-sm ${toneClasses[tone]}`}>
      {children}
    </div>
  )
}
