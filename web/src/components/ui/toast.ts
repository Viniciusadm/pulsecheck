import { createContext, useContext } from 'react'

export type ToastTone = 'success' | 'error'

export type ToastContextValue = {
  showToast: (message: string, tone?: ToastTone) => void
}

export const ToastContext = createContext<ToastContextValue>({ showToast: () => {} })

export function useToast(): ToastContextValue {
  return useContext(ToastContext)
}
