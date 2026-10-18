import type { ReactNode } from 'react'

type EmptyStateProps = {
  title: string
  description?: string
  action?: ReactNode
}

export function EmptyState({ title, description, action }: EmptyStateProps) {
  return (
    <div className="flex flex-col items-start gap-3 rounded-lg border border-dashed border-neutral-300 px-6 py-10">
      <h2 className="text-lg font-semibold text-neutral-900">{title}</h2>
      {description && <p className="max-w-prose text-neutral-600">{description}</p>}
      {action}
    </div>
  )
}
