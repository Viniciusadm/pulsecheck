import type { NotificationChannel } from '../../api/types.ts'
import { Dialog } from '../../components/ui/Dialog.tsx'
import { t } from '../../i18n/index.ts'
import { NotificationChannelForm } from './NotificationChannelForm.tsx'

type NotificationChannelDialogProps = {
  channel?: NotificationChannel
  onClose: () => void
}

export function NotificationChannelDialog({ channel, onClose }: NotificationChannelDialogProps) {
  return (
    <Dialog
      isOpen
      title={channel ? t('settings.channels.form.editTitle', { name: channel.name }) : t('settings.channels.form.createTitle')}
      onClose={onClose}
    >
      <NotificationChannelForm channel={channel} onSaved={onClose} onCancel={onClose} />
    </Dialog>
  )
}
