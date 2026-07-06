<template>
  <div class="space-y-3">
    <p class="text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Documents</p>
    <p class="text-xs text-zinc-500 dark:text-zinc-400">
      Upload photos or ID scans (JPEG, PNG, WebP, or PDF, max 5 MB). Files save immediately.
    </p>

    <div class="grid gap-3 sm:grid-cols-2">
      <div
        v-for="field in fields"
        :key="field.kind"
        class="rounded-md border border-zinc-200 bg-zinc-50 p-3 dark:border-zinc-700 dark:bg-zinc-900/50"
      >
        <div class="flex items-start justify-between gap-2">
          <div>
            <p class="text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ field.label }}</p>
            <p v-if="documentsByKind[field.kind]" class="mt-0.5 text-xs text-zinc-500 dark:text-zinc-400">
              {{ formatFileSize(documentsByKind[field.kind].size_bytes) }}
            </p>
            <p v-else class="mt-0.5 text-xs text-zinc-400 dark:text-zinc-500">No file uploaded</p>
          </div>
          <div class="flex shrink-0 gap-1">
            <button
              v-if="documentsByKind[field.kind]"
              type="button"
              class="btn-secondary px-2 py-1 text-xs"
              @click="openPreview(field.kind)"
            >
              View
            </button>
            <button
              v-if="documentsByKind[field.kind]"
              type="button"
              class="btn-secondary px-2 py-1 text-xs text-red-700 dark:text-red-400"
              :disabled="busyKind === field.kind"
              @click="removeDocument(field.kind)"
            >
              Remove
            </button>
          </div>
        </div>

        <div v-if="previewUrls[field.kind] && isImage(documentsByKind[field.kind])" class="mt-2">
          <img
            :src="previewUrls[field.kind]"
            :alt="field.label"
            class="max-h-24 rounded border border-zinc-200 object-contain dark:border-zinc-700"
          />
        </div>

        <label class="mt-3 flex cursor-pointer items-center gap-2 text-sm text-teal-700 dark:text-teal-400">
          <input
            type="file"
            class="hidden"
            accept=".jpg,.jpeg,.png,.webp,.pdf,image/jpeg,image/png,image/webp,application/pdf"
            :disabled="busyKind === field.kind"
            @change="onFileSelected(field.kind, $event)"
          />
          <span>{{ documentsByKind[field.kind] ? 'Replace file' : 'Choose file' }}</span>
          <span v-if="busyKind === field.kind" class="text-xs text-zinc-500">Uploading…</span>
        </label>
      </div>
    </div>

    <p v-if="error" class="alert-error">{{ error }}</p>
  </div>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue'
import {
  deleteDocument,
  fetchClientDocuments,
  fetchDocumentBlob,
  fetchTenantDocuments,
  formatFileSize,
  uploadClientDocument,
  uploadTenantDocument,
} from '../../api/documents'

const props = defineProps({
  module: {
    type: String,
    required: true,
    validator: (value) => ['rental', 'sales'].includes(value),
  },
  entityId: {
    type: [Number, String],
    required: true,
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

const documents = ref([])
const busyKind = ref('')
const error = ref('')
const previewUrls = reactive({})

const fields = computed(() => props.kinds.map((kind) => ({
  kind,
  label: labels[kind] || kind,
})))

const documentsByKind = computed(() => {
  const map = {}
  for (const document of documents.value) {
    map[document.kind] = document
  }
  return map
})

function isImage(document) {
  return document?.mime_type?.startsWith('image/')
}

async function loadDocuments() {
  if (!props.entityId) {
    documents.value = []
    return
  }

  documents.value = props.module === 'rental'
    ? await fetchTenantDocuments(props.entityId)
    : await fetchClientDocuments(props.entityId)

  await refreshPreviews()
}

async function refreshPreviews() {
  revokePreviews()

  for (const document of documents.value) {
    if (!isImage(document)) continue

    try {
      const blob = await fetchDocumentBlob(document.id)
      previewUrls[document.kind] = URL.createObjectURL(blob)
    } catch {
      // Preview is optional; ignore fetch errors.
    }
  }
}

function revokePreviews() {
  for (const kind of Object.keys(previewUrls)) {
    if (previewUrls[kind]) {
      URL.revokeObjectURL(previewUrls[kind])
      delete previewUrls[kind]
    }
  }
}

async function onFileSelected(kind, event) {
  const file = event.target.files?.[0]
  event.target.value = ''

  if (!file) return

  error.value = ''
  busyKind.value = kind

  try {
    const uploaded = props.module === 'rental'
      ? await uploadTenantDocument(props.entityId, kind, file)
      : await uploadClientDocument(props.entityId, kind, file)

    documents.value = [
      ...documents.value.filter((document) => document.kind !== kind),
      uploaded,
    ]

    if (previewUrls[kind]) {
      URL.revokeObjectURL(previewUrls[kind])
      delete previewUrls[kind]
    }

    if (uploaded.mime_type?.startsWith('image/')) {
      const blob = await fetchDocumentBlob(uploaded.id)
      previewUrls[kind] = URL.createObjectURL(blob)
    }
  } catch (e) {
    error.value = e.response?.data?.message
      || Object.values(e.response?.data?.errors || {})[0]?.[0]
      || 'Could not upload file.'
  } finally {
    busyKind.value = ''
  }
}

async function removeDocument(kind) {
  const document = documentsByKind.value[kind]
  if (!document) return

  error.value = ''
  busyKind.value = kind

  try {
    await deleteDocument(document.id)
    documents.value = documents.value.filter((item) => item.id !== document.id)

    if (previewUrls[kind]) {
      URL.revokeObjectURL(previewUrls[kind])
      delete previewUrls[kind]
    }
  } catch (e) {
    error.value = e.response?.data?.message || 'Could not remove file.'
  } finally {
    busyKind.value = ''
  }
}

async function openPreview(kind) {
  const document = documentsByKind.value[kind]
  if (!document) return

  try {
    const blob = await fetchDocumentBlob(document.id)
    const url = URL.createObjectURL(blob)
    window.open(url, '_blank', 'noopener,noreferrer')
    setTimeout(() => URL.revokeObjectURL(url), 60_000)
  } catch {
    error.value = 'Could not open file.'
  }
}

watch(() => props.entityId, loadDocuments)

onMounted(loadDocuments)

onBeforeUnmount(revokePreviews)
</script>
