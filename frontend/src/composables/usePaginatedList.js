import { ref } from 'vue'

/**
 * Server-side table state for paginated API resources.
 *
 * @param {(params: Record<string, unknown>) => Promise<{ data: unknown[], meta?: object }>} fetchFn
 * @param {{ perPage?: number }} options
 */
export function usePaginatedList(fetchFn, options = {}) {
  const items = ref([])
  const loading = ref(false)
  const search = ref('')
  const page = ref(1)
  const perPage = ref(options.perPage ?? 25)
  const pagination = ref(null)

  async function load() {
    loading.value = true

    try {
      const response = await fetchFn({
        page: page.value,
        per_page: perPage.value,
        search: search.value.trim() || undefined,
      })

      items.value = response.data ?? []
      pagination.value = response.meta ?? null

      if (pagination.value?.current_page) {
        page.value = pagination.value.current_page
      }
    } finally {
      loading.value = false
    }
  }

  async function reload() {
    page.value = 1
    await load()
  }

  async function goToPage(nextPage) {
    if (nextPage < 1 || (pagination.value?.last_page && nextPage > pagination.value.last_page)) {
      return
    }

    page.value = nextPage
    await load()
  }

  async function setPerPage(nextPerPage) {
    perPage.value = nextPerPage
    page.value = 1
    await load()
  }

  function onSearchChange() {
    reload()
  }

  return {
    items,
    loading,
    search,
    page,
    perPage,
    pagination,
    load,
    reload,
    goToPage,
    setPerPage,
    onSearchChange,
  }
}
