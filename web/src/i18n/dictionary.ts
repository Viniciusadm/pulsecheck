import type { en } from './en.ts'

type Widen<Node> = { [Key in keyof Node]: Node[Key] extends string ? string : Widen<Node[Key]> }

type DeepPartial<Node> = { [Key in keyof Node]?: Node[Key] extends string ? string : DeepPartial<Node[Key]> }

export type Dictionary = Widen<typeof en>

export type PartialDictionary = DeepPartial<Dictionary>
