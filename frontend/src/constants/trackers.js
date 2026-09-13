// Single source of truth for each tracker's label/emoji/color/time-field,
// shared between the quick-log grid, history list, and charts so they never
// drift out of sync with each other.
export const TRACKERS = {
  feedings: { label: 'Feed', emoji: '🍼', color: '#ff9d66', time: 'started_at' },
  sleeps: { label: 'Sleep', emoji: '😴', color: '#b9a6e8', time: 'started_at' },
  diapers: { label: 'Diaper', emoji: '🧷', color: '#8fd9c4', time: 'occurred_at' },
  temperatures: { label: 'Temp', emoji: '🌡️', color: '#ff8fab', time: 'measured_at' },
  growths: { label: 'Growth', emoji: '📏', color: '#8fc1e8', time: 'measured_at' },
  medications: { label: 'Medicine', emoji: '💊', color: '#ffd976', time: 'given_at' },
  symptoms: { label: 'Symptom', emoji: '📝', color: '#c9a8d4', time: 'occurred_at' },
}
