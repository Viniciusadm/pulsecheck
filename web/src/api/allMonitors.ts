import { listMonitors } from './monitors.ts'
import type { Monitor } from './types.ts'

const MAX_PARALLEL_PAGE_REQUESTS = 4

export async function listAllMonitors(): Promise<Monitor[]> {
  const firstPage = await listMonitors({ page: 1 })
  const monitors = [...firstPage.data]

  for (let page = 2; page <= firstPage.meta.last_page; page += MAX_PARALLEL_PAGE_REQUESTS) {
    const lastPage = Math.min(page + MAX_PARALLEL_PAGE_REQUESTS - 1, firstPage.meta.last_page)
    const batch = await Promise.all(Array.from({ length: lastPage - page + 1 }, (_, index) => listMonitors({ page: page + index })))
    monitors.push(...batch.flatMap((result) => result.data))
  }

  return monitors
}
