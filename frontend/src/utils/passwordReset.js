const RESET_SESSION_KEY = 'passwordResetSession'

const TTL_MS = 15 * 60 * 1000

export function saveResetSession({ email, code }) {
  sessionStorage.setItem(
    RESET_SESSION_KEY,
    JSON.stringify({
      email,
      code,
      verifiedAt: Date.now(),
    }),
  )
}

export function loadResetSession() {
  const raw = sessionStorage.getItem(RESET_SESSION_KEY)
  if (!raw) return null

  try {
    const session = JSON.parse(raw)
    if (!session?.email || !session?.code || !session?.verifiedAt) {
      clearResetSession()
      return null
    }

    if (Date.now() - session.verifiedAt > TTL_MS) {
      clearResetSession()
      return null
    }

    return session
  } catch {
    clearResetSession()
    return null
  }
}

export function clearResetSession() {
  sessionStorage.removeItem(RESET_SESSION_KEY)
}
