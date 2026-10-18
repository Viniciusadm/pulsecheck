import { useId, type ReactNode } from 'react'
import { CloseIcon, GripIcon } from '../../../components/ui/icons.tsx'
import { t } from '../../../i18n/index.ts'

export const DRAG_HANDLE_CLASS = 'widget-drag-handle'

type WidgetFrameProps = {
  title: string
  isEditing: boolean
  canDrag: boolean
  onRemove: () => void
  children: ReactNode
}

export function WidgetFrame({ title, isEditing, canDrag, onRemove, children }: WidgetFrameProps) {
  const titleId = useId()

  return (
    <section
      aria-labelledby={titleId}
      className={`flex h-full flex-col overflow-hidden rounded-lg border bg-surface ${isEditing ? 'border-primary-300 shadow-sm' : 'border-border'}`}
    >
      <header
        className={`flex min-h-11 items-center gap-2 border-b border-border px-4 py-2 ${isEditing && canDrag ? `${DRAG_HANDLE_CLASS} cursor-grab select-none active:cursor-grabbing` : ''}`}
      >
        {isEditing && canDrag && <GripIcon className="shrink-0 text-neutral-400" />}
        <h2 id={titleId} className="min-w-0 flex-1 truncate font-semibold text-neutral-900">
          {title}
        </h2>
        {isEditing && (
          <button
            type="button"
            onClick={onRemove}
            className="rounded p-1 text-neutral-500 hover:bg-neutral-100 hover:text-status-down"
            aria-label={t('dashboard.removeWidget', { name: title })}
          >
            <CloseIcon />
          </button>
        )}
      </header>
      <div className="@container min-h-0 flex-1 overflow-auto p-4">{children}</div>
    </section>
  )
}
