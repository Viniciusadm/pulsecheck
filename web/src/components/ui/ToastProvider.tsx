import { useCallback, useEffect, useMemo, useRef, useState, type ReactNode } from 'react'
import { t } from '../../i18n/index.ts'
import { CloseIcon, DownIcon, UpIcon } from './icons.tsx'
import { ToastContext, type ToastTone } from './toast.ts'

type Toast = { id: number; message: string; tone: ToastTone }

const TOAST_DURATION_MS = 5000

export function ToastProvider({ children }: { children: ReactNode }) {
  const [toasts, setToasts] = useState<Toast[]>([])
  const nextId = useRef(0)
  const timers = useRef(new Map<number, number>())

  const dismiss = useCallback((id: number) => {
    window.clearTimeout(timers.current.get(id))
    timers.current.delete(id)
    setToasts((current) => current.filter((toast) => toast.id !== id))
  }, [])

  useEffect(() => {
    const activeTimers = timers.current

    return () => {
      activeTimers.forEach((timer) => window.clearTimeout(timer))
      activeTimers.clear()
    }
  }, [])

  const showToast = useCallback(
    (message: string, tone: ToastTone = 'success') => {
      const id = nextId.current++
      setToasts((current) => [...current.slice(-2), { id, message, tone }])
      timers.current.set(id, window.setTimeout(() => dismiss(id), TOAST_DURATION_MS))
    },
    [dismiss],
  )

  const value = useMemo(() => ({ showToast }), [showToast])

  return (
    <ToastContext.Provider value={value}>
      {children}
      <div
        role="region"
        aria-label={t('common.notifications')}
        className="pointer-events-none fixed inset-x-4 bottom-4 z-50 flex flex-col items-end gap-2 sm:left-auto sm:w-96"
      >
        {toasts.map((toast) => (
          <div
            key={toast.id}
            role={toast.tone === 'error' ? 'alert' : 'status'}
            className="pointer-events-auto flex w-full items-start gap-3 rounded-md bg-neutral-900 px-4 py-3 text-sm text-white shadow-lg"
          >
            <span className={`mt-0.5 text-base ${toast.tone === 'success' ? 'text-green-400' : 'text-red-400'}`}>
              {toast.tone === 'success' ? <UpIcon /> : <DownIcon />}
            </span>
            <p className="flex-1">{toast.message}</p>
            <button
              type="button"
              onClick={() => dismiss(toast.id)}
              aria-label={t('common.dismiss')}
              className="rounded p-0.5 text-neutral-300 hover:text-white"
            >
              <CloseIcon />
            </button>
          </div>
        ))}
      </div>
    </ToastContext.Provider>
  )
}
