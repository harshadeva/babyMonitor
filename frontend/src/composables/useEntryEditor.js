import { ref } from 'vue'
import { apiClient, ensureCsrfCookie } from '@/api/client'

/**
 * Shared "edit an existing record" flow: PATCHes the record in place and
 * returns the same {synced, data, error} shape useEntryLogger's submit()
 * does, so callers can handle both create and edit results the same way.
 * Unlike create, this is online-only — there's no offline queue for edits,
 * so a network failure just surfaces as an error to retry later.
 */
export function useEntryEditor(entity) {
  const isSaving = ref(false)

  async function saveEdit(id, payload) {
    if (isSaving.value) return null
    isSaving.value = true
    try {
      await ensureCsrfCookie()
      const { data } = await apiClient.patch(`/api/${entity}/${id}`, payload)
      return { synced: true, data: data.data, meta: data.meta ?? null }
    } catch (error) {
      return {
        synced: false,
        error: error.response?.data || { message: "Could not save changes — try again once you're back online." },
      }
    } finally {
      isSaving.value = false
    }
  }

  return { saveEdit, isSaving }
}
