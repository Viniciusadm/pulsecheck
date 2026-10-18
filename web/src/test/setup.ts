import '@testing-library/jest-dom/vitest'
import { cleanup } from '@testing-library/react'
import { afterEach, beforeEach, vi } from 'vitest'
import { getAppConfig } from '../api/config.ts'
import { setLocale } from '../i18n/index.ts'
import { setDisplayTimeZone } from '../lib/format.ts'
import { makeConfig } from './fixtures.ts'

vi.mock('../api/config.ts', () => ({ getAppConfig: vi.fn() }))

beforeEach(() => {
  setLocale('en')
  setDisplayTimeZone(makeConfig().instance.timezone)
  vi.mocked(getAppConfig).mockResolvedValue(makeConfig())
})

afterEach(() => {
  cleanup()
})

class ResizeObserverStub {
  observe() {}
  unobserve() {}
  disconnect() {}
}

globalThis.ResizeObserver ??= ResizeObserverStub
