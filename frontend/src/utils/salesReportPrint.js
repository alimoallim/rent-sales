import { escapeHtml, printHtmlDocument } from './print'
import { currencyCode, formatMoney } from './money'

function buildSummaryHtml(cards) {
  if (!cards.length) return ''

  const items = cards
    .map(
      (card) => `
        <div class="card">
          <div class="label">${escapeHtml(card.label)}</div>
          <div class="value">${escapeHtml(card.value)}</div>
        </div>
      `,
    )
    .join('')

  return `<div class="summary">${items}</div>`
}

function buildTableHtml(columns, rows) {
  if (!rows.length) {
    return '<div class="empty">No records in this report.</div>'
  }

  const headers = columns
    .map(
      (column) =>
        `<th class="${column.align === 'right' ? 'text-right' : ''}">${escapeHtml(column.label)}</th>`,
    )
    .join('')

  const body = rows
    .map(
      (row) => `
        <tr>
          ${columns
            .map((column) => {
              const raw = column.format ? column.format(row) : row[column.key]
              const value = raw === null || raw === undefined || raw === '' ? '—' : String(raw)
              const align = column.align === 'right' ? 'text-right' : ''
              return `<td class="${align}">${escapeHtml(value)}</td>`
            })
            .join('')}
        </tr>
      `,
    )
    .join('')

  return `
    <table>
      <thead>
        <tr>${headers}</tr>
      </thead>
      <tbody>${body}</tbody>
    </table>
  `
}

export function printSalesReport({ title, subtitle, summaries = [], sections = [] }) {
  const sectionHtml = sections
    .map((section) => {
      const heading = section.title ? `<h2>${escapeHtml(section.title)}</h2>` : ''
      return `${heading}${buildTableHtml(section.columns, section.rows)}`
    })
    .join('')

  const body = `
    <h1>${escapeHtml(title)}</h1>
    ${subtitle ? `<p class="meta">${escapeHtml(subtitle)}</p>` : ''}
    ${buildSummaryHtml(summaries)}
    ${sectionHtml}
    <p class="footer-note">Generated ${escapeHtml(new Date().toLocaleString('en-KE', { dateStyle: 'medium', timeStyle: 'short' }))} · All amounts in ${escapeHtml(currencyCode('sales'))}</p>
  `

  printHtmlDocument({ title, body })
}

export function formatReportDate(value) {
  if (!value) return '—'
  return new Date(value).toLocaleDateString('en-KE', {
    day: 'numeric',
    month: 'short',
    year: 'numeric',
  })
}

export function moneyColumn(key, label, module = 'sales') {
  return {
    key,
    label,
    align: 'right',
    format: (row) => formatMoney(row[key], module),
  }
}
