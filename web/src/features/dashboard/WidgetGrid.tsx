import ReactGridLayout, { useContainerWidth } from 'react-grid-layout'
import 'react-grid-layout/css/styles.css'
import 'react-resizable/css/styles.css'
import type { DashboardWidget, StatsPeriod } from '../../api/types.ts'
import { t } from '../../i18n/index.ts'
import { applyGridPositions, GRID_COLUMNS, inReadingOrder, MAX_WIDGET_HEIGHT } from './layout.ts'
import { widgetComponents } from './widgets/components.ts'
import { widgetDefinitions } from './widgets/registry.ts'
import { DRAG_HANDLE_CLASS, WidgetFrame } from './widgets/WidgetFrame.tsx'

const ROW_HEIGHT = 72

const GAP = 16

const STACKED_BELOW_WIDTH = 768

type WidgetGridProps = {
  widgets: DashboardWidget[]
  period: StatsPeriod
  isEditing: boolean
  onChange: (widgets: DashboardWidget[]) => void
  onRemove: (id: string) => void
}

export function WidgetGrid({ widgets, period, isEditing, onChange, onRemove }: WidgetGridProps) {
  const { width, containerRef, mounted } = useContainerWidth({ measureBeforeMount: true })
  const isStacked = width < STACKED_BELOW_WIDTH

  const renderWidget = (widget: DashboardWidget, canDrag: boolean) => {
    const definition = widgetDefinitions[widget.type]
    const Widget = widgetComponents[widget.type]

    return (
      <WidgetFrame title={t(definition.title)} isEditing={isEditing} canDrag={canDrag} onRemove={() => onRemove(widget.id)}>
        <Widget widget={widget} period={period} />
      </WidgetFrame>
    )
  }

  return (
    <div ref={containerRef} className="flex flex-col gap-3">
      {mounted && isEditing && <p className="text-xs text-neutral-500">{t(isStacked ? 'dashboard.narrowEditingHint' : 'dashboard.editingHint')}</p>}
      {mounted && isStacked && (
        <div className="flex flex-col gap-4">
          {inReadingOrder(widgets).map((widget) => (
            <div key={widget.id} style={widgetDefinitions[widget.type].fitsContent ? undefined : { height: widget.h * ROW_HEIGHT + (widget.h - 1) * GAP }}>
              {renderWidget(widget, false)}
            </div>
          ))}
        </div>
      )}
      {mounted && !isStacked && (
        <ReactGridLayout
          width={width}
          layout={widgets.map((widget) => ({
            i: widget.id,
            x: widget.x,
            y: widget.y,
            w: widget.w,
            h: widget.h,
            minW: widgetDefinitions[widget.type].minSize.w,
            minH: widgetDefinitions[widget.type].minSize.h,
            maxH: MAX_WIDGET_HEIGHT,
          }))}
          gridConfig={{ cols: GRID_COLUMNS, rowHeight: ROW_HEIGHT, margin: [GAP, GAP], containerPadding: [0, 0] }}
          dragConfig={{ enabled: isEditing, handle: `.${DRAG_HANDLE_CLASS}`, cancel: 'button' }}
          resizeConfig={{ enabled: isEditing }}
          onLayoutChange={(layout) => {
            if (isEditing) {
              onChange(applyGridPositions(widgets, layout))
            }
          }}
        >
          {widgets.map((widget) => (
            <div key={widget.id}>{renderWidget(widget, true)}</div>
          ))}
        </ReactGridLayout>
      )}
    </div>
  )
}
