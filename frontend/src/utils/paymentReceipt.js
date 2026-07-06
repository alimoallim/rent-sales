import { escapeHtml, printHtmlDocument } from './print'
import { formatMoney } from './money'

const RECEIPT_EXTRA_STYLES = `
  .receipt {
    max-width: 32rem;
    margin: 0 auto;
    border: 1px solid #d4d4d8;
    border-radius: 0.5rem;
    overflow: hidden;
  }
  .receipt-header {
    background: #0f766e;
    color: #fff;
    padding: 1rem 1.25rem;
    text-align: center;
  }
  .receipt-header h1 {
    color: #fff;
    font-size: 1.1rem;
    margin: 0;
  }
  .receipt-header p {
    margin: 0.25rem 0 0;
    font-size: 0.8rem;
    opacity: 0.9;
  }
  .receipt-body { padding: 1.25rem; }
  .receipt-meta {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 0.75rem 1rem;
    margin-bottom: 1.25rem;
    font-size: 0.875rem;
  }
  .receipt-meta dt {
    font-size: 0.65rem;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: #71717a;
    font-weight: 600;
    margin: 0;
  }
  .receipt-meta dd {
    margin: 0.15rem 0 0;
    font-weight: 500;
    color: #18181b;
  }
  .receipt-amount {
    border: 2px solid #0f766e;
    border-radius: 0.375rem;
    padding: 1rem;
    text-align: center;
    margin-bottom: 1rem;
    background: #f0fdfa;
  }
  .receipt-amount .label {
    font-size: 0.7rem;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: #0f766e;
    font-weight: 600;
  }
  .receipt-amount .value {
    font-size: 1.75rem;
    font-weight: 700;
    color: #0f766e;
    margin-top: 0.25rem;
    font-variant-numeric: tabular-nums;
  }
  .receipt-lines {
    width: 100%;
    font-size: 0.875rem;
    border-collapse: collapse;
  }
  .receipt-lines td {
    padding: 0.4rem 0;
    border-bottom: 1px dashed #e4e4e7;
  }
  .receipt-lines td:last-child {
    text-align: right;
    font-weight: 500;
    font-variant-numeric: tabular-nums;
  }
  .receipt-footer {
    margin-top: 1.25rem;
    padding-top: 1rem;
    border-top: 1px solid #e4e4e7;
    font-size: 0.75rem;
    color: #71717a;
    text-align: center;
  }
`

function appName() {
  return import.meta.env.VITE_APP_NAME || 'Rent & Sales Platform'
}

function formatReceiptDate(value) {
  if (!value) return '—'
  return new Date(value).toLocaleDateString('en-KE', {
    day: 'numeric',
    month: 'long',
    year: 'numeric',
  })
}

function receiptNumber(payment) {
  return payment.invoice_reference?.trim() || `RCP-${payment.id}`
}

function creditedTotal(payment) {
  return Number(payment.amount || 0) + Number(payment.discount || 0)
}

function buildReceiptHtml({
  title,
  receiptLabel,
  payerLabel,
  payerName,
  buildingName,
  unitLabel,
  payment,
  module,
  extraRows = [],
}) {
  const ref = receiptNumber(payment)
  const amount = formatMoney(payment.amount, module)
  const discount = Number(payment.discount || 0)
  const totalCredited = formatMoney(creditedTotal(payment), module)

  const extraLines = extraRows
    .filter((row) => row.value && row.value !== '—')
    .map(
      (row) => `
        <tr>
          <td>${escapeHtml(row.label)}</td>
          <td>${escapeHtml(row.value)}</td>
        </tr>
      `,
    )
    .join('')

  const discountRow = discount > 0
    ? `
      <tr>
        <td>Discount</td>
        <td>${escapeHtml(formatMoney(discount, module))}</td>
      </tr>
      <tr>
        <td>Total credited</td>
        <td>${escapeHtml(totalCredited)}</td>
      </tr>
    `
    : ''

  return `
    <style>${RECEIPT_EXTRA_STYLES}</style>
    <div class="receipt">
      <div class="receipt-header">
        <h1>${escapeHtml(appName())}</h1>
        <p>${escapeHtml(title)}</p>
      </div>
      <div class="receipt-body">
        <dl class="receipt-meta">
          <div>
            <dt>Receipt no.</dt>
            <dd>${escapeHtml(ref)}</dd>
          </div>
          <div>
            <dt>Date</dt>
            <dd>${escapeHtml(formatReceiptDate(payment.paid_at))}</dd>
          </div>
          <div>
            <dt>${escapeHtml(payerLabel)}</dt>
            <dd>${escapeHtml(payerName || '—')}</dd>
          </div>
          <div>
            <dt>Building</dt>
            <dd>${escapeHtml(buildingName || '—')}</dd>
          </div>
          ${unitLabel ? `
          <div>
            <dt>Unit</dt>
            <dd>${escapeHtml(unitLabel)}</dd>
          </div>` : ''}
        </dl>

        <div class="receipt-amount">
          <div class="label">Amount received</div>
          <div class="value">${escapeHtml(amount)}</div>
        </div>

        <table class="receipt-lines">
          <tbody>
            <tr>
              <td>Payment type</td>
              <td>${escapeHtml(receiptLabel)}</td>
            </tr>
            ${discountRow}
            ${extraLines}
          </tbody>
        </table>

        <div class="receipt-footer">
          Thank you for your payment.<br />
          This is a computer-generated receipt.
        </div>
      </div>
    </div>
  `
}

/**
 * @param {Record<string, unknown>} payment
 * @param {{ tenantName?: string, buildingName?: string, unitLabel?: string }} [context]
 */
export function printRentalPaymentReceipt(payment, context = {}) {
  const payerName = context.tenantName || payment.tenant_name
  const buildingName = context.buildingName || payment.building_name

  printHtmlDocument({
    title: `Receipt ${receiptNumber(payment)}`,
    body: buildReceiptHtml({
      title: 'Rent payment receipt',
      receiptLabel: 'Rental payment',
      payerLabel: 'Tenant',
      payerName,
      buildingName,
      unitLabel: context.unitLabel || payment.unit_label,
      payment,
      module: 'rental',
    }),
  })
}

/**
 * @param {Record<string, unknown>} payment
 * @param {{ clientName?: string, buildingName?: string, unitLabel?: string }} [context]
 */
export function printSalesPaymentReceipt(payment, context = {}) {
  const payerName = context.clientName || payment.client_name
  const buildingName = context.buildingName || payment.building_name

  printHtmlDocument({
    title: `Receipt ${receiptNumber(payment)}`,
    body: buildReceiptHtml({
      title: 'Sale payment receipt',
      receiptLabel: 'Installment payment',
      payerLabel: 'Client',
      payerName,
      buildingName,
      unitLabel: context.unitLabel || payment.unit_label,
      payment,
      module: 'sales',
      extraRows: [
        { label: 'Bank', value: payment.bank || '—' },
        { label: 'Remark', value: payment.remark || '—' },
      ],
    }),
  })
}

export function canPrintPaymentReceipt(payment, module) {
  if (!payment) return false
  if (module === 'rental') return payment.status === 'active'
  return payment.status === 'active'
}
