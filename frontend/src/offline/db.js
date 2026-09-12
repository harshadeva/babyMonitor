import Dexie from 'dexie'

export const db = new Dexie('baby-monitor')

db.version(1).stores({
  // Queued create-requests waiting to reach the server (offline-first writes).
  pending_creates: 'client_uuid, entity, created_at',
  // Most recent known entry per tracker, for instant "last fed 12m ago" style UI even offline.
  last_entries: 'entity',
  // Sticky per-device UI preferences (last-used temperature method/unit, etc).
  settings: 'key',
})

export async function queueCreate(entity, babyId, payload) {
  await db.pending_creates.put({
    client_uuid: payload.client_uuid,
    entity,
    baby_id: babyId,
    payload,
    created_at: new Date().toISOString(),
    attempts: 0,
  })
}

export async function rememberLastEntry(entity, data) {
  await db.last_entries.put({ entity, data, at: new Date().toISOString() })
}

export async function getLastEntry(entity) {
  return db.last_entries.get(entity)
}

export async function getSetting(key, fallback = null) {
  const row = await db.settings.get(key)
  return row ? row.value : fallback
}

export async function setSetting(key, value) {
  await db.settings.put({ key, value })
}
