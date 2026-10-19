import { screen, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import * as authApi from '../../api/auth.ts'
import * as teamsApi from '../../api/teams.ts'
import * as usersApi from '../../api/users.ts'
import { axiosError } from '../../test/axiosErrors.ts'
import { ada, makeTeam, makeUser } from '../../test/fixtures.ts'
import { renderApp } from '../../test/renderApp.tsx'

vi.mock('../../api/auth.ts')
vi.mock('../../api/teams.ts')
vi.mock('../../api/users.ts')

const grace = makeUser()

async function usersPage() {
  return within((await screen.findByRole('heading', { name: 'Users' })).closest('section')!)
}

describe('UsersPage', () => {
  beforeEach(() => {
    vi.mocked(authApi.fetchCurrentUser).mockResolvedValue(ada)
    vi.mocked(teamsApi.listTeams).mockResolvedValue([makeTeam({ id: 1, name: 'Platform' }), makeTeam({ id: 2, name: 'Commerce' })])
    vi.mocked(usersApi.listUsers).mockResolvedValue([ada, grace])
  })

  it('lists users with their role and marks the current account', async () => {
    renderApp('/users')
    const section = await usersPage()

    const adaRow = (await section.findByText('Ada Lovelace')).closest('li')!
    expect(within(adaRow).getByText('Administrator')).toBeInTheDocument()
    expect(within(adaRow).getByText('You')).toBeInTheDocument()
    expect(within(adaRow).queryByRole('button', { name: 'Delete: Ada Lovelace' })).not.toBeInTheDocument()

    const graceRow = section.getByText('Grace Hopper').closest('li')!
    expect(within(graceRow).getByText('Member')).toBeInTheDocument()
    expect(within(graceRow).getByText('grace@example.com')).toBeInTheDocument()
  })

  it('creates a user with an initial password and role', async () => {
    vi.mocked(usersApi.createUser).mockResolvedValue(makeUser({ id: 3, name: 'Alan Turing' }))
    const user = userEvent.setup()
    renderApp('/users')
    const section = await usersPage()

    await user.click(section.getByRole('button', { name: 'Add user' }))
    const dialog = await screen.findByRole('dialog', { name: 'Add user' })
    await user.type(within(dialog).getByLabelText('Name'), 'Alan Turing')
    await user.type(within(dialog).getByLabelText('Email'), 'alan@example.com')
    await user.selectOptions(within(dialog).getByLabelText('Role'), 'admin')
    await user.type(within(dialog).getByLabelText('Password'), 'enigma-1912')
    await user.type(within(dialog).getByLabelText('Confirm password'), 'enigma-1912')
    await user.click(within(dialog).getByRole('button', { name: 'Add user' }))

    expect(await screen.findByText('User added. Share the password with them so they can log in.')).toBeInTheDocument()
    expect(usersApi.createUser).toHaveBeenCalledWith({
      name: 'Alan Turing',
      email: 'alan@example.com',
      role: 'admin',
      can_view_all_monitors: false,
      team_ids: [],
      password: 'enigma-1912',
      password_confirmation: 'enigma-1912',
    })
  })

  it('edits a user without sending a password when it is left blank', async () => {
    vi.mocked(usersApi.updateUser).mockResolvedValue({ ...grace, role: 'admin' })
    const user = userEvent.setup()
    renderApp('/users')
    const section = await usersPage()

    await user.click(await section.findByRole('button', { name: 'Edit: Grace Hopper' }))
    const dialog = await screen.findByRole('dialog', { name: 'Edit Grace Hopper' })
    expect(within(dialog).getByLabelText('Email')).toHaveValue('grace@example.com')
    await user.selectOptions(within(dialog).getByLabelText('Role'), 'admin')
    await user.click(within(dialog).getByRole('button', { name: 'Save changes' }))

    expect(await screen.findByText('Changes saved.')).toBeInTheDocument()
    expect(usersApi.updateUser).toHaveBeenCalledWith(grace.id, {
      name: 'Grace Hopper',
      email: 'grace@example.com',
      role: 'admin',
      can_view_all_monitors: false,
      team_ids: [],
    })
  })

  it('shows the last administrator error next to the role', async () => {
    vi.mocked(usersApi.updateUser).mockRejectedValue(
      axiosError(422, { message: 'Invalid.', errors: { role: ['There must be at least one administrator.'] } }),
    )
    const user = userEvent.setup()
    renderApp('/users')
    const section = await usersPage()

    await user.click(await section.findByRole('button', { name: 'Edit: Ada Lovelace' }))
    const dialog = await screen.findByRole('dialog', { name: 'Edit Ada Lovelace' })
    await user.selectOptions(within(dialog).getByLabelText('Role'), 'member')
    await user.click(within(dialog).getByRole('button', { name: 'Save changes' }))

    expect(await within(dialog).findByText('There must be at least one administrator.')).toBeInTheDocument()
    expect(within(dialog).getByLabelText('Role')).toHaveAttribute('aria-invalid', 'true')
  })

  it('deletes a user after confirmation', async () => {
    vi.mocked(usersApi.deleteUser).mockResolvedValue()
    const user = userEvent.setup()
    renderApp('/users')
    const section = await usersPage()

    await user.click(await section.findByRole('button', { name: 'Delete: Grace Hopper' }))
    const dialog = await screen.findByRole('dialog', { name: 'Delete Grace Hopper?' })
    await user.click(within(dialog).getByRole('button', { name: 'Delete user' }))

    expect(await screen.findByText('User deleted.')).toBeInTheDocument()
    expect(usersApi.deleteUser).toHaveBeenCalledWith(grace.id)
  })

  it('shows why a user cannot be deleted', async () => {
    vi.mocked(usersApi.deleteUser).mockRejectedValue(
      axiosError(422, { message: 'Invalid.', errors: { user: ['You cannot delete the last administrator.'] } }),
    )
    const user = userEvent.setup()
    renderApp('/users')
    const section = await usersPage()

    await user.click(await section.findByRole('button', { name: 'Delete: Grace Hopper' }))
    const dialog = await screen.findByRole('dialog', { name: 'Delete Grace Hopper?' })
    await user.click(within(dialog).getByRole('button', { name: 'Delete user' }))

    expect(await screen.findByText('You cannot delete the last administrator.')).toBeInTheDocument()
  })

  it('gives a manager access to specific teams', async () => {
    vi.mocked(usersApi.updateUser).mockResolvedValue({ ...grace, role: 'manager', team_ids: [2] })
    const user = userEvent.setup()
    renderApp('/users')
    const section = await usersPage()

    await user.click(await section.findByRole('button', { name: 'Edit: Grace Hopper' }))
    const dialog = await screen.findByRole('dialog', { name: 'Edit Grace Hopper' })
    await user.selectOptions(within(dialog).getByLabelText('Role'), 'manager')
    await user.click(await within(dialog).findByRole('checkbox', { name: 'Commerce' }))
    await user.click(within(dialog).getByRole('button', { name: 'Save changes' }))

    expect(await screen.findByText('Changes saved.')).toBeInTheDocument()
    expect(usersApi.updateUser).toHaveBeenCalledWith(grace.id, expect.objectContaining({ role: 'manager', can_view_all_monitors: false, team_ids: [2] }))
  })

  it('uses the all monitors shortcut instead of picking teams', async () => {
    vi.mocked(usersApi.updateUser).mockResolvedValue({ ...grace, can_view_all_monitors: true })
    const user = userEvent.setup()
    renderApp('/users')
    const section = await usersPage()

    await user.click(await section.findByRole('button', { name: 'Edit: Grace Hopper' }))
    const dialog = await screen.findByRole('dialog', { name: 'Edit Grace Hopper' })
    await user.click(within(dialog).getByRole('checkbox', { name: /All monitors/ }))

    expect(await within(dialog).findByRole('checkbox', { name: 'Platform' })).toBeDisabled()
    expect(within(dialog).getByRole('checkbox', { name: 'Platform' })).toBeChecked()

    await user.click(within(dialog).getByRole('button', { name: 'Save changes' }))

    expect(await screen.findByText('Changes saved.')).toBeInTheDocument()
    expect(usersApi.updateUser).toHaveBeenCalledWith(grace.id, expect.objectContaining({ can_view_all_monitors: true }))
  })

  it('hides team access for administrators', async () => {
    const user = userEvent.setup()
    renderApp('/users')
    const section = await usersPage()

    await user.click(await section.findByRole('button', { name: 'Edit: Ada Lovelace' }))
    const dialog = await screen.findByRole('dialog', { name: 'Edit Ada Lovelace' })

    expect(within(dialog).getByText('Administrators always see every monitor.')).toBeInTheDocument()
    expect(within(dialog).queryByRole('checkbox', { name: /All monitors/ })).not.toBeInTheDocument()
  })
})
