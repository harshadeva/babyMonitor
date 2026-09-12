import { ref } from 'vue'
import { apiClient, ensureCsrfCookie } from '@/api/client'
import { queueCreate, rememberLastEntry } from '@/offline/db'
import { flushPendingCreates } from '@/offline/sync'

/**
 * Shared "log an event" flow: generates an idempotency key, disables itself
 * while a submit is in flight (kills the double-tap duplicate case), writes
 * optimistically to the offline cache, and queues for background sync when
 * there's no connection.
 */
export function useEntryLogger(entity) {
  const isSubmitting = ref(false)
  const lastResult = ref(null)
  const lastError = ref(null)

  async function submit(babyId, payload) {
    if (isSubmitting.value) return null // guards against a double-tap re-entering submit
    isSubmitting.value = true
    lastError.value = null

    const clientUuid = crypto.randomUUID()
    const fullPayload = { ...payload, client_uuid: clientUuid }

    // Optimistic local update so the UI (and the home screen's "last X" tile) reacts instantly.
    await rememberLastEntry(entity, fullPayload)

    try {
      if (!navigator.onLine) {
        throw new Error('offline')
      }

      await ensureCsrfCookie()
      const { data } = await apiClient.post(`/api/babies/${babyId}/${entity}`, fullPayload)
      lastResult.value = data.data
      await rememberLastEntry(entity, data.data)
      return {
        synced: true,
        data: data.data,
        meta: data.meta ?? null,
        possibleDuplicateOf: data.data.possible_duplicate_of ?? null,
      }
    } catch (error) {
      if (error?.response?.status >= 400 && error.response.status < 500 && error.response.status !== 419) {
        lastError.value = error.response.data
        return { synced: false, error: error.response.data }
      }

      // Network failure (or we already knew we were offline) — queue it.
      await queueCreate(entity, babyId, fullPayload)
      flushPendingCreates()
      return { synced: false, queued: true }
    } finally {
      isSubmitting.value = false
    }
  }

  return { submit, isSubmitting, lastResult, lastError }
}
