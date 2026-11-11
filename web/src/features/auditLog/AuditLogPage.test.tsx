import { screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import * as auditLogApi from '../../api/auditLog.ts'
import * as authApi from '../../api/auth.ts'
import { ada, makeUser } from '../../test/fixtures.ts'
import { renderApp } from '../../test/renderApp.tsx'

vi.mock('../../api/auth.ts')
vi.mock('../../api/auditLog.ts')

describe('AuditLogPage', () => {
  beforeEach(() => {
    vi.mocked(authApi.fetchCurrentUser).mockResolvedValue(ada)
    vi.mocked(auditLogApi.listAuditLog).mockResolvedValue({
      data: [
        {
          id: 2,
          action: 'monitor.updated',
          actor: { id: 1, name: 'Ada Lovelace' },
          subject: { type: 'monitor', id: 7, label: 'Shop API' },
          metadata: { fields: ['name', 'url'] },
          ip_address: '203.0.113.5',
          user_agent: null,
          created_at: '2026-11-11T10:00:00Z',
        },
        {
          id: 3,
          action: 'auth.login',
          actor: { id: 1, name: 'Ada Lovelace' },
          subject: { type: 'user', id: 1, label: 'Ada Lovelace' },
          metadata: {},
          ip_address: null,
          user_agent: null,
          created_at: '2026-11-11T09:30:00Z',
        },
        {
          id: 1,
          action: 'auth.login_failed',
          actor: null,
          subject: null,
          metadata: { email: 'mallory@example.com' },
          ip_address: null,
          user_agent: null,
          created_at: '2026-11-11T09:00:00Z',
        },
      ],
      meta: { current_page: 1, last_page: 1, per_page: 50, total: 2 },
    })
  })

  it('describes each entry and filters by subject', async () => {
    renderApp('/audit-log')
    const user = userEvent.setup()

    expect(await screen.findByText('updated the monitor')).toBeInTheDocument()
    expect(screen.getByText('Shop API')).toBeInTheDocument()
    expect(screen.getByText('Changed: name, url')).toBeInTheDocument()
    expect(screen.getByText('mallory@example.com')).toBeInTheDocument()
    expect(screen.getByText('failed to log in')).toBeInTheDocument()
    expect(screen.getByText('logged in').closest('p')).toHaveTextContent(/^Ada Lovelace logged in$/)

    await user.selectOptions(screen.getByLabelText('Show'), 'monitor')

    await waitFor(() => expect(auditLogApi.listAuditLog).toHaveBeenLastCalledWith({ page: 1, subject_type: 'monitor' }))
  })

  it('is only available to administrators', async () => {
    vi.mocked(authApi.fetchCurrentUser).mockResolvedValue(makeUser())
    const { router } = renderApp('/audit-log')

    await waitFor(() => expect(router.state.location.pathname).toBe('/dashboard'))
    expect(screen.queryByRole('link', { name: 'Audit log' })).not.toBeInTheDocument()
  })
})
