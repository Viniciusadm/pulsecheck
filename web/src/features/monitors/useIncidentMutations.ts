import { useMutation, useQueryClient } from '@tanstack/react-query'
import { acknowledgeIncident, createIncidentNote, deleteIncidentNote } from '../../api/incidents.ts'
import { queryKeys } from '../../api/queryKeys.ts'

function useRefreshIncidents() {
  const queryClient = useQueryClient()

  return (incidentId: number) => {
    void queryClient.invalidateQueries({ queryKey: queryKeys.monitors, predicate: (query) => query.queryKey[2] === 'incidents' })
    void queryClient.invalidateQueries({ queryKey: queryKeys.recentIncidents })
    void queryClient.invalidateQueries({ queryKey: queryKeys.incidentNotes(incidentId) })
  }
}

export function useAcknowledgeIncident() {
  const refresh = useRefreshIncidents()

  return useMutation({ mutationFn: (incidentId: number) => acknowledgeIncident(incidentId), onSuccess: (incident) => refresh(incident.id) })
}

export function useCreateIncidentNote(incidentId: number) {
  const refresh = useRefreshIncidents()

  return useMutation({ mutationFn: (body: string) => createIncidentNote(incidentId, body), onSuccess: () => refresh(incidentId) })
}

export function useDeleteIncidentNote(incidentId: number) {
  const refresh = useRefreshIncidents()

  return useMutation({ mutationFn: (noteId: number) => deleteIncidentNote(incidentId, noteId), onSuccess: () => refresh(incidentId) })
}
