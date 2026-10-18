import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { describe, expect, it, vi } from 'vitest'
import { Dialog } from './Dialog.tsx'

function renderDialog(onClose: () => void) {
  return (
    <Dialog isOpen title="Edit" onClose={onClose}>
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
})
