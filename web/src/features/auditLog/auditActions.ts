import type { AuditLogEntry } from '../../api/types.ts'
import { t, type MessageKey } from '../../i18n/index.ts'

export const AUDIT_SUBJECT_TYPES = ['monitor', 'notification_channel', 'status_page', 'maintenance_window', 'team', 'user', 'instance_settings', 'mail_settings', 'personal_access_token'] as const

export type AuditSubjectType = (typeof AUDIT_SUBJECT_TYPES)[number]

const SPECIAL_ACTIONS: Record<string, MessageKey> = {
  'auth.login': 'auditLog.actions.login',
  'auth.login_failed': 'auditLog.actions.loginFailed',
  'auth.login_recovery_code': 'auditLog.actions.loginRecoveryCode',
  'auth.two_factor_failed': 'auditLog.actions.twoFactorFailed',
  'account.password_changed': 'auditLog.actions.passwordChanged',
  'account.two_factor_enabled': 'auditLog.actions.twoFactorEnabled',
  'account.two_factor_disabled': 'auditLog.actions.twoFactorDisabled',
  'account.recovery_codes_regenerated': 'auditLog.actions.recoveryCodesRegenerated',
  'account.api_token_created': 'auditLog.actions.apiTokenCreated',
  'account.api_token_revoked': 'auditLog.actions.apiTokenRevoked',
  'user.two_factor_reset': 'auditLog.actions.twoFactorReset',
}

const EVENTS: Record<string, MessageKey> = {
  created: 'auditLog.events.created',
  updated: 'auditLog.events.updated',
  deleted: 'auditLog.events.deleted',
}

export function isAuditSubjectType(value: string | null | undefined): value is AuditSubjectType {
  return AUDIT_SUBJECT_TYPES.some((type) => type === value)
}

export function subjectTypeLabel(type: string): string {
  return isAuditSubjectType(type) ? t(`auditLog.subjects.${type}`) : type
}

export function describeAction(entry: AuditLogEntry): string {
  const special = SPECIAL_ACTIONS[entry.action]

  if (special) {
    return t(special)
  }

  const separator = entry.action.lastIndexOf('.')
  const event = EVENTS[entry.action.slice(separator + 1)]
  const subjectType = entry.action.slice(0, separator)

  return event ? t(event, { subject: subjectTypeLabel(subjectType).toLocaleLowerCase() }) : entry.action
}
