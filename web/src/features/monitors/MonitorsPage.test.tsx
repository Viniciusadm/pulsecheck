import { screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import * as authApi from '../../api/auth.ts'
import * as monitorsApi from '../../api/monitors.ts'
import { ada, makeMonitor } from '../../test/fixtures.ts'
import { renderApp } from '../../test/renderApp.tsx'

vi.mock('../../api/auth.ts')
vi.mock('../../api/monitors.ts')

describe('MonitorsPage', () => {
  beforeEach(() => {
    vi.mocked(authApi.fetchCurrentUser).mockResolvedValue(ada)
  })

  it('offers to go back when the page is past the last one', async () => {
    vi.mocked(monitorsApi.listMonitors).mockImplementation(async ({ page } = {}) => ({
      data: page === 1 ? [makeMonitor()] : [],
      meta: { current_page: page ?? 1, last_page: 1, per_page: 20, total: 1 },
    }))
    const user = userEvent.setup()
    const { router } = renderApp('/monitors?page=3')

    expect(await screen.findByText('This page is empty.')).toBeInTheDocument()
    expect(screen.queryByText('Nothing is being monitored yet')).not.toBeInTheDocument()

    await user.click(screen.getByRole('button', { name: 'Go to the first page' }))

    expect(await screen.findByRole('link', { name: 'Shop API' })).toBeInTheDocument()
    await waitFor(() => expect(router.state.location.search).toBe(''))
  })
})
