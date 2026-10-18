import { useQuery } from '@tanstack/react-query'
import { useState } from 'react'
import { listMonitors } from '../../api/monitors.ts'
import { queryKeys } from '../../api/queryKeys.ts'
import type { DashboardWidget, DashboardWidgetType, Monitor } from '../../api/types.ts'
import { Button } from '../../components/ui/Button.tsx'
import { Dialog } from '../../components/ui/Dialog.tsx'
import { SelectField } from '../../components/ui/SelectField.tsx'
import { t } from '../../i18n/index.ts'
import { widgetDefinitions, widgetTypes } from './widgets/registry.ts'

type AddWidgetDialogProps = {
  isOpen: boolean
  widgets: DashboardWidget[]
  onClose: () => void
  onAdd: (type: DashboardWidgetType, options: DashboardWidget['options']) => void
}

async function listAllMonitors(): Promise<Monitor[]> {
  const firstPage = await listMonitors({ page: 1 })
  const remainingPages = await Promise.all(
    Array.from({ length: firstPage.meta.last_page - 1 }, (_, index) => listMonitors({ page: index + 2 })),
  )

  return [firstPage, ...remainingPages].flatMap((page) => page.data)
}

export function AddWidgetDialog({ isOpen, widgets, onClose, onAdd }: AddWidgetDialogProps) {
  const [selectedType, setSelectedType] = useState<DashboardWidgetType | null>(null)
  const [monitorId, setMonitorId] = useState('')
  const requiresMonitor = selectedType !== null && widgetDefinitions[selectedType].requiresMonitor
  const monitors = useQuery({ queryKey: queryKeys.allMonitors, queryFn: listAllMonitors, enabled: isOpen && requiresMonitor })
  const presentTypes = new Set(widgets.map((widget) => widget.type))
  const isPresent = (type: DashboardWidgetType) => !widgetDefinitions[type].requiresMonitor && presentTypes.has(type)
  const catalog = [...widgetTypes].sort((first, second) => Number(isPresent(first)) - Number(isPresent(second)))
  const canAdd = selectedType !== null && (!requiresMonitor || monitorId !== '')

  const close = () => {
    setSelectedType(null)
    setMonitorId('')
    onClose()
  }

  const add = () => {
    if (selectedType === null) {
      return
    }

    onAdd(selectedType, requiresMonitor ? { monitor_id: Number(monitorId) } : {})
    close()
  }

  return (
    <Dialog
      isOpen={isOpen}
      title={t('dashboard.addDialog.title')}
      onClose={close}
      footer={
        <>
          <Button variant="secondary" onClick={close}>
            {t('common.cancel')}
          </Button>
          <Button onClick={add} disabled={!canAdd}>
            {t('dashboard.addDialog.submit')}
          </Button>
        </>
      }
    >
      <div className="flex flex-col gap-4">
        <fieldset className="flex max-h-80 flex-col gap-2 overflow-y-auto">
          <legend className="sr-only">{t('dashboard.addDialog.title')}</legend>
          {catalog.map((type) => {
            const definition = widgetDefinitions[type]
            const isAlreadyAdded = isPresent(type)

            return (
              <label
                key={type}
                className={`flex gap-3 rounded-md border px-3 py-2.5 ${
                  isAlreadyAdded ? 'cursor-not-allowed border-border opacity-60' : 'cursor-pointer hover:bg-neutral-50'
                } ${selectedType === type ? 'border-primary-500 bg-primary-50' : 'border-border'}`}
              >
                <input
                  type="radio"
                  name="widget-type"
                  value={type}
                  checked={selectedType === type}
                  disabled={isAlreadyAdded}
                  onChange={() => setSelectedType(type)}
                  className="mt-1 accent-primary-700"
                />
                <span className="flex flex-col">
                  <span className="font-medium text-neutral-900">{t(definition.title)}</span>
                  <span className="text-xs text-neutral-600">{isAlreadyAdded ? t('dashboard.addDialog.alreadyAdded') : t(definition.description)}</span>
                </span>
              </label>
            )
          })}
        </fieldset>
        {requiresMonitor && (
          <SelectField
            label={t('dashboard.addDialog.monitor')}
            value={monitorId}
            onChange={(event) => setMonitorId(event.target.value)}
            disabled={!monitors.data}
            error={monitors.isError ? t('common.genericError') : undefined}
          >
            <option value="">{monitors.isPending ? t('common.loading') : t('dashboard.addDialog.chooseMonitor')}</option>
            {monitors.data?.map((monitor) => (
              <option key={monitor.id} value={monitor.id}>
                {monitor.name}
              </option>
            ))}
          </SelectField>
        )}
      </div>
    </Dialog>
  )
}
