import { isAxiosError } from 'axios'
import { generalErrorMessage } from '../../api/errors.ts'
import { t } from '../../i18n/index.ts'

export function deliveryMessage(error: unknown): string {
  if (isAxiosError<{ message?: string }>(error) && error.response && [422, 503].includes(error.response.status)) {
    return error.response.data.message ?? t('common.genericError')
  }

  return generalErrorMessage(error) ?? t('common.genericError')
}
