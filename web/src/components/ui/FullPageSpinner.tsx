import { Spinner } from './Spinner.tsx'

export function FullPageSpinner() {
  return (
    <div className="flex min-h-screen items-center justify-center text-primary-600">
      <Spinner />
    </div>
  )
}
