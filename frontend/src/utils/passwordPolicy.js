export const PASSWORD_MIN_LENGTH = 8

export const passwordRequirements = [
  {
    id: 'length',
    label: `At least ${PASSWORD_MIN_LENGTH} characters`,
    test: (password) => password.length >= PASSWORD_MIN_LENGTH,
  },
  {
    id: 'uppercase',
    label: 'One uppercase letter (A–Z)',
    test: (password) => /[A-Z]/.test(password),
  },
  {
    id: 'lowercase',
    label: 'One lowercase letter (a–z)',
    test: (password) => /[a-z]/.test(password),
  },
  {
    id: 'number',
    label: 'One number (0–9)',
    test: (password) => /\d/.test(password),
  },
  {
    id: 'symbol',
    label: 'One special character (!@#$…)',
    test: (password) => /[^A-Za-z0-9]/.test(password),
  },
]

export function evaluatePasswordRequirements(password) {
  return passwordRequirements.map((requirement) => ({
    ...requirement,
    passed: requirement.test(password),
  }))
}

export function isStrongPassword(password) {
  if (!password) return false
  return evaluatePasswordRequirements(password).every((requirement) => requirement.passed)
}

export function passwordStrengthScore(password) {
  if (!password) return 0
  return evaluatePasswordRequirements(password).filter((requirement) => requirement.passed).length
}

export function passwordStrengthLabel(password) {
  const score = passwordStrengthScore(password)
  if (score === 0) return ''
  if (score <= 2) return 'Weak'
  if (score <= 3) return 'Fair'
  if (score <= 4) return 'Good'
  return 'Strong'
}

export function firstFailedPasswordRequirement(password) {
  return evaluatePasswordRequirements(password).find((requirement) => !requirement.passed) ?? null
}
