import { en } from './en.ts'

type Dictionary = typeof en

type Join<Prefix extends string, Key extends string> = Prefix extends '' ? Key : `${Prefix}.${Key}`

type TranslationKey<Node, Prefix extends string = ''> = {
  [Key in keyof Node & string]: Node[Key] extends string
    ? Join<Prefix, Key>
    : TranslationKey<Node[Key], Join<Prefix, Key>>
}[keyof Node & string]

export type MessageKey = TranslationKey<Dictionary>

const dictionary: Dictionary = en

export function t(key: MessageKey, values: Record<string, string | number> = {}): string {
  const message = key.split('.').reduce<unknown>((node, part) => (node as Record<string, unknown>)[part], dictionary)

  if (typeof message !== 'string') {
    return key
  }

  return message.replace(/\{(\w+)\}/g, (placeholder, name: string) =>
    name in values ? String(values[name]) : placeholder,
  )
}
