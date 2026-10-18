import type { DashboardWidget, DashboardWidgetType } from '../../api/types.ts'
import { widgetDefinitions } from './widgets/registry.ts'

export const GRID_COLUMNS = 12

export const MAX_WIDGET_HEIGHT = 12

export const MAX_WIDGETS = 30

type GridPosition = { i: string; x: number; y: number; w: number; h: number }

function createWidgetId(type: DashboardWidgetType): string {
  return `${type}-${Date.now().toString(36)}-${Math.random().toString(36).slice(2, 8)}`
}

export function addWidget(widgets: DashboardWidget[], type: DashboardWidgetType, options: DashboardWidget['options'] = {}): DashboardWidget[] {
  const { defaultSize } = widgetDefinitions[type]
  const bottom = widgets.reduce((lowest, widget) => Math.max(lowest, widget.y + widget.h), 0)

  return [...widgets, { id: createWidgetId(type), type, x: 0, y: bottom, w: defaultSize.w, h: defaultSize.h, options }]
}

export function removeWidget(widgets: DashboardWidget[], id: string): DashboardWidget[] {
  return widgets.filter((widget) => widget.id !== id)
}

export function applyGridPositions(widgets: DashboardWidget[], positions: readonly GridPosition[]): DashboardWidget[] {
  const positionById = new Map(positions.map((position) => [position.i, position]))
  let hasChanged = false

  const next = widgets.map((widget) => {
    const position = positionById.get(widget.id)

    if (!position || (position.x === widget.x && position.y === widget.y && position.w === widget.w && position.h === widget.h)) {
      return widget
    }

    hasChanged = true

    return { ...widget, x: position.x, y: position.y, w: position.w, h: position.h }
  })

  return hasChanged ? next : widgets
}

export function inReadingOrder(widgets: DashboardWidget[]): DashboardWidget[] {
  return [...widgets].sort((first, second) => first.y - second.y || first.x - second.x)
}
