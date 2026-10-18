import { t } from '../../i18n/index.ts'
import { Button } from './Button.tsx'
import { Dialog } from './Dialog.tsx'

type ConfirmDialogProps = {
  isOpen: boolean
  title: string
  description: string
  confirmLabel: string
  pendingLabel: string
  isPending: boolean
  onConfirm: () => void
  onClose: () => void
}

export function ConfirmDialog({ isOpen, title, description, confirmLabel, pendingLabel, isPending, onConfirm, onClose }: ConfirmDialogProps) {
  return (
    <Dialog
      isOpen={isOpen}
      title={title}
      onClose={onClose}
      footer={
        <>
          <Button variant="secondary" onClick={onClose} disabled={isPending}>
            {t('common.cancel')}
          </Button>
          <Button variant="danger" onClick={onConfirm} isLoading={isPending} loadingLabel={pendingLabel}>
            {confirmLabel}
          </Button>
        </>
      }
    >
      <p>{description}</p>
    </Dialog>
  )
}
