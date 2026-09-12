import { apiClient, ensureCsrfCookie } from '@/api/client'
import { db } from '@/offline/db'

let flushing = false
const listeners = new Set()

export function onSyncStateChange(fn) {
  listeners.add(fn)
  return () => listeners.delete(fn)
}

function notify(state) {
  for (const fn of listeners) fn(state)
}

export async function flushPendingCreates() {
  if (flushing || !navigator.onLine) return
  flushing = true

  try {
    const pending = await db.pending_creates.toArray()
    if (pending.length === 0) return

    notify({ syncing: true, remaining: pending.length })

    for (const item of pending) {
      try {
        await ensureCsrfCookie()
        await apiClient.post(`/api/babies/${item.baby_id}/${item.entity}`, item.payload)
        await db.pending_creates.delete(item.client_uuid)
      } catch (error) {
        const status = error.response?.status
        if (status && status >= 400 && status < 500 && status !== 419 && status !== 401) {
          // Not retryable (bad data) — drop it rather than retry forever, but keep a trail.
          console.error('Dropping unsyncable entry', item, error.response?.data)
          await db.pending_creates.delete(item.client_uuid)
        } else {
          await db.pending_creates.update(item.client_uuid, { attempts: (item.attempts || 0) + 1 })
        }
      }
    }
  } finally {
    flushing = false
    const remaining = await db.pending_creates.count()
    notify({ syncing: false, remaining })
  }
}

export async function pendingCount() {
  return db.pending_creates.count()
}

export function startSyncLoop() {
  window.addEventListener('online', flushPendingCreates)
  setInterval(flushPendingCreates, 30_000)
  flushPendingCreates()
}
