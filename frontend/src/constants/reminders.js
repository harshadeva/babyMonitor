// Sensible starting points — feed/diaper/temperature checks are the ones
// newborn care most commonly runs on a rough schedule; the rest default off
// since they're not naturally periodic, but stay fully configurable.
export const DEFAULT_REMINDERS = {
  feedings: { enabled: true, hours: 3 },
  sleeps: { enabled: false, hours: 4 },
  diapers: { enabled: true, hours: 3 },
  temperatures: { enabled: true, hours: 6 },
  growths: { enabled: false, hours: 168 },
  medications: { enabled: false, hours: 24 },
  symptoms: { enabled: false, hours: 24 },
}

export const MIN_HOURS = 1
export const MAX_HOURS = 72
