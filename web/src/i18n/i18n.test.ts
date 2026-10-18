import { describe, expect, it } from 'vitest'
import { t } from './index.ts'

describe('t', () => {
  it('returns the message for a key', () => {
    expect(t('auth.login.title')).toBe('Log in')
  })

  it('interpolates values', () => {
    expect(t('monitors.form.intervalOption', { minutes: 5 })).toBe('5 min')
  })

  it('keeps unknown placeholders', () => {
    expect(t('monitors.form.intervalOption')).toBe('{minutes} min')
  })
})
