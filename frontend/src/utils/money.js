export const MODULE_MONEY = {
  rental: { code: 'KES', locale: 'en-KE' },
  sales: { code: 'USD', locale: 'en-US' },
}

export function currencyCode(module = 'rental') {
  return MODULE_MONEY[module]?.code ?? MODULE_MONEY.rental.code
}

export function formatMoney(amount, module = 'rental') {
  const { code, locale } = MODULE_MONEY[module] ?? MODULE_MONEY.rental

  return new Intl.NumberFormat(locale, {
    style: 'currency',
    currency: code,
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(Number(amount || 0))
}

export function amountLabel(module = 'rental') {
  return `Amount (${currencyCode(module)})`
}

export function moneyLabel(prefix, module = 'rental') {
  return `${prefix} (${currencyCode(module)})`
}
