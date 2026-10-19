import type { NotificationChannelType, NotificationEvent } from '../../api/types.ts'
import type { MessageKey } from '../../i18n/index.ts'

export type ChannelField = {
  key: string
  label: MessageKey
  inputType: 'text' | 'url' | 'email' | 'password'
  placeholder?: string
  hint?: MessageKey
  isSecret?: boolean
}

export type ChannelOption =
  | { key: string; kind: 'select'; label: MessageKey; hint: MessageKey; choices: { value: string; label: MessageKey }[] }
  | { key: string; kind: 'text'; label: MessageKey; hint: MessageKey; placeholder?: string }
  | { key: string; kind: 'checkbox'; label: MessageKey; hint: MessageKey }

export type ChannelTypeDefinition = {
  fields: ChannelField[]
  options?: ChannelOption[]
  help: MessageKey[]
  hasHeaders?: boolean
}

export const channelTypes: Record<NotificationChannelType, ChannelTypeDefinition> = {
  telegram: {
    fields: [
      { key: 'bot_token', label: 'settings.channels.fields.botToken', inputType: 'password', placeholder: '123456789:AA…', isSecret: true },
      { key: 'chat_id', label: 'settings.channels.fields.chatId', inputType: 'text', hint: 'settings.channels.fields.chatIdHint' },
    ],
    help: [
      'settings.channels.help.telegram.createBot',
      'settings.channels.help.telegram.startChat',
      'settings.channels.help.telegram.findChatId',
    ],
    options: [{ key: 'silent', kind: 'checkbox', label: 'settings.channels.options.silent', hint: 'settings.channels.options.silentHint' }],
  },
  slack: {
    fields: [
      { key: 'webhook_url', label: 'settings.channels.fields.webhookUrl', inputType: 'password', placeholder: 'https://hooks.slack.com/services/…', isSecret: true },
    ],
    help: ['settings.channels.help.slack.createApp', 'settings.channels.help.slack.copyUrl'],
    options: [
      {
        key: 'mention',
        kind: 'select',
        label: 'settings.channels.options.mention',
        hint: 'settings.channels.options.mentionHint',
        choices: [
          { value: '', label: 'settings.channels.options.mentionNone' },
          { value: 'here', label: 'settings.channels.options.mentionHere' },
          { value: 'channel', label: 'settings.channels.options.mentionChannel' },
        ],
      },
    ],
  },
  discord: {
    fields: [
      { key: 'webhook_url', label: 'settings.channels.fields.webhookUrl', inputType: 'password', placeholder: 'https://discord.com/api/webhooks/…', isSecret: true },
    ],
    help: ['settings.channels.help.discord.openSettings', 'settings.channels.help.discord.copyUrl'],
    options: [
      {
        key: 'mention_role_id',
        kind: 'text',
        label: 'settings.channels.options.mentionRole',
        hint: 'settings.channels.options.mentionRoleHint',
        placeholder: '123456789012345678',
      },
    ],
  },
  email: {
    fields: [{ key: 'email', label: 'settings.channels.fields.email', inputType: 'email', placeholder: 'ops@example.com' }],
    help: ['settings.channels.help.email.sender'],
  },
  webhook: {
    fields: [
      { key: 'url', label: 'settings.channels.fields.url', inputType: 'url', placeholder: 'https://example.com/hooks/pulsecheck', hint: 'settings.channels.fields.urlHint', isSecret: true },
    ],
    help: ['settings.channels.help.webhook.payload', 'settings.channels.help.webhook.response'],
    hasHeaders: true,
  },
}

export const channelTypeOrder: NotificationChannelType[] = ['discord', 'email', 'slack', 'telegram', 'webhook']

export const notificationEventOrder: NotificationEvent[] = ['down', 'recovered', 'degraded', 'degradation_resolved', 'reminder', 'closed']
