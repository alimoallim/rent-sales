# Design System — Rent & Sales Management Platform

**Date:** 2026-06-30  
**Status:** Draft — awaiting review before UI implementation  
**Stack:** Vue 3, Tailwind CSS v4, shadcn-vue (to be added for interactive primitives)  
**Audience:** Office/operations staff (2–5 people), desktop-first, non-technical users  

This document is the single source of truth for visual and interaction patterns. Apply it uniformly across every screen. Do not invent one-off styles per page.

---

## 1. Design principles

| Principle | What it means in practice |
|-----------|---------------------------|
| **Three-second clarity** | A staff member should understand what a screen is for and what to do next without reading instructions. |
| **Calm and professional** | No gradients, decorative illustrations, or animation for its own sake. Legibility beats flair. |
| **One accent** | Teal/slate-blue is the only brand accent. Status colors (success, warning, danger) are semantic only — never used for decoration. |
| **Familiar patterns** | Sidebar + topbar + content area (Tailwind UI Application UI family). Tables for lists, cards for summaries, dialogs for focused tasks. |
| **Plain language** | “Save payment,” not “Submit.” “No tenants found for this building yet,” not “No records.” |
| **Consistent terms** | Same word for the same thing everywhere (see §10). |

---

## 2. Color tokens

Named palette mapped to Tailwind utilities. Use **semantic names in code comments/docs**; implement with the Tailwind classes below.

| Token | Tailwind reference | Hex (approx.) | Use |
|-------|-------------------|---------------|-----|
| **background** | `bg-slate-50` | `#f8fafc` | App shell background behind content |
| **surface** | `bg-white` | `#ffffff` | Cards, tables, dialogs, sidebar |
| **text-primary** | `text-slate-900` | `#0f172a` | Headings, table body, amounts |
| **text-secondary** | `text-slate-600` | `#475569` | Subtitles, helper text, table headers |
| **text-muted** | `text-slate-500` | `#64748b` | Placeholders, captions, disabled hints |
| **border** | `border-slate-200` | `#e2e8f0` | Card rings, table borders, dividers |
| **accent** | `bg-teal-700` / `text-teal-700` | `#0f766e` | Primary buttons, active nav, links, focus rings |
| **accent-hover** | `bg-teal-800` | `#115e59` | Primary button hover |
| **success** | `bg-emerald-50` `text-emerald-800` `border-emerald-200` | — | Paid up, active, completed |
| **warning** | `bg-amber-50` `text-amber-900` `border-amber-300` | — | Outstanding balance, pending, meter reminders |
| **danger** | `bg-red-50` `text-red-800` `border-red-200` | — | Void, delete, validation errors, destructive actions |
| **info** | `bg-sky-50` `text-sky-900` `border-sky-200` | — | Neutral informational banners (building-level context labels) |

### Contrast

- Body text (`text-slate-900` on `bg-white` or `bg-slate-50`): exceeds WCAG AA.
- Secondary text (`text-slate-600` on white): exceeds WCAG AA for normal size.
- Do not use `text-slate-400` or lighter for essential labels or form field values.
- Primary buttons: white text on `bg-teal-700` exceeds WCAG AA.

### Accent usage rules

- **One accent only:** `teal-700` for primary actions and active navigation.
- Do not use `slate-900` for primary buttons in the new system (legacy screens use this — migrate away).
- Status colors are **never** used for primary buttons or navigation.

---

## 3. Typography

**Font family:** Inter (already loaded via `style.css`), with `system-ui` fallback.

| Role | Size / weight | Tailwind classes | Example |
|------|---------------|------------------|---------|
| **Page title** | 20px / semibold | `text-xl font-semibold text-slate-900` | “Payments” |
| **Section heading** | 16px / semibold | `text-base font-semibold text-slate-900` | “Rental agreement” |
| **Body** | 14px / regular | `text-sm text-slate-900` | Form labels, table cells |
| **Label / caption** | 14px / medium | `text-sm font-medium text-slate-700` | Field labels above inputs |
| **Helper / muted** | 12–14px / regular | `text-xs text-slate-500` or `text-sm text-slate-600` | Subtitles, field hints |
| **Table header** | 14px / medium | `text-sm font-medium text-slate-600` | Column headers |
| **Money (emphasis)** | 14–20px / semibold | `font-semibold tabular-nums` | Balances, totals — always `tabular-nums` for alignment |

**Rules**

- Maximum ~5 distinct sizes in the app (table above).
- Use `font-semibold` for headings and money totals; `font-medium` for labels and table headers; `font-normal` for body.
- Do not use `font-bold` (700) except where semibold is insufficient (rare).
- No separate display font.

---

## 4. Spacing & layout

Use Tailwind’s default spacing scale only (`1` = 4px). No arbitrary pixel values like `p-[13px]`.

### Shell dimensions

| Element | Value | Tailwind |
|---------|-------|----------|
| Sidebar width (desktop) | 256px | `w-64` |
| Sidebar collapsed (tablet) | 0 (overlay drawer) | — |
| Topbar height | 56px | `h-14` |
| Main content padding | 24px | `p-6` |
| Content max-width | 1280px | `max-w-7xl` (wider than current `max-w-6xl` for data tables) |
| Card / panel padding | 16–24px | `p-4` or `p-6` |
| Section gap (vertical) | 24px | `space-y-6` |
| Form field gap | 16px | `gap-4` |
| Table cell padding | 12px 16px | `px-4 py-3` |

### Grid patterns

- **Stat cards (dashboard):** `grid gap-4 sm:grid-cols-2 lg:grid-cols-4`
- **Form two-column:** `grid gap-4 sm:grid-cols-2`
- **Balance breakdown (payment):** single column, `space-y-2`, right-aligned amounts in a definition list

### Responsive behavior

Use Tailwind breakpoints consistently — **never ad hoc pixel breakpoints per screen**.

| Breakpoint | Tailwind | Width | Layout role |
|------------|----------|-------|-------------|
| **Mobile** | default, `sm` optional | &lt; 640px | Single column; sidebar drawer; **tables → stacked cards**; full-screen dialogs; touch-sized controls |
| **Tablet** | `md`–`lg` | 640px–1023px | Sidebar drawer; cards show more fields than phone; two-column summaries where helpful |
| **Desktop** | `lg`+ | ≥ 1024px | Sidebar always visible; full data tables; multi-column forms |

#### Mobile (&lt; 640px)

- Sidebar hidden; hamburger opens slide-out drawer with overlay.
- **All data tables become stacked cards** — one card per row. No horizontal table scroll.
- Forms stack in a single column regardless of desktop column count.
- Dialogs are **full-screen** (or near full-screen) — not small centered modals.
- Primary actions use full-width buttons where appropriate.
- Minimum touch target **44px** (`min-h-11`, adequate padding on icon buttons).
- Topbar includes a **Dashboard** link for quick navigation home.

#### Tablet (640px–1023px)

- Sidebar remains a slide-out drawer (hamburger in topbar).
- Card lists show **primary fields on all cards** plus **secondary fields** from `md` upward (via `tabletCard` column flag).
- Balance/summary stat grids: `sm:grid-cols-2`.
- Forms may use two columns only when fields are short and pairing aids scanability (`sm:grid-cols-2`).

#### Desktop (≥ 1024px)

- Sidebar always visible (`w-64`).
- Full data tables with all columns.
- Multi-column forms and dashboard grids as designed.

#### Tables → cards pattern

Use `ResponsiveDataList` for every list (tenants, payments, charges, reports, etc.):

- **Desktop (`lg+`):** standard table inside `table-shell`.
- **Phone:** card per row with `cardTitle` + `mobileCard` columns.
- **Tablet (`md`–`lg`):** same cards with additional `tabletCard` columns.
- Row actions appear as full-width touch buttons at the bottom of each card on small screens.

#### Verification checklist (required per screen)

Before marking a screen complete, confirm it at three reference widths:

1. **~375px** (phone)
2. **~768px** (tablet)
3. **~1280px** (desktop)

Report: *"Tested/designed at phone, tablet, and desktop widths."*

#### Accessibility on all breakpoints

- Visible `focus-visible` rings on all interactive elements (already on buttons, nav, inputs).
- No feature is desktop-only — dense reports use card summaries on phone/tablet.
- Plain-language copy; consistent terminology per §10.

---

## 5. Application shell (target structure)

Reference: Tailwind UI Application UI — sidebar + topbar + main.

```
┌──────────────┬─────────────────────────────────────────────┐
│              │  Topbar: module name · user · sign out        │
│   Sidebar    ├─────────────────────────────────────────────┤
│   (nav)      │                                             │
│              │  Main content (max-w-7xl, padded)           │
│              │                                             │
└──────────────┴─────────────────────────────────────────────┘
```

### Sidebar

- White background, right border `border-slate-200`.
- App name at top (text-sm font-semibold).
- Nav items: `text-sm font-medium`, `px-3 py-2`, `rounded-lg`.
- **Active item:** `bg-teal-50 text-teal-800` (not inverted dark — easier to scan).
- **Hover:** `bg-slate-100 text-slate-900`.
- Group labels optional for Rent vs Sales modules when both visible.

### Topbar

- White background, bottom border, height `h-14`.
- Shows: current page context (breadcrumb or page title), signed-in user name, sign out.
- No duplicate of full nav (nav lives in sidebar only).

### Building vs tenant context (metering rule)

Visually separate **tenant balance** screens from **building operating expense** screens:

| Context | Nav label | Page subtitle pattern | Accent hint |
|---------|-----------|----------------------|-------------|
| Tenant meter readings (water, electricity) | “Water” / “Electricity” | “Tenant meter readings and charges” | None — standard surface |
| Building utilities (Nairobi Water, building electricity) | “Building utilities” | “Building operating expenses — not charged to tenants” | `info` banner at top of every utilities screen |

Never place tenant water and building water in the same nav item or ambiguous label.

---

## 6. Components

### 6.1 Buttons

| Variant | Appearance | Use |
|---------|------------|-----|
| **Primary** | `bg-teal-700 text-white hover:bg-teal-800` · `rounded-lg px-4 py-2 text-sm font-medium` | Save payment, register tenant, main forward action |
| **Secondary** | `border border-slate-300 bg-white text-slate-700 hover:bg-slate-50` | Cancel, export, filter actions |
| **Destructive** | `border border-red-200 bg-white text-red-700 hover:bg-red-50` | Void payment, delete (with confirm dialog) |
| **Ghost / link** | `text-teal-700 hover:underline` | Table row actions (Edit, View) |

**Rules**

- One primary button per dialog or form footer (right-aligned).
- Destructive actions never use filled red buttons in the main form footer — use secondary destructive style + confirmation dialog.
- Disabled: `opacity-50 cursor-not-allowed`.
- Focus: `focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-teal-600 focus-visible:ring-offset-2`.

### 6.2 Form fields

**Label placement:** above the field, `text-sm font-medium text-slate-700`, `mb-1`.

**Input / select (default)**

```
w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900
placeholder:text-slate-400
focus:border-teal-600 focus:outline-none focus:ring-1 focus:ring-teal-600
```

**Error state**

- Border `border-red-400`, ring `ring-red-200`.
- Error message below field: `text-sm text-red-600`, plain language (“Enter an amount greater than zero.”).

**Yes/No toggles (contract metering)**

- Segmented control: two buttons in `inline-flex rounded-lg border border-slate-300 p-1`.
- Selected: `bg-teal-700 text-white`; unselected: `text-slate-700`.

**Date fields:** native `<input type="date">` styled like text inputs (shadcn-vue DatePicker optional later).

**Searchable selects (tenant, unit):** shadcn-vue Combobox when lists are long; standard `<select>` acceptable for &lt;20 items.

### 6.3 Data tables

Canonical list pattern for tenants, payments, charges, reports.

```
Container: overflow-hidden rounded-xl bg-white ring-1 ring-slate-200
Table:     min-w-full text-sm
Header:    bg-slate-50 text-left text-slate-600
Header cell: px-4 py-3 font-medium
Body row:  border-t border-slate-100 hover:bg-slate-50
Body cell: px-4 py-3
Money cols: text-right tabular-nums
Actions:   text-right, ghost links
```

- Empty state inside table: single row, `colspan` full width, centered `text-slate-500 py-8`.
- Pagination (when API paginates): below table, secondary buttons “Previous” / “Next” + “Showing X–Y.”
- No zebra striping — hover only.

### 6.4 Cards / panels

```
rounded-xl bg-white p-4 ring-1 ring-slate-200
```

- Dashboard stat cards: label `text-sm text-slate-500`, value `text-2xl font-semibold tabular-nums`.
- Grouped form sections (e.g. rental agreement): `rounded-lg border border-slate-200 bg-slate-50 p-4` inside a dialog or page.

### 6.5 Status badges

Pill: `rounded-full px-2.5 py-0.5 text-xs font-medium`

| Meaning | Colors | Used for |
|---------|--------|----------|
| Active / paid / vacant (positive) | `bg-emerald-100 text-emerald-800` | Active tenant, paid water bill, active payment |
| Pending / outstanding / owes | `bg-amber-100 text-amber-900` | Pending bill, positive balance |
| Inactive / voided | `bg-slate-100 text-slate-600` | Moved-out tenant, voided payment |
| Credit balance | `bg-emerald-50 text-emerald-800` | Tenant overpaid |
| Danger (rare badge) | `bg-red-100 text-red-800` | Only if status is explicitly “voided” in destructive sense |

Use the **same mapping everywhere** — a “pending” water bill and “owes money” balance both use amber family, but with clear adjacent text (“Pending” vs “KES 12,000 due”).

### 6.6 Dialogs / modals

Use **shadcn-vue Dialog** for:

- Record payment, register tenant, record water/electricity bill
- Confirm void / destructive actions

Pattern:

- Overlay: `bg-black/40`
- Panel: `max-w-lg` (forms) or `max-w-2xl` (tenant registration with agreement section)
- Title: section heading typography
- Footer: secondary Cancel (left or right per shadcn default) + primary Save on the right

Avoid full-page opaque overlays without dialog chrome for standard CRUD — use dialog or dedicated route, not anonymous floating forms.

### 6.7 Toasts / notifications

Use **shadcn-vue Sonner** (or Toast) for action feedback:

| Type | Use | Duration |
|------|-----|----------|
| Success | “Payment saved.” | 4s |
| Error | “Could not save payment. Check the amount and try again.” | Until dismissed |
| Info | Rare — prefer inline banners for persistent context | 5s |

Plain language, one sentence, no error codes.

### 6.8 Empty states

Centered in content area or table:

- **Heading:** `text-sm font-medium text-slate-900` — specific (“No payments recorded yet”)
- **Body:** `text-sm text-slate-600` — what to do next (“Record a payment using the button above.”)
- Optional primary action button if not already in page header.

No illustrations.

---

## 7. Domain-specific UI patterns

### 7.1 Outstanding balance breakdown (payment screen)

A **summary card** at the top of the payment form, before amount entry.

**Structure**

```
┌─ Outstanding balance ─────────────────────────────┐
│  Water owed          KES 2,700                    │
│  Electricity owed    KES 0                      │
│  Services owed       KES 10,000                   │
│  Rent owed           KES 65,000                 │
│  ─────────────────────────────────                │
│  Total due           KES 77,700    (semibold)     │
└───────────────────────────────────────────────────┘
```

**Styling**

- Container: `rounded-lg border p-4`
- **Owes money:** `border-amber-200 bg-amber-50`
- **Paid up:** `border-emerald-200 bg-emerald-50` — title “Tenant is fully paid up”
- **Credit:** `border-emerald-200 bg-emerald-50` — show credit balance line
- Definition list: `flex justify-between`, labels `text-slate-600`, amounts `font-medium tabular-nums`
- Total row: top border `border-slate-200 pt-2 font-semibold`

**Agreement metering** (secondary block below breakdown, smaller):

- `rounded-lg border border-slate-200 bg-slate-50 p-4 text-sm`
- “Water: Required monthly” / “Electricity: Not required”

### 7.2 Missing meter reading reminder (payment screen)

**Non-alarming but unmissable** — staff must notice it, but it is not a validation error dump.

**Styling:** `warning` token — `border-amber-300 bg-amber-50 text-amber-950`

**Content**

- Short title: `font-semibold` — “Meter reading required before payment”
- Body: plain language naming tenant, utility, and period (from API `message`)
- Primary action button: “Enter water reading” / “Enter electricity reading” → navigates to correct tenant bill screen with period pre-filled

**Placement:** directly below balance breakdown, above payment amount field.

**When payment is blocked:** Save button disabled (`opacity-50`); banner remains warning-toned (not red). Red is reserved for validation errors and destructive actions.

### 7.3 Overpayment warning

Separate from meter reminder — `warning` border, explains overpayment will create credit; requires confirm before save.

### 7.4 Building utilities screens

Persistent **info** banner at top:

> “These are building operating costs. They are not charged to individual tenants.”

Prevents confusion with tenant water/electricity bill screens.

---

## 8. shadcn-vue integration plan

Install shadcn-vue per [shadcn-vue.com](https://www.shadcn-vue.com) into `frontend/src/components/ui/`.

**Use shadcn-vue for**

- Dialog (payment form, tenant form, confirmations)
- Button (primitive — restyle with tokens above)
- Input, Label, Select
- Combobox (tenant/building pickers)
- Toast / Sonner
- Dropdown Menu (user menu in topbar)
- Sheet (mobile sidebar)

**Restyle defaults**

- Map shadcn `--primary` CSS variables to teal-700 equivalents.
- Map `--radius` to `0.5rem` (`rounded-lg`).
- Remove shadcn’s default zinc palette in favor of slate + teal tokens above.

**Do not use shadcn for**

- Data tables (custom markup per §6.3)
- Balance breakdown (custom layout)
- Dashboard stat cards

---

## 9. Focus & accessibility

- All interactive elements: `focus-visible:ring-2 focus-visible:ring-teal-600 focus-visible:ring-offset-2`.
- Dialogs: trap focus, Escape to close (except destructive confirm).
- Form errors: linked via `aria-describedby` to error text.
- Table headers: `scope="col"`.
- Icon-only buttons: `aria-label` required.
- Color is never the only indicator of status — always pair badge color with text label.

---

## 10. Terminology (rent module)

Use consistently in nav, headings, buttons, and empty states.

| Use this | Not this | Notes |
|----------|----------|-------|
| Buildings | Categories, properties | Legacy `categories` |
| Units | Houses, apartments | Legacy `houses` |
| Tenants | Clients, renters | Rent module only |
| Payments | Invoices, vouchers (except “invoice reference” field label) | |
| Charges | Bills (for rent/service charges) | |
| Water | Water bills (nav may shorten to “Water”) | Tenant-level meter |
| Electricity | — | Tenant-level meter |
| Building utilities | Utilities (always qualify in subtitle) | Nairobi Water + building electricity |
| Move out | Vacate, deactivate | |
| Void (payment) | Delete | |
| Record payment | Submit, create payment | |
| Register tenant | Add tenant, create tenant | |
| Active / Moved out | Inactive | Tenant status |
| Total due | Balance, outstanding (either ok in body text; **Total due** in breakdown header) | |

**Sales module (future UI):** use **Clients** and **Sale units** — never “Tenants” in sales context.

---

## 11. File & component organization (implementation)

```
frontend/src/
├── components/
│   ├── ui/              # shadcn-vue primitives (Button, Dialog, …)
│   ├── layout/          # AppSidebar, AppTopbar, AppShell
│   ├── data/            # DataTable, EmptyState, StatusBadge, MoneyCell
│   ├── rental/          # BalanceBreakdown, MeterReadingBanner, …
│   └── PageHeader.vue   # keep; align to tokens
├── layouts/
│   └── AppLayout.vue    # refactor to sidebar + topbar shell
└── style.css            # Inter import, shadcn CSS variables
```

---

## 12. Build order (after approval)

1. **This document** — review and approve ✓ (current step)
2. **Layout shell** — sidebar, topbar, responsive drawer
3. **Dashboard** — rental home with stat cards
4. **High-frequency screens** — Payments (with breakdown + meter banner), Tenants, payment list
5. **Remaining rental CRUD** — buildings, units, charges, water, electricity, utilities, expenses, payroll, shareholders
6. **Reports** — consistent table + export actions
7. **Sales module** — when backend ready; reuse same system

**Checkpoint after each step** — do not proceed without confirmation.

---

## 13. Out of scope for this document

- Backend API or data model changes
- New features not already in requirements
- Marketing/landing pages
- Dark mode (not required for v1)
- Custom icon set (use Lucide via shadcn-vue when icons are needed; sparingly)

---

## 14. Quick reference — migrate from current UI

The existing screens use ad hoc `slate-900` primary buttons, horizontal top nav, and inconsistent reminder colors (red for meter block). During implementation:

| Current | Target |
|---------|--------|
| `bg-slate-900` buttons | `bg-teal-700` primary |
| Horizontal nav in header | Sidebar navigation |
| `max-w-6xl` | `max-w-7xl` for main content |
| Red meter reminder | Amber warning banner (§7.2) |
| `window.confirm` | shadcn Dialog for confirmations |
| Inline `alert()` errors | Toast for success; inline field errors for validation |

---

*Review this document and confirm or request changes before layout shell implementation begins.*
