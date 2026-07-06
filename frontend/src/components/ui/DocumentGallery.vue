<template>
  <div class="grid gap-4 sm:grid-cols-2">
    <div
      v-for="field in fields"
      :key="field.kind"
      class="rounded-md border border-zinc-200 bg-zinc-50 p-3 dark:border-zinc-700 dark:bg-zinc-900/50"
    >
      <p class="text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ field.label }}</p>

      <div v-if="documentsByKind[field.kind]" class="mt-2 space-y-2">
        <div v-if="previewUrls[field.kind] && isImage(documentsByKind[field.kind])">
          <img
            :src="previewUrls[field.kind]"
            :alt="field.label"
            class="max-h-40 w-full rounded border border-zinc-200 object-contain dark:border-zinc-700"
          />
        </div>
        <p class="text-xs text-zinc-500 dark:text-zinc-400">
          {{ formatFileSize(documentsByKind[field.kind].size_bytes) }}
          · {{ documentsByKind[field.kind].mime_type }}
        </p>
        <button type="button" class="btn-secondary px-2 py-1 text-xs" @click="openDocument(field.kind)">
          Open file
        </button>
      </div>

      <p v-else class="mt-1 text-sm text-zinc-400 dark:text-zinc-500">Not uploaded</p>
    </div>
  </div>
</template>

<script setup>
import { computed, onBeforeUnmount, reactive, watch } from 'vue'
import { fetchDocumentBlob, formatFileSize } from '../../api/documents'

const props = defineProps({
  documents: {
    type: Array,
    default: () => [],
  },
  kinds: {
    type: Array,
    required: true,
  },
})

const labels = {
  photo: 'Photo',
  signature: 'Signature',
  id_document: 'ID document',
}

const previewUrls = reactive({})

const fields = computed(() => props.kinds.map((kind) => ({
  kind,
  label: labels[kind] || kind,
})))

const documentsByKind = computed(() => {
  const map = {}
  for (const document of props.documents) {
    map[document.kind] = document
  }
  return map
})

function isImage(document) {
  return document?.mime_type?.startsWith('image/')
}

function revokePreviews() {
  for (const kind of Object.keys(previewUrls)) {
    if (previewUrls[kind]) {
      URL.revokeObjectURL(previewUrls[kind])
      delete previewUrls[kind]
    }
  }
}

async function refreshPreviews() {
  revokePreviews()

  for (const document of props.documents) {
    if (!isImage(document)) continue

    try {
      const blob = await fetchDocumentBlob(document.id)
      previewUrls[document.kind] = URL.createObjectURL(blob)
    } catch {
      // Preview is optional.
    }
  }
}

async function openDocument(kind) {
  const document = documentsByKind.value[kind]
  if (!document) return

  try {
    const blob = await fetchDocumentBlob(document.id)
    const url = URL.createObjectURL(blob)
    window.open(url, '_blank', 'noopener,noreferrer')
    setTimeout(() => URL.revokeObjectURL(url), 60_000)
  } catch {
    // Caller may show toast; keep gallery simple.
  }
}

watch(() => props.documents, refreshPreviews, { immediate: true, deep: true })

onBeforeUnmount(revokePreviews)
</script>
