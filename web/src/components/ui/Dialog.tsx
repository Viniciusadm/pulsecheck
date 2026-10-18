import { useEffect, useId, useRef, type ReactNode } from 'react'
import { createPortal } from 'react-dom'
import { t } from '../../i18n/index.ts'
import { CloseIcon } from './icons.tsx'

type DialogProps = {
  isOpen: boolean
  title: string
  onClose: () => void
  children: ReactNode
  footer?: ReactNode
  isBusy?: boolean
}

const FOCUSABLE_SELECTOR = 'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])'

export function Dialog({ isOpen, title, onClose, children, footer, isBusy = false }: DialogProps) {
  const titleId = useId()
  const panelRef = useRef<HTMLDivElement>(null)
  const onCloseRef = useRef(onClose)
  const isBusyRef = useRef(isBusy)

  useEffect(() => {
    onCloseRef.current = onClose
    isBusyRef.current = isBusy
  })

  const requestClose = () => {
    if (!isBusy) {
      onClose()
    }
  }

  useEffect(() => {
    if (!isOpen) {
      return
    }

    const previouslyFocused = document.activeElement as HTMLElement | null
    panelRef.current?.focus()

    const handleKeyDown = (event: KeyboardEvent) => {
      if (event.key === 'Escape' && !isBusyRef.current) {
        onCloseRef.current()
      }

      if (event.key === 'Tab' && panelRef.current) {
        trapFocus(event, panelRef.current)
      }
    }

    document.addEventListener('keydown', handleKeyDown)

    return () => {
      document.removeEventListener('keydown', handleKeyDown)
      previouslyFocused?.focus()
    }
  }, [isOpen])

  if (!isOpen) {
    return null
  }

  return createPortal(
    <div className="fixed inset-0 z-50 flex items-end justify-center p-4 sm:items-center">
      <div className="absolute inset-0 bg-neutral-900/40" aria-hidden="true" onClick={requestClose} />
      <div
        ref={panelRef}
        role="dialog"
        aria-modal="true"
        aria-labelledby={titleId}
        tabIndex={-1}
        className="relative w-full max-w-md rounded-lg bg-surface p-6 shadow-lg focus:outline-none"
      >
        <div className="mb-4 flex items-start justify-between gap-4">
          <h2 id={titleId} className="text-lg font-semibold text-neutral-900">
            {title}
          </h2>
          <button
            type="button"
            onClick={requestClose}
            disabled={isBusy}
            className="rounded p-1 text-neutral-500 hover:bg-neutral-100 hover:text-neutral-800"
            aria-label={t('common.close')}
          >
            <CloseIcon />
          </button>
        </div>
        <div className="text-neutral-700">{children}</div>
        {footer && <div className="mt-6 flex flex-wrap justify-end gap-2">{footer}</div>}
      </div>
    </div>,
    document.body,
  )
}

function trapFocus(event: KeyboardEvent, panel: HTMLElement) {
  const focusable = Array.from(panel.querySelectorAll<HTMLElement>(FOCUSABLE_SELECTOR))
  const first = focusable[0]
  const last = focusable[focusable.length - 1]

  if (!first || !last) {
    event.preventDefault()
    panel.focus()

    return
  }

  const active = document.activeElement

  if (event.shiftKey && (active === first || active === panel)) {
    event.preventDefault()
    last.focus()
  } else if (!event.shiftKey && (active === last || !panel.contains(active))) {
    event.preventDefault()
    first.focus()
  }
}
