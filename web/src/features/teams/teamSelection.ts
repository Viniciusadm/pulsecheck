import type { Team } from '../../api/types.ts'

export function initialTeamIds(teams: Team[] | undefined, currentIds?: number[]): number[] {
  if (currentIds) {
    return currentIds
  }

  return teams?.length === 1 ? [teams[0].id] : []
}

export function teamIdsError(errors: Record<string, string>): string | undefined {
  return Object.entries(errors).find(([field]) => field.startsWith('team_ids'))?.[1]
}
