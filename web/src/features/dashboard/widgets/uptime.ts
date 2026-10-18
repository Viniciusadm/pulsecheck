import type { UptimeTrendBucket } from '../../../api/types.ts'

export function overallUptime(buckets: UptimeTrendBucket[]): number | null {
  const checks = buckets.reduce((total, bucket) => total + bucket.checks, 0)
  const failedChecks = buckets.reduce((total, bucket) => total + bucket.failed_checks, 0)

  if (checks === 0) {
    return null
  }

  const percentage = Math.round(((checks - failedChecks) / checks) * 10_000) / 100

  return failedChecks > 0 ? Math.min(percentage, 99.99) : percentage
}
