import type { PartialDictionary } from './dictionary.ts'
import { en } from './en.ts'
import { ptBR } from './ptBR.ts'

type Join<Prefix extends string, Key extends string> = Prefix extends '' ? Key : `${Prefix}.${Key}`

type TranslationKey<Node, Prefix extends string = ''> = {
  [Key in keyof Node & string]: Node[Key] extends string
    ? Join<Prefix, Key>
    : TranslationKey<Node[Key], Join<Prefix, Key>>
}[keyof Node & string]

export type MessageKey = TranslationKey<typeof en>

const dictionaries = { en, 'pt-BR': ptBR } satisfies Record<string, PartialDictionary>

export type Locale = keyof typeof dictionaries

export const defaultLocale: Locale = 'en'

export const supportedLocales = Object.keys(dictionaries) as Locale[]

let currentLocale: Locale = defaultLocale
const listeners = new Set<() => void>()

export function isSupportedLocale(locale: string): locale is Locale {
  return Object.hasOwn(dictionaries, locale)
}

export function getLocale(): Locale {
  return currentLocale
}

export function setLocale(locale: string, { notify = true }: { notify?: boolean } = {}): Locale {
  const next = isSupportedLocale(locale) ? locale : defaultLocale

  if (typeof document !== 'undefined') {
    document.documentElement.lang = next
  }

  if (next !== currentLocale) {
    currentLocale = next

    if (notify) {
      listeners.forEach((listener) => listener())
    }
  }

  return next
}

export function subscribeToLocale(listener: () => void): () => void {
  listeners.add(listener)

  return () => {
    listeners.delete(listener)
  }
}

function lookup(dictionary: PartialDictionary, key: string): unknown {
  return key.split('.').reduce<unknown>(
    (node, part) => (node !== null && typeof node === 'object' ? (node as Record<string, unknown>)[part] : undefined),
    dictionary,
  )
}

export function t(key: MessageKey, values: Record<string, string | number> = {}): string {
  const localized = lookup(dictionaries[currentLocale], key)
  const message = typeof localized === 'string' ? localized : lookup(dictionaries[defaultLocale], key)

  if (typeof message !== 'string') {
    return key
  }

  return message.replace(/\{(\w+)\}/g, (placeholder, name: string) =>
    name in values ? String(values[name]) : placeholder,
  )
}

export function localeLabel(locale: string): string {
  try {
    const label = new Intl.DisplayNames([locale], { type: 'language' }).of(locale)

    return label ? label.charAt(0).toLocaleUpperCase(locale) + label.slice(1) : locale
  } catch {
    return locale
  }
}
