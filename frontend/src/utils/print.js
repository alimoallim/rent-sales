/**
 * Open a self-contained HTML document for printing (iframe — no popup blocker).
 */
export function escapeHtml(value) {
  return String(value ?? '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
}

export const PRINT_DOCUMENT_STYLES = `
  * { box-sizing: border-box; }
  body {
    font-family: system-ui, -apple-system, 'Segoe UI', sans-serif;
    padding: 1.5rem;
    color: #18181b;
    line-height: 1.45;
    -webkit-print-color-adjust: exact;
    print-color-adjust: exact;
  }
  h1 { font-size: 1.25rem; margin: 0 0 0.25rem; font-weight: 600; }
  h2 { font-size: 0.95rem; margin: 1.25rem 0 0.5rem; font-weight: 600; }
  .meta { margin: 0 0 1rem; color: #52525b; font-size: 0.875rem; }
  .summary {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 0.75rem;
    margin-bottom: 1.25rem;
  }
  .card {
    border: 1px solid #e4e4e7;
    border-radius: 0.375rem;
    padding: 0.75rem;
    background: #fafafa;
  }
  .label {
    font-size: 0.7rem;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: #71717a;
    font-weight: 600;
  }
  .value {
    font-size: 1rem;
    font-weight: 600;
    margin-top: 0.25rem;
    font-variant-numeric: tabular-nums;
  }
  .value-success { color: #047857; }
  .value-warning { color: #b45309; }
  .progress {
    margin: 0.75rem 0 1rem;
  }
  .progress-meta {
    display: flex;
    justify-content: space-between;
    font-size: 0.75rem;
    color: #52525b;
    margin-bottom: 0.25rem;
  }
  .progress-track {
    height: 0.5rem;
    border-radius: 999px;
    background: #e4e4e7;
    overflow: hidden;
  }
  .progress-fill {
    height: 100%;
    border-radius: 999px;
    background: #10b981;
  }
  table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.875rem;
  }
  th, td {
    border-bottom: 1px solid #e4e4e7;
    padding: 0.5rem 0.75rem;
    text-align: left;
    vertical-align: top;
  }
  th {
    font-size: 0.7rem;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: #71717a;
    background: #f4f4f5;
  }
  tfoot td {
    font-weight: 600;
    background: #fafafa;
    border-top: 2px solid #d4d4d8;
  }
  .text-right { text-align: right; }
  .muted { color: #71717a; font-size: 0.8125rem; }
  .empty {
    border: 1px dashed #d4d4d8;
    border-radius: 0.375rem;
    padding: 1.5rem;
    text-align: center;
    color: #71717a;
    font-size: 0.875rem;
  }
  .footer-note {
    margin-top: 1rem;
    font-size: 0.75rem;
    color: #71717a;
  }
  @media print {
    body { padding: 0.5rem; }
  }
`

export function printHtmlDocument({ title, body }) {
  const html = `<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="utf-8" />
    <title>${escapeHtml(title)}</title>
    <style>${PRINT_DOCUMENT_STYLES}</style>
  </head>
  <body>${body}</body>
</html>`

  const iframe = document.createElement('iframe')
  iframe.setAttribute('title', title)
  Object.assign(iframe.style, {
    position: 'fixed',
    top: '0',
    left: '0',
    width: '0',
    height: '0',
    border: '0',
    opacity: '0',
    pointerEvents: 'none',
  })
  document.body.appendChild(iframe)

  const win = iframe.contentWindow
  const doc = win.document
  doc.open()
  doc.write(html)
  doc.close()

  const triggerPrint = () => {
    win.focus()
    win.print()
    setTimeout(() => iframe.remove(), 1000)
  }

  if (doc.readyState === 'complete') {
    setTimeout(triggerPrint, 200)
  } else {
    win.addEventListener('load', () => setTimeout(triggerPrint, 100), { once: true })
  }
}
