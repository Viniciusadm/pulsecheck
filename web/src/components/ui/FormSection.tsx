import { useId, type ReactNode } from 'react'
import { Card } from './Card.tsx'

export function FormSection({ title, description, children }: { title: string; description?: string; children: ReactNode }) {
  const headingId = useId()

  return (
    <Card className="flex flex-col gap-4 px-4 py-5 sm:px-6" role="group" aria-labelledby={headingId}>
      <div className="flex flex-col gap-1">
        <h2 id={headingId} className="text-base font-semibold text-neutral-900">
          {title}
        </h2>
        {description && <p className="text-sm text-neutral-600">{description}</p>}
      </div>
      {children}
    </Card>
  )
}
