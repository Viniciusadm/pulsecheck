export function inputClassName(error?: string) {
  return `min-h-10 rounded-md border bg-surface px-3 text-sm text-neutral-900 placeholder:text-neutral-400 focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-200 ${
    error ? 'border-status-down' : 'border-neutral-300'
  }`
}
