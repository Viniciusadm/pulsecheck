import { Alert } from '../../components/ui/Alert.tsx'
import { Button } from '../../components/ui/Button.tsx'
import { useToast } from '../../components/ui/toast.ts'
import { t } from '../../i18n/index.ts'

export function RecoveryCodes({ codes, onDone }: { codes: string[]; onDone: () => void }) {
  const { showToast } = useToast()

  const copyCodes = () => void navigator.clipboard?.writeText(codes.join('\n')).then(() => showToast(t('account.twoFactor.codesCopied')))

  const downloadCodes = () => {
    const url = URL.createObjectURL(new Blob([`${codes.join('\n')}\n`], { type: 'text/plain' }))
    const link = document.createElement('a')
    link.href = url
    link.download = 'pulsecheck-recovery-codes.txt'
    link.click()
    URL.revokeObjectURL(url)
  }

  return (
    <div className="flex flex-col gap-4">
      <Alert tone="info">{t('account.twoFactor.saveCodes')}</Alert>
      <ul aria-label={t('account.twoFactor.recoveryCodes')} className="grid max-w-md grid-cols-2 gap-2 rounded-lg border border-border bg-neutral-50 p-4 font-mono text-sm text-neutral-900">
        {codes.map((code) => (
          <li key={code}>{code}</li>
        ))}
      </ul>
      <div className="flex flex-wrap gap-2">
        <Button variant="secondary" onClick={copyCodes}>{t('account.twoFactor.copyCodes')}</Button>
        <Button variant="secondary" onClick={downloadCodes}>{t('account.twoFactor.downloadCodes')}</Button>
        <Button onClick={onDone}>{t('account.twoFactor.savedCodes')}</Button>
      </div>
    </div>
  )
}
