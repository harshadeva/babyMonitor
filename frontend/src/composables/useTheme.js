import { ref } from 'vue'

const THEME_KEY = 'theme'

function loadInitialTheme() {
  try {
    const stored = localStorage.getItem(THEME_KEY)
    if (stored === 'light' || stored === 'dark') return stored
  } catch {
    // localStorage unavailable (private mode, etc.) — fall through to default
  }
  return 'light' // light is the default regardless of system/OS preference
}

function applyTheme(value) {
  document.documentElement.setAttribute('data-theme', value)
}

const theme = ref(loadInitialTheme())
applyTheme(theme.value)

export function useTheme() {
  function toggleTheme() {
    theme.value = theme.value === 'light' ? 'dark' : 'light'
    applyTheme(theme.value)
    try {
      localStorage.setItem(THEME_KEY, theme.value)
    } catch {
      // ignore — theme just won't persist across reloads
    }
  }

  return { theme, toggleTheme }
}
