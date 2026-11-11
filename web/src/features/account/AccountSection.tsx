import type { ReactNode } from 'react'
import { Card } from '../../components/ui/Card.tsx'

type AccountSectionProps = {
  id: string
  title: string
  description: string
  action?: ReactNode
  children: ReactNode
}

export function AccountSection({ id, title, description, action, children }: AccountSectionProps) {
  return (
    <section aria-labelledby={id}>
      <Card className="flex flex-col gap-5 px-5 py-6 sm:px-6">
        <div className="flex flex-wrap items-start justify-between gap-3">
          <div className="flex max-w-prose flex-col gap-1">
            <h2 id={id} className="text-lg font-semibold text-neutral-900">{title}</h2>
            <p className="text-neutral-600">{description}</p>
          </div>
          {action}
        </div>
        {children}
      </Card>
    </section>
  )
}
