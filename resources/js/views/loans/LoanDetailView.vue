<template>
  <div class="space-y-5 max-w-3xl">
    <div v-if="loading" class="space-y-4">
      <div class="skeleton h-16" />
      <div class="skeleton h-40" />
      <div class="skeleton h-40" />
    </div>

    <template v-else-if="loan">
      <PageHeader
        :title="loan.summary || loan.items_label || 'Loan'"
        :subtitle="whoAndWhere"
        icon="handshake"
        back-to="/loans"
        back-label="Active loans"
      >
        <template #actions>
          <StatusBadge :status="badgeStatus" />
        </template>
      </PageHeader>

      <p class="-mt-2 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs muted">
        <span class="font-mono">{{ loan.reference || `#${loan.id}` }}</span>
        <span class="flex items-center gap-1" :class="overdue ? 'text-danger-600 font-semibold' : ''">
          <Icon name="calendar" :size="13" />
          {{ dueLabel }}
        </span>
      </p>

      <!-- What to do next, in plain words -->
      <div
        v-if="hint"
        class="flex items-start gap-2.5 rounded-2xl border px-4 py-3 text-sm"
        :class="overdue ? 'border-danger-600/20 bg-danger-100 text-danger-600' : 'border-info-600/20 bg-info-100 text-info-600'"
      >
        <Icon :name="overdue ? 'alert' : 'info'" :size="18" class="mt-0.5 shrink-0" />
        <span>{{ hint }}</span>
      </div>

      <!-- Tools on this loan -->
      <section class="card">
        <header class="flex items-center gap-2 p-4 pb-3">
          <Icon name="boxes" :size="18" class="text-content-muted" />
          <p class="section-title">Tools on this loan</p>
        </header>
        <ul class="divide-rows border-t border-line">
          <li v-for="li in loanItems" :key="li.id" class="flex flex-wrap items-center gap-3 px-4 py-3">
            <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-neutral-100 text-content-muted shrink-0">
              <Icon name="package" :size="18" />
            </span>
            <div class="min-w-0 flex-1">
              <p class="text-sm font-medium text-content truncate">{{ itemLabel(li) }}</p>
              <p class="text-xs muted flex flex-wrap items-center gap-x-2.5 gap-y-1 mt-0.5">
                <span v-if="li.item?.asset_tag" class="font-mono">{{ li.item.asset_tag }}</span>
                <span v-if="li.item?.tool_type?.name" class="flex items-center gap-1">
                  <Icon name="wrench" :size="12" />
                  {{ li.item.tool_type.name }}
                </span>
                <span v-if="li.condition_out" class="flex items-center gap-1">
                  <Icon name="check-circle" :size="12" />
                  Went out {{ conditionLabel(li.condition_out) }}
                </span>
                <span v-if="li.fuel_pct_out !== null && li.fuel_pct_out !== undefined" class="flex items-center gap-1">
                  <Icon name="fuel" :size="12" />
                  {{ li.fuel_pct_out }}% fuel out
                </span>
              </p>
              <p v-if="li.inspection" class="mt-1 flex items-center gap-1.5 text-xs" :class="li.inspection.damage_found ? 'text-danger-600' : 'text-brand-700'">
                <Icon :name="li.inspection.damage_found ? 'alert' : 'check-circle'" :size="13" />
                {{ li.inspection.damage_found ? (li.inspection.damage_description || 'Problem found') : 'Looks good' }}
              </p>
            </div>
            <StatusBadge :status="li.status" />
          </li>
        </ul>
        <RouterLink
          v-if="loan.borrow_request"
          :to="`/requests/${loan.borrow_request.id}`"
          class="flex items-center gap-2 border-t border-line px-4 py-3 text-sm font-medium text-brand-700 hover:bg-surface"
        >
          <Icon name="clipboard" :size="16" />
          See the request this came from
          <span class="font-mono text-xs muted">{{ loan.borrow_request.reference }}</span>
        </RouterLink>
      </section>

      <!-- 1. Pick-up: depot confirms the borrower is taking the tools -->
      <section v-if="loan.status === 'reserved' && auth.can('checkout_items')" class="card">
        <header class="flex items-center gap-2.5 p-4 sm:p-5 pb-3">
          <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-brand-100 text-brand-700 shrink-0">
            <Icon name="package" :size="18" />
          </span>
          <div>
            <p class="section-title">Confirm pick-up</p>
            <p class="text-xs muted">Check each tool before the borrower takes them away.</p>
          </div>
        </header>

        <div class="space-y-4 border-t border-line p-4 sm:p-5">
          <div
            v-if="checkoutWarnings.length || checkoutBlocking.length"
            class="rounded-xl border px-3 py-2.5 text-sm"
            :class="checkoutBlocking.length ? 'border-danger-600/25 bg-danger-100 text-danger-600' : 'border-warn-600/25 bg-warn-100 text-warn-600'"
          >
            <p class="font-semibold flex items-center gap-1.5">
              <Icon name="alert" :size="16" />
              {{ checkoutBlocking.length ? 'Service overdue — pick-up is blocked' : 'Service overdue — pick-up still allowed' }}
            </p>
            <ul class="mt-1.5 list-disc pl-5 space-y-0.5">
              <li v-for="p in [...checkoutBlocking, ...checkoutWarnings]" :key="p.id">{{ p.name }}</li>
            </ul>
          </div>

          <div v-for="row in checkoutForm" :key="row.item_id" class="card p-3.5 space-y-3.5">
            <p class="text-sm font-semibold text-content">{{ row.name }}</p>

            <div>
              <label class="label">Scan or type the QR code (optional)</label>
              <input v-model="row.qr_token" type="text" class="input font-mono" placeholder="Scan the label to confirm this unit" autocomplete="off" />
            </div>

            <div>
              <p class="label">How does it look?</p>
              <div class="flex flex-wrap gap-2">
                <button
                  v-for="c in CONDITIONS"
                  :key="c.value"
                  type="button"
                  class="chip"
                  :class="row.condition_out === c.value ? 'chip-active' : ''"
                  @click="row.condition_out = c.value"
                >
                  <Icon :name="c.icon" :size="16" />
                  {{ c.label }}
                </button>
              </div>
            </div>

            <div class="rounded-xl border border-line bg-surface p-3">
              <div class="flex items-center justify-between gap-3">
                <span class="flex items-center gap-2 text-sm font-medium text-content-muted">
                  <Icon name="fuel" :size="18" :class="fuelTextClass(row.fuel_pct_out)" />
                  Fuel when it leaves
                </span>
                <span class="text-2xl font-semibold tabular-nums" :class="fuelTextClass(row.fuel_pct_out)">
                  {{ row.fuel_pct_out }}%
                </span>
              </div>
              <div class="mt-2 h-3 w-full overflow-hidden rounded-full bg-neutral-200">
                <div class="h-full rounded-full transition-all" :class="fuelBarClass(row.fuel_pct_out)" :style="{ width: `${row.fuel_pct_out}%` }" />
              </div>
              <input v-model.number="row.fuel_pct_out" type="range" min="0" max="100" step="5" class="fuel-slider" aria-label="Fuel when it leaves" />
              <div class="flex justify-between text-xs muted">
                <span>Empty</span>
                <span>Half</span>
                <span>Full</span>
              </div>
            </div>
          </div>

          <div
            v-if="companionHints.length"
            class="rounded-xl border border-warn-600/25 bg-warn-100 px-3 py-2.5 text-sm text-warn-700"
          >
            <p class="font-semibold flex items-center gap-1.5">
              <Icon name="alert" :size="16" />
              Companion suggestions
            </p>
            <ul class="mt-1.5 list-disc pl-5 space-y-0.5">
              <li v-for="(hint, i) in companionHints" :key="i">{{ hint }}</li>
            </ul>
            <p class="mt-1 text-xs">Pick-up is still allowed if you skip these.</p>
          </div>

          <div v-for="group in companionSuggestions" :key="group.item_id" class="card p-3.5 space-y-3">
            <p class="text-sm font-semibold text-content">With {{ group.item_label }}</p>

            <div v-if="group.companions?.length" class="space-y-2">
              <p class="label">Add companion (scan or pick)</p>
              <div class="flex flex-wrap gap-2">
                <button
                  v-for="c in group.companions"
                  :key="c.id"
                  type="button"
                  class="chip"
                  :class="isCompanionAttached(group.item_id, c.id) ? 'chip-active' : ''"
                  @click="toggleCompanion(group.item_id, c)"
                >
                  {{ c.label }}
                </button>
              </div>
              <div v-for="att in companionsFor(group.item_id)" :key="att.item_id" class="rounded-lg bg-surface p-2">
                <label class="label">Scan code for {{ att.label }}</label>
                <input v-model="att.qr_token" type="text" class="input font-mono" placeholder="Optional scan" autocomplete="off" />
              </div>
            </div>

            <div v-if="group.consumables?.length" class="space-y-2">
              <p class="label">Estimate consumables taken</p>
              <div
                v-for="sku in group.consumables"
                :key="sku.id"
                class="flex flex-wrap items-center justify-between gap-2 rounded-lg border border-line px-3 py-2 text-sm"
              >
                <span class="min-w-0">
                  <span class="font-medium">{{ sku.label }}</span>
                  <span class="muted"> · {{ sku.stock_qty }} {{ sku.stock_unit }} on hand</span>
                </span>
                <input
                  v-model.number="consumableQty[consumableKey(group.item_id, sku.id)]"
                  type="number"
                  min="0"
                  step="0.01"
                  class="input w-24"
                  placeholder="0"
                />
              </div>
            </div>
          </div>

          <div v-if="checkoutBlocking.length" class="space-y-2">
            <label class="flex items-start gap-2.5 rounded-xl bg-surface p-3 text-sm text-content-muted">
              <input v-model="maintenanceOverride" type="checkbox" class="mt-0.5 h-4 w-4 rounded border-neutral-300" />
              <span>
                Override and allow pick-up anyway
                <span class="block text-xs muted">This is logged. Only use if the depot accepts the risk.</span>
              </span>
            </label>
            <div v-if="maintenanceOverride">
              <label class="label">Why are you overriding?</label>
              <input v-model="maintenanceOverrideReason" type="text" class="input" placeholder="Example: oil change booked for tomorrow" />
            </div>
          </div>

          <p v-if="actionError.checkout" class="flex items-center gap-2 text-sm text-danger-600">
            <Icon name="alert" :size="16" />
            {{ actionError.checkout }}
          </p>

          <button class="btn-primary w-full sm:w-auto" :disabled="acting || (checkoutBlocking.length && !maintenanceOverride)" @click="doCheckout">
            <Icon :name="acting ? 'refresh' : 'package'" :size="18" />
            {{ acting ? 'Saving…' : 'Confirm pick-up' }}
          </button>
        </div>
      </section>

      <!-- 2. Return: borrower says they are bringing the tools back -->
      <section v-if="canSelfReturn" class="card">
        <header class="flex items-center gap-2.5 p-4 sm:p-5 pb-3">
          <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-info-100 text-info-600 shrink-0">
            <Icon name="arrow-left" :size="18" />
          </span>
          <div>
            <p class="section-title">Return tools</p>
            <p class="text-xs muted">Describe the condition of each tool you are returning.</p>
          </div>
        </header>

        <div class="space-y-4 border-t border-line p-4 sm:p-5">
          <div v-for="row in returnForm" :key="row.item_id" class="card p-3.5 space-y-3.5">
            <p class="text-sm font-semibold text-content">{{ row.name }}</p>

            <div class="rounded-xl border border-line bg-surface p-3">
              <div class="flex items-center justify-between gap-3">
                <span class="flex items-center gap-2 text-sm font-medium text-content-muted">
                  <Icon name="fuel" :size="18" :class="fuelTextClass(row.fuel_pct)" />
                  Fuel now
                </span>
                <span class="text-2xl font-semibold tabular-nums" :class="fuelTextClass(row.fuel_pct)">
                  {{ row.fuel_pct }}%
                </span>
              </div>
              <div class="mt-2 h-3 w-full overflow-hidden rounded-full bg-neutral-200">
                <div class="h-full rounded-full transition-all" :class="fuelBarClass(row.fuel_pct)" :style="{ width: `${row.fuel_pct}%` }" />
              </div>
              <input v-model.number="row.fuel_pct" type="range" min="0" max="100" step="5" class="fuel-slider" aria-label="Fuel now" />
              <div class="flex justify-between text-xs muted">
                <span>Empty</span>
                <span>Half</span>
                <span>Full</span>
              </div>
            </div>

            <div>
              <p class="label">How does it look?</p>
              <div class="flex flex-wrap gap-2">
                <button
                  v-for="c in CONDITIONS"
                  :key="c.value"
                  type="button"
                  class="chip"
                  :class="row.condition === c.value ? 'chip-active' : ''"
                  @click="row.condition = c.value"
                >
                  <Icon :name="c.icon" :size="16" />
                  {{ c.label }}
                </button>
              </div>
            </div>

            <div v-if="row.tracks_usage_hours" class="grid sm:grid-cols-2 gap-3">
              <div>
                <label class="label">Hour-meter reading (preferred)</label>
                <input v-model.number="row.usage_hours_reading" type="number" min="0" step="0.1" class="input" :placeholder="row.usage_hours_out != null ? `Was ${row.usage_hours_out}` : 'Example: 125.5'" />
              </div>
              <div>
                <label class="label">Or hours used this trip</label>
                <input v-model.number="row.usage_hours_estimate" type="number" min="0" step="0.5" class="input" placeholder="Example: 3.5" />
              </div>
            </div>

            <label class="flex items-start gap-2.5 rounded-xl bg-surface p-3 text-sm text-content-muted">
              <input v-model="row.damage_found" type="checkbox" class="mt-0.5 h-4 w-4 rounded border-neutral-300" />
              <span>
                Something is broken
                <span class="block text-xs muted">Tick this if the tool is damaged or not working.</span>
              </span>
            </label>
            <input
              v-if="row.damage_found"
              v-model="row.damage_description"
              type="text"
              class="input"
              placeholder="What is wrong? Short words are fine."
            />

            <label class="flex items-start gap-2.5 rounded-xl bg-warn-100/50 p-3 text-sm text-content-muted">
              <input v-model="row.end_of_life_soon" type="checkbox" class="mt-0.5 h-4 w-4 rounded border-neutral-300" />
              <span>
                This tool is near the end of its life
                <span class="block text-xs muted">Puts it into this year’s replacement plan.</span>
              </span>
            </label>
          </div>

          <p v-if="actionError.return" class="flex items-center gap-2 text-sm text-danger-600">
            <Icon name="alert" :size="16" />
            {{ actionError.return }}
          </p>

          <button class="btn-primary w-full sm:w-auto" :disabled="acting" @click="doSelfReturn">
            <Icon :name="acting ? 'refresh' : 'arrow-left'" :size="18" />
            {{ acting ? 'Saving…' : 'Submit return' }}
          </button>
        </div>
      </section>

      <!-- 3. Depot inspects tools after return -->
      <section v-if="loan.status === 'return_pending' && auth.can('checkout_items')" class="card">
        <header class="flex items-center gap-2.5 p-4 sm:p-5 pb-3">
          <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-warn-100 text-warn-600 shrink-0">
            <Icon name="clipboard" :size="18" />
          </span>
          <div>
            <p class="section-title">Inspect returned tools</p>
            <p class="text-xs muted">Check each tool and say if it is OK to go back into stock.</p>
          </div>
        </header>

        <div class="space-y-4 border-t border-line p-4 sm:p-5">
          <div
            v-if="loan.missing_companions?.length"
            class="rounded-xl border border-warn-600/25 bg-warn-100 px-3 py-2.5 text-sm text-warn-700"
          >
            <p class="font-semibold">Companions still out</p>
            <ul class="mt-1 list-disc pl-5">
              <li v-for="(m, i) in loan.missing_companions" :key="i">
                {{ m.companion }} went with {{ m.primary }}
              </li>
            </ul>
          </div>

          <div v-if="consumableReviewForm.length" class="card p-3.5 space-y-3">
            <p class="text-sm font-semibold text-content">Confirm consumables used</p>
            <div
              v-for="row in consumableReviewForm"
              :key="row.id"
              class="flex flex-wrap items-center justify-between gap-2 rounded-lg bg-surface px-3 py-2 text-sm"
            >
              <span>
                {{ row.label }}
                <span class="muted"> · estimated {{ row.qty_estimated }}</span>
              </span>
              <input v-model.number="row.qty_used" type="number" min="0" step="0.01" class="input w-28" />
            </div>
          </div>

          <div v-for="row in reviewForm" :key="row.item_id" class="card p-3.5 space-y-3.5">
            <p class="text-sm font-semibold text-content">{{ row.name }}</p>

            <div class="grid grid-cols-2 gap-2">
              <button
                v-for="r in RESULTS"
                :key="r.value"
                type="button"
                class="flex items-center justify-center gap-2 rounded-xl border px-3 h-12 text-sm font-semibold transition"
                :class="row.overall_result === r.value ? r.onClass : 'border-line bg-surface-raised text-content-muted hover:bg-surface'"
                @click="row.overall_result = r.value"
              >
                <Icon :name="r.icon" :size="18" />
                {{ r.label }}
              </button>
            </div>

            <div class="rounded-xl border border-line bg-surface p-3">
              <div class="flex items-center justify-between gap-3">
                <span class="flex items-center gap-2 text-sm font-medium text-content-muted">
                  <Icon name="fuel" :size="18" :class="fuelTextClass(row.fuel_pct)" />
                  Fuel when returned
                </span>
                <span class="text-2xl font-semibold tabular-nums" :class="fuelTextClass(row.fuel_pct)">
                  {{ row.fuel_pct }}%
                </span>
              </div>
              <div class="mt-2 h-3 w-full overflow-hidden rounded-full bg-neutral-200">
                <div class="h-full rounded-full transition-all" :class="fuelBarClass(row.fuel_pct)" :style="{ width: `${row.fuel_pct}%` }" />
              </div>
              <input v-model.number="row.fuel_pct" type="range" min="0" max="100" step="5" class="fuel-slider" aria-label="Fuel when returned" />
              <div class="flex justify-between text-xs muted">
                <span>Empty</span>
                <span>Half</span>
                <span>Full</span>
              </div>
            </div>

            <div>
              <p class="label">How does it look?</p>
              <div class="flex flex-wrap gap-2">
                <button
                  v-for="c in CONDITIONS"
                  :key="c.value"
                  type="button"
                  class="chip"
                  :class="row.condition === c.value ? 'chip-active' : ''"
                  @click="row.condition = c.value"
                >
                  <Icon :name="c.icon" :size="16" />
                  {{ c.label }}
                </button>
              </div>
            </div>

            <div v-if="row.tracks_usage_hours" class="grid sm:grid-cols-2 gap-3">
              <div>
                <label class="label">Hour-meter reading (preferred)</label>
                <input v-model.number="row.usage_hours_reading" type="number" min="0" step="0.1" class="input" :placeholder="row.usage_hours_out != null ? `Was ${row.usage_hours_out}` : 'Example: 125.5'" />
              </div>
              <div>
                <label class="label">Or hours used this trip</label>
                <input v-model.number="row.usage_hours_estimate" type="number" min="0" step="0.5" class="input" placeholder="Example: 3.5" />
              </div>
            </div>

            <label class="flex items-start gap-2.5 rounded-xl bg-surface p-3 text-sm text-content-muted">
              <input v-model="row.damage_found" type="checkbox" class="mt-0.5 h-4 w-4 rounded border-neutral-300" />
              <span>
                Damage found
                <span class="block text-xs muted">Creates a damage report when you stop the tool from going out.</span>
              </span>
            </label>
            <input
              v-if="row.damage_found || row.take_out_of_service || row.overall_result === 'fail'"
              v-model="row.damage_description"
              type="text"
              class="input"
              placeholder="What is wrong?"
            />

            <label class="flex items-start gap-2.5 rounded-xl bg-warn-100/50 p-3 text-sm text-content-muted">
              <input v-model="row.end_of_life_soon" type="checkbox" class="mt-0.5 h-4 w-4 rounded border-neutral-300" />
              <span>
                Flag for replacement soon
                <span class="block text-xs muted">Moves this tool into the current CapEx year.</span>
              </span>
            </label>

            <div>
              <label class="label">Notes</label>
              <input v-model="row.notes" type="text" class="input" placeholder="Anything the next person should know" />
            </div>

            <label class="flex items-start gap-2.5 rounded-xl border border-danger-100 bg-danger-100/50 p-3 text-sm text-content-muted">
              <input v-model="row.take_out_of_service" type="checkbox" class="mt-0.5 h-4 w-4 rounded border-neutral-300" @change="onTakeOut(row)" />
              <span class="flex items-start gap-2">
                <Icon name="alert" :size="16" class="mt-0.5 text-danger-600 shrink-0" />
                <span>
                  Stop anyone using this tool
                  <span class="block text-xs muted">Use this only when it is not safe or not working.</span>
                </span>
              </span>
            </label>
          </div>

          <p v-if="actionError.review" class="flex items-center gap-2 text-sm text-danger-600">
            <Icon name="alert" :size="16" />
            {{ actionError.review }}
          </p>

          <button class="btn-primary w-full sm:w-auto" :disabled="acting" @click="doReviewReturn">
            <Icon :name="acting ? 'refresh' : 'check'" :size="18" />
            {{ acting ? 'Saving…' : 'Confirm return complete' }}
          </button>
        </div>
      </section>

      <!-- Rare: ask to keep the tools longer -->
      <section v-if="['checked_out', 'return_pending'].includes(loan.status)" class="card">
        <header class="flex items-center gap-2.5 p-4 sm:p-5 pb-3">
          <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-neutral-100 text-content-muted shrink-0">
            <Icon name="clock" :size="18" />
          </span>
          <div>
            <p class="section-title">Need more time?</p>
            <p class="text-xs muted">Ask the depot to move the return date.</p>
          </div>
        </header>

        <ul v-if="loan.extensions?.length" class="divide-rows border-t border-line">
          <li v-for="ext in loan.extensions" :key="ext.id" class="flex flex-wrap items-center gap-3 px-4 py-3">
            <div class="min-w-0 flex-1">
              <p class="text-sm font-medium text-content">Asked to keep until {{ formatDateTime(ext.requested_due_at) }}</p>
              <p v-if="ext.reason" class="text-xs muted">“{{ ext.reason }}”</p>
            </div>
            <StatusBadge :status="ext.status" />
            <template v-if="ext.status === 'pending' && auth.can('checkout_items')">
              <button class="btn-secondary btn-sm" :disabled="acting" @click="decide(ext, true)">
                <Icon name="check" :size="16" />
                Allow extra time
              </button>
              <button class="btn-danger btn-sm" :disabled="acting" @click="decide(ext, false)">
                <Icon name="x" :size="16" />
                Decline
              </button>
            </template>
          </li>
        </ul>

        <div class="space-y-3 border-t border-line p-4 sm:p-5">
          <div class="grid sm:grid-cols-2 gap-3">
            <div>
              <label class="label">New return date</label>
              <input v-model="extensionForm.requested_due_at" type="datetime-local" class="input" />
            </div>
            <div>
              <label class="label">Why?</label>
              <input v-model="extensionForm.reason" type="text" class="input" placeholder="Example: job takes longer" />
            </div>
          </div>

          <p v-if="actionError.extension" class="flex items-center gap-2 text-sm text-danger-600">
            <Icon name="alert" :size="16" />
            {{ actionError.extension }}
          </p>

          <button class="btn-secondary w-full sm:w-auto" :disabled="acting" @click="requestExtension">
            <Icon name="clock" :size="17" />
            Ask for more time
          </button>
        </div>
      </section>
    </template>

    <EmptyState v-else icon="alert" title="Loan not found" hint="It may have been removed.">
      <RouterLink to="/loans" class="btn-secondary btn-sm">
        <Icon name="arrow-left" :size="16" />
        Back to loans
      </RouterLink>
    </EmptyState>
  </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { RouterLink, useRoute } from 'vue-router';
import api from '../../api';
import { useAuthStore } from '../../stores/auth';
import { useToastStore } from '../../stores/toast';
import { nextStepHint } from '../../status';
import { fromLocalInput } from '../../datetime';
import Icon from '../../components/Icon.vue';
import PageHeader from '../../components/PageHeader.vue';
import EmptyState from '../../components/EmptyState.vue';
import StatusBadge from '../../components/StatusBadge.vue';

const route = useRoute();
const auth = useAuthStore();
const toasts = useToastStore();

const loan = ref(null);
const loading = ref(true);
const acting = ref(false);
const actionError = reactive({ checkout: '', return: '', review: '', extension: '' });

const CONDITIONS = [
  { value: 'new', label: 'Like new', icon: 'sparkles' },
  { value: 'good', label: 'Good', icon: 'check-circle' },
  { value: 'fair', label: 'Okay', icon: 'info' },
  { value: 'poor', label: 'Bad', icon: 'alert' },
];

const RESULTS = [
  { value: 'pass', label: 'Looks good', icon: 'check-circle', onClass: 'border-brand-solid bg-brand-solid text-white' },
  { value: 'fail', label: 'Problem found', icon: 'alert', onClass: 'border-danger-solid bg-danger-solid text-white' },
];

const checkoutForm = ref([]);
const returnForm = ref([]);
const reviewForm = ref([]);
const attachedCompanions = ref([]);
const consumableQty = reactive({});
const consumableReviewForm = ref([]);
const extensionForm = reactive({ requested_due_at: '', reason: '' });
const maintenanceOverride = ref(false);
const maintenanceOverrideReason = ref('');

const loanItems = computed(() => loan.value?.items || []);
const companionSuggestions = computed(() => loan.value?.companion_suggestions || []);
const companionHints = computed(() => {
  const hints = [];
  for (const group of companionSuggestions.value) {
    const attached = attachedCompanions.value.some((c) => c.companion_of_item_id === group.item_id);
    if (!attached && group.required_skipped_hints?.length) {
      hints.push(...group.required_skipped_hints);
    }
  }
  return [...new Set(hints)];
});

const checkoutBlocking = computed(() => {
  const map = loan.value?.maintenance_by_item || {};
  return Object.values(map).flatMap((m) => m.blocking || []);
});

const checkoutWarnings = computed(() => {
  const map = loan.value?.maintenance_by_item || {};
  return Object.values(map).flatMap((m) => m.warnings || []);
});

const overdue = computed(
  () =>
    loan.value?.due_at &&
    new Date(loan.value.due_at) < new Date() &&
    ['checked_out', 'return_pending'].includes(loan.value.status),
);

const badgeStatus = computed(() => (overdue.value ? 'overdue' : loan.value?.status));

const hint = computed(() => (loan.value ? nextStepHint(badgeStatus.value, auth.can('checkout_items')) : ''));

const canSelfReturn = computed(
  () => loan.value && loan.value.status === 'checked_out' && (loan.value.borrower_id === auth.user?.id || auth.can('checkout_items')),
);

const whoAndWhere = computed(() => {
  const l = loan.value;
  if (!l) return '';

  const parts = [];
  if (l.borrower?.name) parts.push(l.borrower.name);
  if (l.depot?.name) parts.push(l.depot.name);
  if (l.property?.name) parts.push(`for ${l.property.name}`);

  return parts.join(' · ');
});

const dueLabel = computed(() => {
  const l = loan.value;
  if (!l?.due_at) return 'No date set';
  if (overdue.value) return `Late — was due ${formatDateTime(l.due_at)}`;

  return `Return by ${formatDateTime(l.due_at)}`;
});

function conditionLabel(value) {
  return CONDITIONS.find((c) => c.value === value)?.label.toLowerCase() || value;
}

function fuelTextClass(pct) {
  if (pct <= 20) return 'text-danger-600';
  if (pct <= 50) return 'text-warn-600';

  return 'text-brand-700';
}

function fuelBarClass(pct) {
  if (pct <= 20) return 'bg-danger-solid';
  if (pct <= 50) return 'bg-warn-solid';

  return 'bg-brand-solid';
}

function itemLabel(li) {
  return li.item?.label || li.item?.name || li.item?.asset_tag || `Item #${li.item_id}`;
}

function tracksHours(li) {
  return Boolean(li.item?.tool_type?.tracks_usage_hours);
}

function onTakeOut(row) {
  if (row.take_out_of_service) row.damage_found = true;
}

function buildForms() {
  const items = loanItems.value.filter((li) => !li.companion_of_loan_item_id);
  checkoutForm.value = items.map((li) => ({
    item_id: li.item_id,
    name: itemLabel(li),
    qr_token: '',
    condition_out: li.item?.condition || 'good',
    fuel_pct_out: li.item?.fuel_pct ?? 100,
  }));
  returnForm.value = loanItems.value.map((li) => ({
    item_id: li.item_id,
    name: itemLabel(li),
    condition: '',
    fuel_pct: li.fuel_pct_out ?? 50,
    tracks_usage_hours: tracksHours(li),
    usage_hours_out: li.usage_hours_out ?? li.item?.usage_hours ?? null,
    usage_hours_reading: null,
    usage_hours_estimate: null,
    damage_found: false,
    damage_description: '',
    end_of_life_soon: false,
  }));
  reviewForm.value = loanItems.value.map((li) => ({
    item_id: li.item_id,
    name: itemLabel(li),
    overall_result: 'pass',
    condition: li.condition_in || li.item?.condition || 'good',
    fuel_pct: li.fuel_pct_in ?? li.inspection?.fuel_pct ?? 50,
    tracks_usage_hours: tracksHours(li),
    usage_hours_out: li.usage_hours_out ?? li.item?.usage_hours ?? null,
    usage_hours_reading: li.inspection?.usage_hours_reading ?? null,
    usage_hours_estimate: li.inspection?.usage_hours_estimate ?? null,
    damage_found: Boolean(li.inspection?.damage_found),
    damage_description: li.inspection?.damage_description || '',
    end_of_life_soon: Boolean(li.inspection?.end_of_life_soon),
    take_out_of_service: false,
    notes: '',
  }));

  attachedCompanions.value = [];
  Object.keys(consumableQty).forEach((k) => delete consumableQty[k]);
  for (const group of companionSuggestions.value) {
    for (const sku of group.consumables || []) {
      consumableQty[consumableKey(group.item_id, sku.id)] = 0;
    }
  }

  consumableReviewForm.value = (loan.value?.consumable_issues || [])
    .filter((i) => i.status === 'estimated')
    .map((i) => ({
      id: i.id,
      label: i.item?.label || i.item?.name || `SKU #${i.item_id}`,
      qty_estimated: Number(i.qty_estimated),
      qty_used: Number(i.qty_estimated),
    }));
}

function consumableKey(primaryItemId, skuId) {
  return `${primaryItemId}:${skuId}`;
}

function companionsFor(primaryItemId) {
  return attachedCompanions.value.filter((c) => c.companion_of_item_id === primaryItemId);
}

function isCompanionAttached(primaryItemId, companionId) {
  return attachedCompanions.value.some(
    (c) => c.companion_of_item_id === primaryItemId && c.item_id === companionId,
  );
}

function toggleCompanion(primaryItemId, companion) {
  if (isCompanionAttached(primaryItemId, companion.id)) {
    attachedCompanions.value = attachedCompanions.value.filter(
      (c) => !(c.companion_of_item_id === primaryItemId && c.item_id === companion.id),
    );
    return;
  }
  attachedCompanions.value = [
    ...attachedCompanions.value,
    {
      item_id: companion.id,
      companion_of_item_id: primaryItemId,
      label: companion.label,
      qr_token: '',
    },
  ];
}

function formatDateTime(value) {
  return value ? new Date(value).toLocaleString(undefined, { dateStyle: 'medium', timeStyle: 'short' }) : '—';
}

/**
 * Action endpoints return the loan with fewer eager-loaded relations than the
 * detail endpoint, so merge instead of replacing to keep extensions/depot/etc.
 */
function applyLoan(fresh) {
  loan.value = { ...(loan.value || {}), ...fresh };
  buildForms();
}

async function load() {
  loading.value = true;
  try {
    const { data } = await api.get(`/loans/${route.params.id}`);
    loan.value = data.data;
    buildForms();
  } catch {
    loan.value = null;
  } finally {
    loading.value = false;
  }
}

async function doCheckout() {
  if (checkoutBlocking.value.length && !maintenanceOverride.value) {
    actionError.checkout = 'Tick override and give a reason, or service the tool first.';
    return;
  }
  if (checkoutBlocking.value.length && maintenanceOverride.value && !maintenanceOverrideReason.value.trim()) {
    actionError.checkout = 'Say why you are overriding before confirming pick-up.';
    return;
  }

  acting.value = true;
  actionError.checkout = '';
  try {
    const { data } = await api.post(`/loans/${loan.value.id}/checkout`, {
      items: checkoutForm.value.map((r) => ({
        item_id: r.item_id,
        qr_token: r.qr_token || undefined,
        condition_out: r.condition_out || undefined,
        fuel_pct_out: r.fuel_pct_out,
      })),
      companions: attachedCompanions.value.map((c) => ({
        item_id: c.item_id,
        companion_of_item_id: c.companion_of_item_id,
        qr_token: c.qr_token || undefined,
      })),
      consumables: Object.entries(consumableQty)
        .filter(([, qty]) => Number(qty) > 0)
        .map(([key, qty]) => {
          const [companion_of_item_id, item_id] = key.split(':').map(Number);
          return { item_id, companion_of_item_id, qty_estimated: Number(qty) };
        }),
      maintenance_override: maintenanceOverride.value || undefined,
      maintenance_override_reason: maintenanceOverride.value ? maintenanceOverrideReason.value : undefined,
    });
    maintenanceOverride.value = false;
    maintenanceOverrideReason.value = '';
    await load();
    if (data.warnings?.length) {
      toasts.success(`Pick-up confirmed. Note: ${data.warnings[0]}`);
    } else {
      toasts.success('Pick-up confirmed. These tools are now with the borrower.');
    }
  } catch (e) {
    const errors = e.response?.data?.errors;
    actionError.checkout =
      errors?.maintenance?.[0] ||
      errors?.qr?.[0] ||
      e.response?.data?.message ||
      'That did not work. Please try again.';
  } finally {
    acting.value = false;
  }
}

async function doSelfReturn() {
  acting.value = true;
  actionError.return = '';
  try {
    const { data } = await api.post(`/loans/${loan.value.id}/self-return`, {
      items: returnForm.value.map((r) => ({
        item_id: r.item_id,
        condition: r.condition || undefined,
        fuel_pct: r.fuel_pct,
        usage_hours_reading: r.usage_hours_reading ?? undefined,
        usage_hours_estimate: r.usage_hours_reading == null ? (r.usage_hours_estimate ?? undefined) : undefined,
        damage_found: r.damage_found,
        damage_description: r.damage_found ? r.damage_description : undefined,
        end_of_life_soon: r.end_of_life_soon || undefined,
      })),
    });
    applyLoan(data.data);
    toasts.success('Return submitted. The depot will inspect the tools.');
  } catch (e) {
    actionError.return = e.response?.data?.message || 'That did not work. Please try again.';
  } finally {
    acting.value = false;
  }
}

async function doReviewReturn() {
  acting.value = true;
  actionError.review = '';
  try {
    const { data } = await api.post(`/loans/${loan.value.id}/review-return`, {
      items: reviewForm.value.map((r) => ({
        item_id: r.item_id,
        overall_result: r.overall_result,
        condition: r.condition || undefined,
        fuel_pct: r.fuel_pct,
        usage_hours_reading: r.usage_hours_reading ?? undefined,
        usage_hours_estimate: r.usage_hours_reading == null ? (r.usage_hours_estimate ?? undefined) : undefined,
        damage_found: r.damage_found || r.take_out_of_service || r.overall_result === 'fail',
        damage_description: r.damage_description || undefined,
        end_of_life_soon: r.end_of_life_soon || undefined,
        take_out_of_service: r.take_out_of_service || undefined,
        notes: r.notes || undefined,
      })),
      consumables: consumableReviewForm.value.map((r) => ({
        id: r.id,
        qty_used: r.qty_used,
      })),
    });
    applyLoan(data.data);
    toasts.success('Return complete. This loan is closed.');
  } catch (e) {
    actionError.review = e.response?.data?.message || 'That did not work. Please try again.';
  } finally {
    acting.value = false;
  }
}

async function requestExtension() {
  actionError.extension = '';
  if (!extensionForm.requested_due_at) {
    actionError.extension = 'Pick a new return date first.';
    return;
  }

  acting.value = true;
  try {
    await api.post(`/loans/${loan.value.id}/request-extension`, {
      requested_due_at: fromLocalInput(extensionForm.requested_due_at),
      reason: extensionForm.reason || undefined,
    });
    await load();
    extensionForm.requested_due_at = '';
    extensionForm.reason = '';
    toasts.success('Asked for more time. The depot will answer.');
  } catch (e) {
    actionError.extension = e.response?.data?.message || 'Could not ask for more time.';
  } finally {
    acting.value = false;
  }
}

async function decide(ext, approve) {
  acting.value = true;
  try {
    await api.post(`/loan-extensions/${ext.id}/decide`, { approve, note: '' });
    await load();
    toasts.success(approve ? 'Extra time allowed.' : 'Extra time declined.');
  } catch {
    toasts.error('Could not save that answer.');
  } finally {
    acting.value = false;
  }
}

onMounted(load);
</script>

<style scoped>
/* Big one-thumb fuel slider — the only piece the shared classes do not cover. */
.fuel-slider {
  -webkit-appearance: none;
  appearance: none;
  width: 100%;
  height: 2.75rem;
  background: transparent;
  cursor: pointer;
}
.fuel-slider::-webkit-slider-runnable-track {
  height: 0.5rem;
  border-radius: 999px;
  background: var(--color-line);
}
.fuel-slider::-webkit-slider-thumb {
  -webkit-appearance: none;
  height: 2rem;
  width: 2rem;
  margin-top: -0.75rem;
  border-radius: 999px;
  background: #fff;
  border: 2px solid var(--color-brand-700);
  box-shadow: 0 1px 3px rgba(9, 9, 11, 0.25);
}
.fuel-slider::-moz-range-track {
  height: 0.5rem;
  border-radius: 999px;
  background: var(--color-line);
}
.fuel-slider::-moz-range-thumb {
  height: 2rem;
  width: 2rem;
  border-radius: 999px;
  background: #fff;
  border: 2px solid var(--color-brand-700);
}
.fuel-slider:focus-visible {
  outline: 2px solid var(--color-brand-500);
  outline-offset: 2px;
}
</style>
