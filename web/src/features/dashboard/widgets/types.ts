import type { DashboardWidget, StatsPeriod } from '../../../api/types.ts'

export type WidgetProps = {
  widget: DashboardWidget
  period: StatsPeriod
}
