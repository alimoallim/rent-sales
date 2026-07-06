/**
 * Normalize user input for case-insensitive prefix matching.
 */
export function normalizeSearchQuery(value) {
  return String(value ?? '')
    .trim()
    .toLocaleLowerCase('en-US')
}

/**
 * @param {unknown} haystack
 * @param {unknown} query
 */
export function textMatchesSearch(haystack, query) {
  const needle = normalizeSearchQuery(query)
  if (!needle) return true

  return normalizeSearchQuery(haystack).startsWith(needle)
}

/**
 * @param {unknown} values
 * @param {unknown} query
 */
export function valuesMatchSearch(values, query) {
  const needle = normalizeSearchQuery(query)
  if (!needle) return true

  const parts = Array.isArray(values) ? values : [values]

  return parts
    .filter((value) => value !== null && value !== undefined && value !== '')
    .some((value) => normalizeSearchQuery(value).startsWith(needle))
}
