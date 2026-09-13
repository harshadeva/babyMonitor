import { ref } from 'vue'
import { apiClient, ensureCsrfCookie } from '@/api/client'
import { queueCreate, rememberLastEntry } from '@/offline/db'
import { flushPendingCreates } from '@/offline/sync'

/**
 * crypto.randomUUID() is restricted to secure contexts (HTTPS/localhost) in
 * most browsers, so it silently throws when this app is served over plain
 * HTTP from a raw IP or non-localhost host. crypto.getRandomValues() has no
 * such restriction, so it's the fallback; a non-crypto fallback covers the
 * (very unlikely) case neither is available.
 */
function generateUuid() {
  if (typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function') {
    try {
      return crypto.randomUUID()
    } catch {
      // fall through to getRandomValues
    }
  }

  if (typeof crypto !== 'undefined' && typeof crypto.getRandomValues === 'function') {
    const bytes = crypto.getRandomValues(new Uint8Array(16))
    bytes[6] = (bytes[6] & 0x0f) | 0x40
    bytes[8] = (bytes[8] & 0x3f) | 0x80
    const hex = Array.from(bytes, (b) => b.toString(16).padStart(2, '0')).join('')
    return `${hex.slice(0, 8)}-${hex.slice(8, 12)}-${hex.slice(12, 16)}-${hex.slice(16, 20)}-${hex.slice(20)}`
  }

  return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, (c) => {
    const r = (Math.random() * 16) | 0
    const v = c === 'x' ? r : (r & 0x3) | 0x8
    return v.toString(16)
  })
}

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

    // fullPayload is declared here (not with const inside the try block) so
    // it's still visible in catch if something fails partway through.
    let fullPayload

    // Everything below runs inside try/finally — if this throws for any
    // reason, isSubmitting must still reset, or the UI is stuck on
    // "Saving…" forever with no way to retry.
    try {
      fullPayload = { ...payload, client_uuid: generateUuid() }

      // Optimistic local update so the UI (and the home screen's "last X" tile) reacts instantly.
      await rememberLastEntry(entity, fullPayload)

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

      if (!fullPayload) {
        // Failed before we even had a payload to queue (e.g. UUID generation
        // itself threw) — nothing left to fall back to.
        lastError.value = { message: 'Could not save this entry on this device.' }
        return { synced: false, error: lastError.value }
      }

      // Network failure (or we already knew we were offline) — queue it.
      try {
        await queueCreate(entity, babyId, fullPayload)
      } catch {
        // If even IndexedDB is unavailable, there's nothing left to fall back
        // to — surface a real error instead of hanging.
        lastError.value = { message: 'Could not save this entry on this device.' }
        return { synced: false, error: lastError.value }
      }
      flushPendingCreates()
      return { synced: false, queued: true }
    } finally {
      isSubmitting.value = false
    }
  }

  return { submit, isSubmitting, lastResult, lastError }
}
