<template>
  <div class="space-y-5">
    <PageHeader
      title="Settings"
      subtitle="Set up how the app works. Changes here affect everybody."
      icon="settings"
    />

    <div class="flex flex-wrap gap-2">
      <button
        v-for="t in tabs"
        :key="t.value"
        type="button"
        class="chip"
        :class="tab === t.value ? 'chip-active' : ''"
        @click="tab = t.value"
      >
        <Icon :name="t.icon" :size="15" />
        {{ t.label }}
      </button>
    </div>

    <section class="card overflow-hidden">
      <header class="flex items-start gap-3 border-b border-line p-4 sm:p-5">
        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-neutral-100 text-content-muted">
          <Icon :name="panel.icon" :size="20" />
        </span>
        <div class="min-w-0">
          <p class="section-title">{{ panel.title }}</p>
          <p class="mt-0.5 text-sm muted">{{ panel.hint }}</p>
        </div>
      </header>

      <div v-if="panelLoading" class="space-y-3 p-4 sm:p-5">
        <div v-for="i in 4" :key="i" class="skeleton h-11" />
      </div>

      <!-- Settings groups: branding, email, text messages, sign-on, features -->
      <form v-else-if="settingsTab" class="space-y-4 p-4 sm:p-5" @submit.prevent="saveSettings">
        <div v-for="f in settingsTab.fields" :key="f.key">
          <label
            v-if="f.type === 'checkbox'"
            class="flex items-start gap-2.5 rounded-xl bg-surface p-3 text-sm text-content-muted"
          >
            <input
              v-model="settingsForm[f.key]"
              type="checkbox"
              class="mt-0.5 h-4 w-4 rounded border-neutral-300"
            />
            <span>{{ f.label }}</span>
          </label>

          <template v-else>
            <label class="label" :for="`setting-${f.key}`">{{ f.label }}</label>
            <select
              v-if="f.type === 'select'"
              :id="`setting-${f.key}`"
              v-model="settingsForm[f.key]"
              class="select"
            >
              <option v-for="o in f.options" :key="o" :value="o">{{ o }}</option>
            </select>
            <textarea
              v-else-if="f.type === 'textarea'"
              :id="`setting-${f.key}`"
              v-model="settingsForm[f.key]"
              rows="4"
              class="textarea font-mono text-xs"
            />
            <div v-else-if="f.type === 'image_upload'" class="space-y-3">
              <div
                v-if="brandingImagePreview(f.key)"
                class="flex items-center gap-3 rounded-xl border border-line bg-surface p-3"
              >
                <img
                  :src="brandingImagePreview(f.key)"
                  :alt="f.label"
                  class="h-14 max-w-[160px] object-contain"
                />
                <div class="min-w-0 flex-1">
                  <p class="truncate text-xs font-mono text-content-muted">{{ settingsForm[f.key] }}</p>
                  <p class="text-xs muted">{{ f.hint || '' }}</p>
                </div>
              </div>
              <div class="flex flex-wrap items-center gap-2">
                <input
                  :id="`branding-upload-${f.key}`"
                  type="file"
                  :accept="f.accept || 'image/png,image/jpeg,image/webp,.png,.jpg,.jpeg,.webp'"
                  class="sr-only"
                  @change="onBrandingImageChange($event, f)"
                />
                <label
                  :for="`branding-upload-${f.key}`"
                  class="btn-secondary cursor-pointer"
                  :class="uploadingBrandingKey === f.key ? 'pointer-events-none opacity-60' : ''"
                >
                  <Icon :name="uploadingBrandingKey === f.key ? 'refresh' : 'upload'" :size="17" />
                  {{
                    uploadingBrandingKey === f.key
                      ? 'Uploading…'
                      : (settingsForm[f.key] ? `Replace ${f.shortLabel || f.label.toLowerCase()}` : `Upload ${f.shortLabel || f.label.toLowerCase()}`)
                  }}
                </label>
              </div>
            </div>
            <input
              v-else
              :id="`setting-${f.key}`"
              v-model="settingsForm[f.key]"
              :type="f.type"
              :placeholder="f.placeholder || undefined"
              class="input"
            />
          </template>
        </div>

        <button type="submit" class="btn-primary" :disabled="savingSettings">
          <Icon :name="savingSettings ? 'refresh' : 'check'" :size="17" />
          {{ savingSettings ? 'Saving…' : 'Save changes' }}
        </button>
      </form>

      <!-- People -->
      <div v-else-if="tab === 'users'" class="space-y-5 p-4 sm:p-5">
        <form class="space-y-3 rounded-xl bg-surface p-3.5" @submit.prevent="createUser">
          <p class="label mb-0">Add someone</p>
          <div class="grid gap-3 sm:grid-cols-2">
            <div>
              <label class="label">Full name</label>
              <input v-model="userForm.name" type="text" required class="input" placeholder="Jordan Smith" />
            </div>
            <div>
              <label class="label">Email</label>
              <input v-model="userForm.email" type="email" required class="input" placeholder="jordan@company.com" />
            </div>
            <div>
              <label class="label">First password</label>
              <input v-model="userForm.password" type="password" required class="input" placeholder="They can change it later" />
            </div>
            <div>
              <label class="label">Jobs (roles)</label>
              <input v-model="userForm.roles" type="text" class="input" placeholder="borrower, depot_admin" />
            </div>
          </div>
          <button class="btn-primary btn-sm" type="submit" :disabled="savingUser">
            <Icon :name="savingUser ? 'refresh' : 'plus'" :size="16" />
            {{ savingUser ? 'Adding…' : 'Add person' }}
          </button>
        </form>

        <ul v-if="users.length" class="card divide-rows overflow-hidden">
          <li v-for="u in users" :key="u.id" class="flex flex-wrap items-center gap-3 px-4 py-3">
            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-neutral-100 text-content-muted">
              <Icon name="user" :size="18" />
            </span>
            <div class="min-w-0 flex-1 basis-48">
              <p class="truncate text-sm font-medium text-content">{{ u.name }}</p>
              <p class="truncate text-xs muted">{{ u.email }}</p>
              <p class="mt-0.5 flex items-center gap-1 text-xs muted">
                <Icon name="shield" :size="13" />
                {{ (u.roles || []).map((r) => r.name).join(', ') || 'No job set' }}
              </p>
            </div>
            <StatusBadge
              :status="u.is_active ? 'available' : 'unavailable'"
              :label="u.is_active ? 'Can sign in' : 'Blocked'"
            />
          </li>
        </ul>
        <p v-else class="text-sm muted">Nobody here yet.</p>
      </div>

      <!-- Roles and permissions -->
      <div v-else-if="tab === 'roles'" class="space-y-5 p-4 sm:p-5">
        <form class="flex flex-col gap-2 rounded-xl bg-surface p-3.5 sm:flex-row sm:items-end" @submit.prevent="createRole">
          <div class="min-w-0 flex-1">
            <label class="label">New job name</label>
            <input v-model="roleForm.name" type="text" required class="input" placeholder="depot_admin" />
          </div>
          <button class="btn-primary btn-sm" type="submit" :disabled="savingRole">
            <Icon :name="savingRole ? 'refresh' : 'plus'" :size="16" />
            {{ savingRole ? 'Adding…' : 'Add job' }}
          </button>
        </form>

        <div v-for="r in roles" :key="r.id" class="card-pad space-y-3">
          <div class="flex items-center gap-2">
            <Icon name="shield" :size="17" class="text-content-muted" />
            <p class="section-title">{{ r.name }}</p>
          </div>
          <div class="flex flex-wrap gap-2">
            <label
              v-for="p in allPermissions"
              :key="p"
              class="chip cursor-pointer"
              :class="hasPermission(r, p) ? 'chip-active' : ''"
            >
              <input
                type="checkbox"
                class="sr-only"
                :checked="hasPermission(r, p)"
                @change="togglePermission(r, p, $event.target.checked)"
              />
              <Icon :name="hasPermission(r, p) ? 'check' : 'plus'" :size="14" />
              {{ p }}
            </label>
          </div>
        </div>
      </div>

      <!-- Sites -->
      <div v-else-if="tab === 'properties'" class="space-y-5 p-4 sm:p-5">
        <form class="space-y-3 rounded-xl bg-surface p-3.5" @submit.prevent="createProperty">
          <p class="label mb-0">Add a site</p>
          <div class="grid gap-3 sm:grid-cols-3">
            <div>
              <label class="label">Name</label>
              <input v-model="propertyForm.name" type="text" required class="input" placeholder="Riverside Flats" />
            </div>
            <div>
              <label class="label">Short code</label>
              <input v-model="propertyForm.code" type="text" required class="input" placeholder="RIV" />
            </div>
            <div>
              <label class="label">Town or city</label>
              <input v-model="propertyForm.city" type="text" class="input" placeholder="Leeds" />
            </div>
          </div>
          <button class="btn-primary btn-sm" type="submit" :disabled="savingProperty">
            <Icon :name="savingProperty ? 'refresh' : 'plus'" :size="16" />
            {{ savingProperty ? 'Adding…' : 'Add site' }}
          </button>
        </form>

        <ul v-if="properties.length" class="card divide-rows overflow-hidden">
          <li v-for="p in properties" :key="p.id" class="flex items-center justify-between gap-3 px-4 py-3">
            <div class="flex min-w-0 items-center gap-2.5">
              <Icon name="building" :size="17" class="shrink-0 text-content-muted" />
              <div class="min-w-0">
                <p class="truncate text-sm font-medium text-content">{{ p.name }}</p>
                <p class="font-mono text-xs muted">{{ p.code }}</p>
              </div>
            </div>
            <StatusBadge
              :status="p.is_active ? 'available' : 'unavailable'"
              :label="p.is_active ? 'In use' : 'Closed'"
            />
          </li>
        </ul>
        <p v-else class="text-sm muted">No sites yet.</p>
      </div>

      <!-- Equipment statuses -->
      <div v-else-if="tab === 'statuses'" class="space-y-5 p-4 sm:p-5">
        <form class="space-y-3 rounded-xl bg-surface p-3.5" @submit.prevent="createStatus">
          <p class="label mb-0">Add a status</p>
          <div class="grid gap-3 sm:grid-cols-3">
            <div>
              <label class="label">Name</label>
              <input v-model="statusForm.name" type="text" required class="input" placeholder="Being repaired" />
            </div>
            <div>
              <label class="label">Can it go out?</label>
              <select v-model="statusForm.availability_effect" class="select">
                <option value="available">Yes, free to borrow</option>
                <option value="unavailable">No, not available</option>
                <option value="in_use">No, it is out already</option>
              </select>
            </div>
            <div>
              <label class="label">Colour</label>
              <input v-model="statusForm.color" type="text" class="input" placeholder="#b45309" />
            </div>
          </div>
          <button class="btn-primary btn-sm" type="submit" :disabled="savingStatus">
            <Icon :name="savingStatus ? 'refresh' : 'plus'" :size="16" />
            {{ savingStatus ? 'Adding…' : 'Add status' }}
          </button>
        </form>

        <ul v-if="customStatuses.length" class="flex flex-wrap gap-2">
          <li v-for="s in customStatuses" :key="s.id">
            <StatusBadge :status="s.slug" :label="s.name" :color="s.color" />
          </li>
        </ul>
        <p v-else class="text-sm muted">No statuses yet.</p>
      </div>

      <!-- Extra fields -->
      <div v-else-if="tab === 'custom_fields'" class="space-y-5 p-4 sm:p-5">
        <form class="space-y-3 rounded-xl bg-surface p-3.5" @submit.prevent="createField">
          <p class="label mb-0">Add a field</p>
          <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <div>
              <label class="label">Goes on</label>
              <input v-model="fieldForm.entity_type" type="text" required class="input" placeholder="item" />
            </div>
            <div>
              <label class="label">Key name</label>
              <input v-model="fieldForm.key" type="text" required class="input font-mono text-xs" placeholder="tyre_size" />
            </div>
            <div>
              <label class="label">Label people see</label>
              <input v-model="fieldForm.label" type="text" required class="input" placeholder="Tyre size" />
            </div>
            <div>
              <label class="label">Kind of box</label>
              <select v-model="fieldForm.field_type" class="select">
                <option v-for="ft in FIELD_TYPES" :key="ft.value" :value="ft.value">{{ ft.label }}</option>
              </select>
            </div>
          </div>
          <button class="btn-primary btn-sm" type="submit" :disabled="savingField">
            <Icon :name="savingField ? 'refresh' : 'plus'" :size="16" />
            {{ savingField ? 'Adding…' : 'Add field' }}
          </button>
        </form>

        <ul v-if="customFields.length" class="card divide-rows overflow-hidden">
          <li v-for="f in customFields" :key="f.id" class="flex items-center justify-between gap-3 px-4 py-3">
            <div class="min-w-0">
              <p class="truncate text-sm font-medium text-content">{{ f.label }}</p>
              <p class="font-mono text-xs muted">{{ f.entity_type }}.{{ f.key }}</p>
            </div>
            <span class="shrink-0 rounded-full border border-line bg-surface px-2.5 py-1 text-xs font-medium text-content-muted">
              {{ fieldTypeLabel(f.field_type) }}
            </span>
          </li>
        </ul>
        <p v-else class="text-sm muted">No extra fields yet.</p>
      </div>

      <!-- Alerts -->
      <div v-else-if="tab === 'notifications'" class="space-y-4 p-4 sm:p-5">
        <ul v-if="notificationTypes.length" class="card divide-rows overflow-hidden">
          <li v-for="type in notificationTypes" :key="type.id" class="space-y-2.5 px-4 py-3">
            <div class="min-w-0">
              <p class="text-sm font-medium text-content">{{ type.name }}</p>
              <p class="text-xs muted">{{ type.group }}</p>
            </div>
            <div class="flex flex-wrap gap-2">
              <label
                v-for="ch in CHANNELS"
                :key="ch.value"
                class="chip cursor-pointer"
                :class="matrixState[`${type.id}:${ch.value}`] ? 'chip-active' : ''"
              >
                <input
                  v-model="matrixState[`${type.id}:${ch.value}`]"
                  type="checkbox"
                  class="sr-only"
                />
                <Icon :name="ch.icon" :size="14" />
                {{ ch.label }}
              </label>
            </div>
          </li>
        </ul>
        <p v-else class="text-sm muted">No message types set up yet.</p>

        <button class="btn-primary" :disabled="savingMatrix" @click="saveMatrix">
          <Icon :name="savingMatrix ? 'refresh' : 'check'" :size="17" />
          {{ savingMatrix ? 'Saving…' : 'Save choices' }}
        </button>
      </div>

      <!-- Label layout builder -->
      <div v-else-if="tab === 'labels'" class="space-y-5 p-4 sm:p-5">
        <div class="flex flex-wrap gap-2">
          <button
            v-for="size in labelSizes"
            :key="size.key"
            type="button"
            class="chip"
            :class="labelSizeKey === size.key ? 'chip-active' : ''"
            @click="labelSizeKey = size.key"
          >
            {{ size.label }}
          </button>
        </div>

        <div class="grid gap-5 lg:grid-cols-2">
          <div class="space-y-4">
            <p class="text-sm muted">
              {{ activeLabelSize?.hint || 'Pick a size, then choose what prints on it.' }}
              Changes preview live — Save when you like it.
            </p>

            <div class="space-y-2">
              <p class="text-xs font-medium uppercase tracking-wide text-content-muted">Fields</p>
              <label
                v-for="field in activeToggleFields"
                :key="field.key"
                class="flex items-start gap-2.5 rounded-xl bg-surface p-3 text-sm text-content-muted"
              >
                <input
                  v-model="labelLayout[labelSizeKey][field.key]"
                  type="checkbox"
                  class="mt-0.5 h-4 w-4 rounded border-neutral-300"
                  @change="onLabelDraftChange"
                />
                <span>
                  <span class="font-medium text-content">{{ field.label }}</span>
                  <span v-if="field.hint" class="mt-0.5 block text-xs muted">{{ field.hint }}</span>
                </span>
              </label>
            </div>

            <div class="space-y-3 rounded-xl border border-line p-3">
              <p class="text-xs font-medium uppercase tracking-wide text-content-muted">Placement &amp; style</p>

              <div>
                <label class="label" for="label-qr-side">QR position</label>
                <select
                  id="label-qr-side"
                  v-model="labelLayout[labelSizeKey].qr_side"
                  class="select"
                  @change="onLabelDraftChange"
                >
                  <option value="left">Left</option>
                  <option value="right">Right</option>
                </select>
              </div>

              <div>
                <label class="label" for="label-font">Font</label>
                <select
                  id="label-font"
                  v-model="labelLayout[labelSizeKey].font"
                  class="select"
                  @change="onLabelDraftChange"
                >
                  <option value="bold">Bold (best for thermal)</option>
                  <option value="regular">Regular</option>
                </select>
              </div>

              <div>
                <label class="label" for="label-id-size">6-digit ID size</label>
                <select
                  id="label-id-size"
                  v-model="labelLayout[labelSizeKey].id_size"
                  class="select"
                  @change="onLabelDraftChange"
                >
                  <option value="large">Large</option>
                  <option value="medium">Medium</option>
                  <option value="small">Small</option>
                </select>
              </div>

              <div>
                <label class="label" for="label-name-size">Item name size</label>
                <select
                  id="label-name-size"
                  v-model="labelLayout[labelSizeKey].name_size"
                  class="select"
                  @change="onLabelDraftChange"
                >
                  <option value="large">Large</option>
                  <option value="medium">Medium</option>
                  <option value="small">Small</option>
                </select>
              </div>

              <label class="flex items-start gap-2.5 rounded-xl bg-surface p-3 text-sm text-content-muted">
                <input
                  v-model="labelLayout[labelSizeKey].logo"
                  type="checkbox"
                  class="mt-0.5 h-4 w-4 rounded border-neutral-300"
                  @change="onLogoToggle"
                />
                <span>
                  <span class="font-medium text-content">Print logo</span>
                  <span class="mt-0.5 block text-xs muted">
                    Uses Branding → Logo path
                    <span v-if="logoPathPreview"> ({{ logoPathPreview }})</span>
                    <span v-else> — set a logo path under Branding first</span>
                  </span>
                </span>
              </label>

              <div>
                <p class="label mb-2">Field order (top → bottom)</p>
                <ul class="space-y-1.5">
                  <li
                    v-for="(key, idx) in orderedStackKeys"
                    :key="key"
                    class="flex items-center justify-between gap-2 rounded-lg bg-surface px-3 py-2 text-sm"
                  >
                    <span class="font-medium text-content">{{ stackFieldLabel(key) }}</span>
                    <span class="flex gap-1">
                      <button
                        type="button"
                        class="btn-secondary btn-sm"
                        :disabled="idx === 0"
                        @click="moveStack(idx, -1)"
                      >
                        Up
                      </button>
                      <button
                        type="button"
                        class="btn-secondary btn-sm"
                        :disabled="idx === orderedStackKeys.length - 1"
                        @click="moveStack(idx, 1)"
                      >
                        Down
                      </button>
                    </span>
                  </li>
                </ul>
                <p v-if="!orderedStackKeys.length" class="text-xs muted">Turn on at least one text field to reorder.</p>
              </div>
            </div>

            <p class="text-xs muted">
              Ownership text is set under
              <button type="button" class="font-medium text-brand underline" @click="tab = 'branding'">
                Branding → Label ownership line
              </button>
              <span v-if="ownershipPreview"> — currently “{{ ownershipPreview }}”</span>
              <span v-else> — currently empty (line hidden even if toggled on).</span>
            </p>

            <div class="flex flex-wrap gap-2">
              <button type="button" class="btn-primary" :disabled="savingLabels" @click="saveLabelLayout">
                <Icon :name="savingLabels ? 'refresh' : 'check'" :size="17" />
                {{ savingLabels ? 'Saving…' : 'Save layout' }}
              </button>
              <button type="button" class="btn-secondary" :disabled="loadingLabelPreview" @click="refreshLabelPreview(true)">
                <Icon :name="loadingLabelPreview ? 'refresh' : 'eye'" :size="17" />
                {{ loadingLabelPreview ? 'Loading…' : 'Preview' }}
              </button>
            </div>
          </div>

          <div class="flex flex-col items-center justify-center rounded-xl border border-dashed border-line bg-surface p-4">
            <p class="mb-3 text-xs font-medium uppercase tracking-wide text-content-muted">
              Live preview
              <span v-if="labelPreviewDirty" class="ml-1 font-normal normal-case text-amber-700">(unsaved draft)</span>
            </p>
            <img
              v-if="labelPreviewUrl"
              :src="labelPreviewUrl"
              alt="Label preview"
              class="max-w-full bg-surface-raised shadow-sm"
              :style="labelPreviewStyle"
            />
            <p v-else class="text-sm muted">Adjust fields to see a sample label.</p>
            <p v-if="labelPreviewItem" class="mt-2 text-xs muted">
              Sample: {{ labelPreviewItem.label || labelPreviewItem.asset_tag }}
            </p>
          </div>
        </div>
      </div>

      <!-- Backups -->
      <div v-else-if="tab === 'backups'" class="space-y-4 p-4 sm:p-5">
        <button class="btn-primary" :disabled="backingUp" @click="exportBackup">
          <Icon :name="backingUp ? 'refresh' : 'download'" :size="17" />
          {{ backingUp ? 'Saving…' : 'Make a backup now' }}
        </button>

        <ul v-if="backups.length" class="card divide-rows overflow-hidden">
          <li v-for="b in backups" :key="b.name" class="flex items-center justify-between gap-3 px-4 py-3">
            <div class="min-w-0">
              <p class="truncate font-mono text-xs text-content">{{ b.name }}</p>
              <p class="text-xs muted">{{ (b.size_bytes / 1024).toFixed(0) }} KB</p>
            </div>
            <a :href="`/api/backups/${b.name}/download`" class="btn-secondary btn-sm">
              <Icon name="download" :size="16" />
              Download
            </a>
          </li>
        </ul>
        <p v-else class="text-sm muted">No backups saved yet.</p>
      </div>

      <!-- Updates -->
      <div v-else-if="tab === 'updater'" class="space-y-4 p-4 sm:p-5">
        <div class="flex flex-wrap items-center gap-3">
          <button class="btn-secondary" :disabled="checkingUpdate || applyingUpdate" @click="checkUpdate">
            <Icon name="refresh" :size="17" />
            {{ checkingUpdate ? 'Checking…' : 'Check for updates' }}
          </button>
          <button
            v-if="updateInfo?.update_available"
            class="btn-primary"
            :disabled="applyingUpdate || checkingUpdate"
            @click="applyUpdate"
          >
            <Icon :name="applyingUpdate ? 'refresh' : 'download'" :size="17" />
            {{ applyingUpdate ? 'Updating…' : `Install v${updateInfo.latest}` }}
          </button>
        </div>

        <div v-if="updateInfo" class="space-y-3 rounded-xl border border-line bg-surface p-4 text-sm">
          <dl class="grid gap-2 sm:grid-cols-2">
            <div>
              <dt class="muted text-xs">Installed</dt>
              <dd class="font-medium text-content">v{{ updateInfo.current || '—' }}</dd>
            </div>
            <div>
              <dt class="muted text-xs">Latest on GitHub</dt>
              <dd class="font-medium text-content">
                {{ updateInfo.latest ? `v${updateInfo.latest}` : '—' }}
              </dd>
            </div>
          </dl>

          <p v-if="updateInfo.update_available" class="text-brand-700">
            A newer version is ready. Install keeps your database, .env, and uploaded files.
          </p>
          <p v-else-if="updateInfo.message" class="muted">{{ updateInfo.message }}</p>
          <p v-else class="muted">You are on the latest version.</p>

          <div v-if="updateInfo.release_notes" class="space-y-1">
            <p class="text-xs font-medium uppercase tracking-wide muted">Release notes</p>
            <pre
              class="max-h-48 overflow-auto whitespace-pre-wrap rounded-lg border border-line bg-surface-raised p-3 text-xs text-content-muted"
            >{{ updateInfo.release_notes }}</pre>
          </div>
        </div>

        <p v-if="applyingUpdate" class="text-sm muted">
          The site may briefly go into maintenance mode. Keep this tab open until it finishes.
        </p>
      </div>
    </section>
  </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue';
import api from '../../api';
import { useToastStore } from '../../stores/toast';
import Icon from '../../components/Icon.vue';
import PageHeader from '../../components/PageHeader.vue';
import StatusBadge from '../../components/StatusBadge.vue';

const toasts = useToastStore();

/**
 * One entry per tab: the chip label plus the card heading and the one-line
 * plain explanation shown under it, so a non-technical admin can still tell
 * what a screen does.
 */
const PANELS = {
  branding: {
    label: 'Branding',
    icon: 'sparkles',
    title: 'Branding',
    hint: 'The name, logo, colour, and the ownership line printed on tool labels.',
  },
  smtp: {
    label: 'Email',
    icon: 'mail',
    title: 'Email sending (SMTP)',
    hint: 'The mail server the app uses to send emails. Your email provider gives you these details.',
  },
  twilio: {
    label: 'Text messages',
    icon: 'phone',
    title: 'Text messages (Twilio)',
    hint: 'Lets the app send text message reminders. Needs a Twilio account.',
  },
  saml: {
    label: 'Company sign-in',
    icon: 'key',
    title: 'Single sign-on (SAML)',
    hint: 'Let people sign in with their company account instead of a separate password.',
  },
  features: {
    label: 'Features',
    icon: 'star',
    title: 'Features on or off',
    hint: 'Switch parts of the app on or off for everyone.',
  },
  labels: {
    label: 'Labels',
    icon: 'file',
    title: 'Tool label layout',
    hint: 'Choose which fields print on each label size. Preview updates after you save.',
  },
  users: {
    label: 'People',
    icon: 'users',
    title: 'People',
    hint: 'Add someone new, or check who is allowed to sign in.',
  },
  roles: {
    label: 'Jobs',
    icon: 'shield',
    title: 'Jobs and permissions',
    hint: 'A job is a group of people, like depot staff. Tick what each job is allowed to do.',
  },
  properties: {
    label: 'Sites',
    icon: 'building',
    title: 'Sites and buildings',
    hint: 'The places crews take tools to.',
  },
  statuses: {
    label: 'Statuses',
    icon: 'check-circle',
    title: 'Equipment statuses',
    hint: 'The states a tool can be in, and whether it can still go out.',
  },
  custom_fields: {
    label: 'Extra fields',
    icon: 'file',
    title: 'Extra fields',
    hint: 'Add your own boxes to items, loans and other records.',
  },
  notifications: {
    label: 'Alerts',
    icon: 'bell',
    title: 'Who gets told what',
    hint: 'Choose how each kind of message reaches people.',
  },
  backups: {
    label: 'Backups',
    icon: 'download',
    title: 'Backups',
    hint: 'Save a copy of everything, or download a copy you made earlier.',
  },
  updater: {
    label: 'Updates',
    icon: 'refresh',
    title: 'App updates',
    hint: 'Check GitHub for a newer release and install it on this server.',
  },
};

const SETTINGS_GROUPS = {
  branding: { fields: [
    { key: 'app_name', label: 'App name', type: 'text' },
    {
      key: 'logo_path',
      label: 'Logo',
      type: 'image_upload',
      shortLabel: 'logo',
      hint: 'Used in the app and on labels when “Print logo” is on.',
      accept: 'image/png,image/jpeg,image/webp,.png,.jpg,.jpeg,.webp',
      endpoint: '/settings/branding/logo',
      formField: 'logo',
    },
    {
      key: 'favicon_path',
      label: 'Favicon',
      type: 'image_upload',
      shortLabel: 'favicon',
      hint: 'Small icon shown in the browser tab.',
      accept: 'image/png,image/jpeg,image/webp,image/x-icon,.png,.jpg,.jpeg,.webp,.ico',
      endpoint: '/settings/branding/favicon',
      formField: 'favicon',
    },
    { key: 'primary_color', label: 'Primary colour', type: 'text' },
    { key: 'support_email', label: 'Support email', type: 'email' },
    {
      key: 'label_ownership',
      label: 'Label ownership line',
      type: 'text',
      placeholder: 'e.g. Property of ACME Construction — return to Main Depot',
    },
  ]},
  smtp: { fields: [
    { key: 'host', label: 'Host', type: 'text' },
    { key: 'port', label: 'Port', type: 'number' },
    { key: 'username', label: 'Username', type: 'text' },
    { key: 'password', label: 'Password', type: 'password' },
    { key: 'encryption', label: 'Encryption', type: 'select', options: ['tls', 'ssl', 'none'] },
    { key: 'from_address', label: 'Send from address', type: 'email' },
    { key: 'from_name', label: 'Send from name', type: 'text' },
  ]},
  twilio: { fields: [
    { key: 'sms_enabled', label: 'Send text messages', type: 'checkbox' },
    { key: 'account_sid', label: 'Account SID', type: 'text' },
    { key: 'auth_token', label: 'Auth token', type: 'password' },
    { key: 'from_number', label: 'Send from number', type: 'text' },
  ]},
  saml: { fields: [
    { key: 'enabled', label: 'Turn company sign-in on', type: 'checkbox' },
    { key: 'entity_id', label: 'Entity ID', type: 'text' },
    { key: 'sso_url', label: 'Sign-in URL', type: 'text' },
    { key: 'slo_url', label: 'Sign-out URL', type: 'text' },
    { key: 'x509_cert', label: 'x509 certificate', type: 'textarea' },
    { key: 'auto_provision', label: 'Create accounts automatically on first sign-in', type: 'checkbox' },
    { key: 'default_role', label: 'Job given to new accounts', type: 'text' },
  ]},
  features: { fields: [
    { key: 'waitlist_enabled', label: 'Waiting list when nothing is free', type: 'checkbox' },
    { key: 'self_return_enabled', label: 'Crews can return tools themselves', type: 'checkbox' },
    { key: 'offline_scanning_enabled', label: 'Scanning works without signal', type: 'checkbox' },
    { key: 'capex_forecast_enabled', label: 'Budget plan screen', type: 'checkbox' },
  ]},
};

const CHANNELS = [
  { value: 'in_app', label: 'In the app', icon: 'bell' },
  { value: 'mail', label: 'Email', icon: 'mail' },
  { value: 'sms', label: 'Text message', icon: 'phone' },
];

const FIELD_TYPES = [
  { value: 'text', label: 'Short text' },
  { value: 'textarea', label: 'Long text' },
  { value: 'number', label: 'Number' },
  { value: 'boolean', label: 'Yes or no' },
  { value: 'date', label: 'Date' },
  { value: 'select', label: 'Pick one' },
  { value: 'multiselect', label: 'Pick several' },
];

const tabs = Object.entries(PANELS).map(([value, meta]) => ({ value, label: meta.label, icon: meta.icon }));

const tab = ref('branding');
const panel = computed(() => PANELS[tab.value]);
const panelLoading = ref(false);
const settingsTab = computed(() => SETTINGS_GROUPS[tab.value] || null);
const settingsForm = reactive({});
const savingSettings = ref(false);
const uploadingBrandingKey = ref('');
const brandingPreviewUrls = reactive({});

function logoPublicUrl(path, bust = false) {
  if (!path) return '';
  let url = path;
  if (!String(path).startsWith('http') && !String(path).startsWith('/')) {
    url = `/storage/${String(path).replace(/^\/+/, '')}`;
  }
  if (!bust) return url;
  const sep = url.includes('?') ? '&' : '?';
  return `${url}${sep}v=${Date.now()}`;
}

function brandingImagePreview(key) {
  return brandingPreviewUrls[key] || logoPublicUrl(settingsForm[key]);
}

async function onBrandingImageChange(event, field) {
  const file = event.target.files?.[0];
  event.target.value = '';
  if (!file || !field?.endpoint || !field?.formField) return;

  // Instant local preview so the thumbnail updates even before the request finishes.
  const localUrl = URL.createObjectURL(file);
  brandingPreviewUrls[field.key] = localUrl;

  uploadingBrandingKey.value = field.key;
  try {
    const formData = new FormData();
    formData.append(field.formField, file);
    const { data } = await api.post(field.endpoint, formData);
    const path = data.data?.[field.key];
    // Same storage path on replace — bust cache so the thumbnail refreshes.
    const url = logoPublicUrl(data.data?.url || path, true);
    if (localUrl.startsWith('blob:')) {
      URL.revokeObjectURL(localUrl);
    }
    settingsForm[field.key] = path;
    brandingPreviewUrls[field.key] = url;
    if (field.key === 'logo_path') {
      logoPathPreview.value = path || '';
    }
    toasts.success(`${field.shortLabel || field.label} uploaded.`);
  } catch (e) {
    if (localUrl.startsWith('blob:')) {
      URL.revokeObjectURL(localUrl);
    }
    delete brandingPreviewUrls[field.key];
    toasts.error(
      e.response?.data?.message
      || `Could not upload that ${field.shortLabel || 'image'}. Use JPG, PNG, or WebP under 5 MB.`,
    );
  } finally {
    uploadingBrandingKey.value = '';
  }
}

const users = ref([]);
const userForm = reactive({ name: '', email: '', password: '', roles: '' });
const savingUser = ref(false);
const roles = ref([]);
const allPermissions = ref([]);
const roleForm = reactive({ name: '' });
const savingRole = ref(false);
const properties = ref([]);
const propertyForm = reactive({ name: '', code: '', city: '' });
const savingProperty = ref(false);
const customStatuses = ref([]);
const statusForm = reactive({ name: '', availability_effect: 'available', color: '' });
const savingStatus = ref(false);
const customFields = ref([]);
const fieldForm = reactive({ entity_type: '', key: '', label: '', field_type: 'text' });
const savingField = ref(false);
const notificationTypes = ref([]);
const matrixState = reactive({});
const savingMatrix = ref(false);
const backups = ref([]);
const backingUp = ref(false);
const updateInfo = ref(null);
const checkingUpdate = ref(false);
const applyingUpdate = ref(false);

const LABEL_FIELD_DEFS = {
  qr: { label: 'QR code', hint: 'Scan target — flip left/right under Placement.' },
  numeric_id: { label: '6-digit ID', hint: 'Large tool number for typing when scan fails.' },
  name: { label: 'Item name', hint: 'Turn off to give the ID more room.' },
  asset_tag: { label: 'Asset tag', hint: 'Short inventory tag (standard / medium only).' },
  barcode: { label: 'Barcode', hint: 'Code 128 of the tool number (standard / medium only).' },
  ownership: { label: 'Ownership line', hint: 'Uses the Branding ownership text when set.' },
  logo: { label: 'Logo', hint: 'From Branding logo path.' },
};

const LABEL_OPTION_KEYS = ['qr_side', 'stack_order', 'font', 'id_size', 'name_size', 'logo'];

const labelSizes = ref([]);
const labelLayout = reactive({});
const labelSizeKey = ref('niimbot_15x30');
const savingLabels = ref(false);
const loadingLabelPreview = ref(false);
const labelPreviewUrl = ref('');
const labelPreviewItem = ref(null);
const ownershipPreview = ref('');
const logoPathPreview = ref('');
const labelPreviewDirty = ref(false);
let labelPreviewTimer = null;
let labelPreviewSeq = 0;

const activeLabelSize = computed(() => labelSizes.value.find((s) => s.key === labelSizeKey.value) || null);

const activeToggleFields = computed(() => {
  const row = labelLayout[labelSizeKey.value] || {};
  // Only real printable fields — never show qr_side / stack_order / etc. as checkboxes.
  return Object.keys(LABEL_FIELD_DEFS)
    .filter((key) => key !== 'logo' && !LABEL_OPTION_KEYS.includes(key) && Object.prototype.hasOwnProperty.call(row, key))
    .map((key) => ({ key, ...LABEL_FIELD_DEFS[key] }));
});

const orderedStackKeys = computed(() => {
  const row = labelLayout[labelSizeKey.value];
  if (!row) return [];
  const order = Array.isArray(row.stack_order) ? row.stack_order : [];
  return order.filter((key) => {
    if (key === 'logo') return !!row.logo;
    if (key === 'qr') return false;
    return !!row[key];
  });
});

const labelPreviewStyle = computed(() => {
  const size = activeLabelSize.value;
  if (!size) return {};
  if (size.layout === 'compact') {
    return { width: `${(size.width_px || 192) * 4}px`, height: 'auto', imageRendering: 'pixelated' };
  }
  return { width: '100%', maxWidth: '420px', height: 'auto' };
});

function stackFieldLabel(key) {
  return LABEL_FIELD_DEFS[key]?.label || key;
}

function ensureSizeRow(key) {
  if (!labelLayout[key] || typeof labelLayout[key] !== 'object') {
    labelLayout[key] = {};
  }
  const row = labelLayout[key];
  if (!row.qr_side) row.qr_side = 'left';
  if (!row.font) row.font = 'bold';
  if (!row.id_size) row.id_size = 'large';
  if (!row.name_size) row.name_size = 'medium';
  if (typeof row.logo !== 'boolean') row.logo = false;
  if (!Array.isArray(row.stack_order)) {
    row.stack_order = Object.keys(row).filter((k) => !LABEL_OPTION_KEYS.includes(k) && k !== 'qr');
  }
}

function onLabelDraftChange() {
  labelPreviewDirty.value = true;
  scheduleLabelPreview();
}

function onLogoToggle() {
  const row = labelLayout[labelSizeKey.value];
  if (!row) return;
  const order = Array.isArray(row.stack_order) ? [...row.stack_order] : [];
  if (row.logo && !order.includes('logo')) {
    order.unshift('logo');
  }
  if (!row.logo) {
    row.stack_order = order.filter((k) => k !== 'logo');
  } else {
    row.stack_order = order;
  }
  onLabelDraftChange();
}

function moveStack(idx, delta) {
  const row = labelLayout[labelSizeKey.value];
  if (!row || !Array.isArray(row.stack_order)) return;
  const visible = orderedStackKeys.value;
  const fromKey = visible[idx];
  const toKey = visible[idx + delta];
  if (!fromKey || !toKey) return;
  const order = [...row.stack_order];
  const a = order.indexOf(fromKey);
  const b = order.indexOf(toKey);
  if (a < 0 || b < 0) return;
  order[a] = toKey;
  order[b] = fromKey;
  row.stack_order = order;
  onLabelDraftChange();
}

function scheduleLabelPreview() {
  if (labelPreviewTimer) clearTimeout(labelPreviewTimer);
  labelPreviewTimer = setTimeout(() => {
    refreshLabelPreview(false);
  }, 350);
}

function fieldTypeLabel(value) {
  return FIELD_TYPES.find((ft) => ft.value === value)?.label || value;
}

async function loadLabelBuilder() {
  const [sizesRes, layoutRes, brandingRes, itemsRes] = await Promise.all([
    api.get('/qr/sizes'),
    api.get('/settings/labels'),
    api.get('/settings/branding'),
    api.get('/items', { params: { per_page: 1 } }),
  ]);

  labelSizes.value = sizesRes.data?.data || [];
  const layout = layoutRes.data?.data?.layout || {};
  Object.keys(labelLayout).forEach((k) => delete labelLayout[k]);
  Object.assign(labelLayout, JSON.parse(JSON.stringify(layout)));
  Object.keys(labelLayout).forEach((k) => ensureSizeRow(k));

  if (!labelSizeKey.value || !labelLayout[labelSizeKey.value]) {
    labelSizeKey.value = labelSizes.value[0]?.key || 'standard';
  }
  ensureSizeRow(labelSizeKey.value);

  ownershipPreview.value = brandingRes.data?.data?.label_ownership || '';
  logoPathPreview.value = brandingRes.data?.data?.logo_path || '';

  const items = itemsRes.data?.data;
  const list = Array.isArray(items) ? items : (items?.data || []);
  labelPreviewItem.value = list[0] || null;
  labelPreviewDirty.value = false;

  if (labelPreviewItem.value) {
    await refreshLabelPreview(false);
  }
}

async function saveLabelLayout() {
  savingLabels.value = true;
  try {
    Object.keys(labelLayout).forEach((k) => ensureSizeRow(k));
    const { data } = await api.put('/settings/labels', { layout: labelLayout });
    const layout = data?.data?.layout || {};
    Object.keys(labelLayout).forEach((k) => delete labelLayout[k]);
    Object.assign(labelLayout, JSON.parse(JSON.stringify(layout)));
    Object.keys(labelLayout).forEach((k) => ensureSizeRow(k));
    labelPreviewDirty.value = false;
    toasts.success('Label layout saved.');
    await refreshLabelPreview(false);
  } catch (e) {
    toasts.error(e.response?.data?.message || Object.values(e.response?.data?.errors || {})[0]?.[0] || 'Could not save label layout.');
  } finally {
    savingLabels.value = false;
  }
}

async function refreshLabelPreview(showErrors = true) {
  if (!labelPreviewItem.value?.id) {
    if (showErrors) toasts.error('No sample item available to preview.');
    return;
  }
  ensureSizeRow(labelSizeKey.value);
  const seq = ++labelPreviewSeq;
  loadingLabelPreview.value = true;
  try {
    const png = await api.post(
      '/qr/preview',
      {
        size: labelSizeKey.value,
        item_id: labelPreviewItem.value.id,
        layout: labelLayout[labelSizeKey.value],
      },
      { responseType: 'blob' },
    );
    if (seq !== labelPreviewSeq) return;
    if (labelPreviewUrl.value?.startsWith('blob:')) {
      URL.revokeObjectURL(labelPreviewUrl.value);
    }
    labelPreviewUrl.value = URL.createObjectURL(new Blob([png.data], { type: 'image/png' }));
  } catch (e) {
    if (showErrors) {
      let message = 'Could not load label preview.';
      const data = e.response?.data;
      if (data instanceof Blob) {
        try {
          const text = await data.text();
          const json = JSON.parse(text);
          message = json.message || Object.values(json.errors || {})[0]?.[0] || message;
        } catch {
          // keep default
        }
      } else if (data?.message) {
        message = data.message;
      }
      toasts.error(message);
    }
  } finally {
    if (seq === labelPreviewSeq) loadingLabelPreview.value = false;
  }
}

async function loadSettings(group) {
  const { data } = await api.get(`/settings/${group}`);
  Object.keys(settingsForm).forEach((k) => delete settingsForm[k]);
  Object.assign(settingsForm, data.data);
  if (group === 'branding') {
    Object.keys(brandingPreviewUrls).forEach((k) => delete brandingPreviewUrls[k]);
    if (settingsForm.logo_path) {
      brandingPreviewUrls.logo_path = logoPublicUrl(settingsForm.logo_path);
    }
    if (settingsForm.favicon_path) {
      brandingPreviewUrls.favicon_path = logoPublicUrl(settingsForm.favicon_path);
    }
    logoPathPreview.value = settingsForm.logo_path || '';
  }
}

async function saveSettings() {
  savingSettings.value = true;
  try {
    const endpointMap = { branding: 'branding', smtp: 'smtp', twilio: 'twilio', saml: 'saml', features: 'features' };
    await api.put(`/settings/${endpointMap[tab.value]}`, settingsForm);
    toasts.success('Saved.');
  } catch (e) {
    toasts.error(e.response?.data?.message || 'Could not save these settings.');
  } finally {
    savingSettings.value = false;
  }
}

async function loadUsers() {
  const { data } = await api.get('/admin/users');
  users.value = data.data.data || data.data;
}
async function createUser() {
  savingUser.value = true;
  try {
    await api.post('/admin/users', {
      ...userForm,
      roles: userForm.roles ? userForm.roles.split(',').map((r) => r.trim()) : [],
    });
    Object.assign(userForm, { name: '', email: '', password: '', roles: '' });
    await loadUsers();
    toasts.success('The new person can now sign in.');
  } catch (e) {
    toasts.error(e.response?.data?.message || 'Could not add this person.');
  } finally {
    savingUser.value = false;
  }
}

async function loadRoles() {
  const { data } = await api.get('/admin/roles');
  roles.value = data.data;
  allPermissions.value = data.permissions;
}
async function createRole() {
  savingRole.value = true;
  try {
    await api.post('/admin/roles', { name: roleForm.name });
    roleForm.name = '';
    await loadRoles();
    toasts.success('Job added.');
  } catch (e) {
    toasts.error(e.response?.data?.message || 'Could not add this job.');
  } finally {
    savingRole.value = false;
  }
}
function hasPermission(role, perm) {
  return (role.permissions || []).some((p) => p.name === perm);
}
async function togglePermission(role, perm, checked) {
  const current = (role.permissions || []).map((p) => p.name);
  const next = checked ? [...new Set([...current, perm])] : current.filter((p) => p !== perm);
  try {
    await api.put(`/admin/roles/${role.id}`, { permissions: next });
    await loadRoles();
  } catch {
    toasts.error('Could not change what this job is allowed to do.');
    await loadRoles();
  }
}

async function loadProperties() {
  const { data } = await api.get('/properties');
  properties.value = data.data.data || data.data;
}
async function createProperty() {
  savingProperty.value = true;
  try {
    await api.post('/properties', propertyForm);
    Object.assign(propertyForm, { name: '', code: '', city: '' });
    await loadProperties();
    toasts.success('Site added.');
  } catch (e) {
    toasts.error(e.response?.data?.message || 'Could not add this site.');
  } finally {
    savingProperty.value = false;
  }
}

async function loadStatuses() {
  const { data } = await api.get('/custom-statuses');
  customStatuses.value = data.data;
}
async function createStatus() {
  savingStatus.value = true;
  try {
    await api.post('/custom-statuses', statusForm);
    Object.assign(statusForm, { name: '', availability_effect: 'available', color: '' });
    await loadStatuses();
    toasts.success('Status added.');
  } catch (e) {
    toasts.error(e.response?.data?.message || 'Could not add this status.');
  } finally {
    savingStatus.value = false;
  }
}

async function loadFields() {
  const { data } = await api.get('/custom-fields');
  customFields.value = data.data;
}
async function createField() {
  savingField.value = true;
  try {
    await api.post('/custom-fields', fieldForm);
    Object.assign(fieldForm, { entity_type: '', key: '', label: '', field_type: 'text' });
    await loadFields();
    toasts.success('Field added.');
  } catch (e) {
    toasts.error(e.response?.data?.message || 'Could not add this field.');
  } finally {
    savingField.value = false;
  }
}

async function loadMatrix() {
  const { data } = await api.get('/notifications/matrix');
  notificationTypes.value = data.data;
  data.data.forEach((type) => {
    CHANNELS.forEach(({ value: ch }) => {
      const setting = (type.settings || []).find((s) => s.channel === ch);
      matrixState[`${type.id}:${ch}`] = setting ? !!setting.is_enabled : ch === 'in_app';
    });
  });
}
async function saveMatrix() {
  savingMatrix.value = true;
  try {
    const entries = [];
    notificationTypes.value.forEach((type) => {
      CHANNELS.forEach(({ value: ch }) => {
        entries.push({ notification_type_id: type.id, channel: ch, is_enabled: !!matrixState[`${type.id}:${ch}`] });
      });
    });
    await api.put('/notifications/matrix', { entries });
    toasts.success('Saved.');
  } catch (e) {
    toasts.error(e.response?.data?.message || 'Could not save these choices.');
  } finally {
    savingMatrix.value = false;
  }
}

async function loadBackups() {
  const { data } = await api.get('/backups');
  backups.value = data.data;
}
async function exportBackup() {
  backingUp.value = true;
  try {
    await api.post('/backups/export');
    await loadBackups();
    toasts.success('Backup made.');
  } catch (e) {
    toasts.error(e.response?.data?.message || 'Could not make a backup.');
  } finally {
    backingUp.value = false;
  }
}

async function checkUpdate() {
  checkingUpdate.value = true;
  try {
    const { data } = await api.get('/updater/check');
    updateInfo.value = data.data;
  } catch (e) {
    toasts.error(e.response?.data?.message || 'Could not check for updates.');
  } finally {
    checkingUpdate.value = false;
  }
}

async function applyUpdate() {
  if (!updateInfo.value?.update_available || !updateInfo.value?.download_url) {
    toasts.error('No update package is available to install.');
    return;
  }
  if (
    !window.confirm(
      `Install Maintenance Depot v${updateInfo.value.latest} now? The site will pause briefly while files and database updates are applied.`,
    )
  ) {
    return;
  }

  applyingUpdate.value = true;
  try {
    const { data } = await api.post('/updater/apply', {
      download_url: updateInfo.value.download_url,
    });
    if (!data.data?.ok) {
      toasts.error(data.data?.message || 'Update did not finish.');
      return;
    }
    toasts.success(`Updated to v${data.data.version}. Reloading…`);
    updateInfo.value = {
      ...updateInfo.value,
      current: data.data.version,
      update_available: false,
      latest: data.data.version,
    };
    window.setTimeout(() => window.location.reload(), 1200);
  } catch (e) {
    toasts.error(e.response?.data?.message || e.response?.data?.data?.message || 'Could not install the update.');
  } finally {
    applyingUpdate.value = false;
  }
}

const LOADERS = {
  users: loadUsers,
  roles: loadRoles,
  properties: loadProperties,
  statuses: loadStatuses,
  custom_fields: loadFields,
  notifications: loadMatrix,
  backups: loadBackups,
  labels: loadLabelBuilder,
};

async function openTab(value) {
  const loader = SETTINGS_GROUPS[value] ? () => loadSettings(value) : LOADERS[value];
  if (!loader) return;

  panelLoading.value = true;
  try {
    await loader();
  } catch {
    toasts.error('Could not load this section.');
  } finally {
    panelLoading.value = false;
  }
}

watch(tab, openTab);

watch(labelSizeKey, () => {
  if (tab.value === 'labels') {
    ensureSizeRow(labelSizeKey.value);
    scheduleLabelPreview();
  }
});

onMounted(() => openTab('branding'));
</script>
