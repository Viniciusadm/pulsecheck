import { describe, expect, it } from 'vitest'
import { getLocale, localeLabel, setLocale, subscribeToLocale, t } from './index.ts'

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

describe('setLocale', () => {
  it('uses a supported locale and sets the document language', () => {
    expect(setLocale('en')).toBe('en')
    expect(getLocale()).toBe('en')
    expect(document.documentElement.lang).toBe('en')
  })

  it('translates to Brazilian Portuguese', () => {
    expect(setLocale('pt-BR', { notify: false })).toBe('pt-BR')
    expect(document.documentElement.lang).toBe('pt-BR')
    expect(t('auth.login.title')).toBe('Entrar')
    expect(t('monitors.pagination.page', { page: 2, total: 5 })).toBe('Página 2 de 5')

    setLocale('en', { notify: false })
  })

  it('falls back to English for a locale without a dictionary', () => {
    expect(setLocale('xx')).toBe('en')
    expect(getLocale()).toBe('en')
    expect(document.documentElement.lang).toBe('en')
    expect(t('auth.login.title')).toBe('Log in')
  })

  it('does not notify listeners when the locale stays the same', () => {
    let notifications = 0
    const unsubscribe = subscribeToLocale(() => notifications++)

    setLocale('en')
    unsubscribe()

    expect(notifications).toBe(0)
  })
})

describe('localeLabel', () => {
  it('names a language in its own language', () => {
    expect(localeLabel('en')).toBe('English')
    expect(localeLabel('pt-BR')).toBe('Português (Brasil)')
  })
})
