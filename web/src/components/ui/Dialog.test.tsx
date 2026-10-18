import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { describe, expect, it, vi } from 'vitest'
import { Dialog } from './Dialog.tsx'

function renderDialog(onClose: () => void, isBusy = false) {
  return (
    <Dialog isOpen title="Edit" onClose={onClose} isBusy={isBusy}>
      <input aria-label="Name" />
    </Dialog>
  )
}

describe('Dialog', () => {
  it('keeps focus inside when the parent re-renders with a new onClose', async () => {
    const { rerender } = render(renderDialog(() => {}))
    const input = screen.getByRole('textbox', { name: 'Name' })

    await userEvent.click(input)
    rerender(renderDialog(() => {}))

    expect(input).toHaveFocus()
  })

  it('closes with the latest onClose on Escape', async () => {
    const latestOnClose = vi.fn()
    const { rerender } = render(renderDialog(() => {}))

    rerender(renderDialog(latestOnClose))
    await userEvent.keyboard('{Escape}')

    expect(latestOnClose).toHaveBeenCalledOnce()
  })

  it('keeps keyboard focus inside the dialog', async () => {
    render(
      <>
        <button type="button">Outside</button>
        {renderDialog(() => {})}
      </>,
    )
    const close = screen.getByRole('button', { name: 'Close' })
    const input = screen.getByRole('textbox', { name: 'Name' })

    await userEvent.tab()
    expect(close).toHaveFocus()
    await userEvent.tab()
    expect(input).toHaveFocus()
    await userEvent.tab()
    expect(close).toHaveFocus()
    await userEvent.tab({ shift: true })
    expect(input).toHaveFocus()
  })

  it('does not close while busy', async () => {
    const onClose = vi.fn()
    render(renderDialog(onClose, true))

    await userEvent.keyboard('{Escape}')

    expect(onClose).not.toHaveBeenCalled()
    expect(screen.getByRole('button', { name: 'Close' })).toBeDisabled()
  })
})
