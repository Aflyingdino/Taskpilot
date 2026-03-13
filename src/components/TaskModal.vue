<script setup>
import { ref, watch, computed } from 'vue'
import { ui, closeTaskModal } from '@/stores/uiStore'
import { createTask, updateTask, projectLabels, findTask } from '@/stores/boardStore'
import ColorPicker from './ColorPicker.vue'
import { STATUS_OPTIONS } from '@/utils/constants'

const isEdit = computed(() => !!ui.editTaskId)

const form = ref({ text: '', description: '', status: 'not_started', deadline: '', labelIds: [], mainColor: null, color: null })

watch(
  () => ui.taskModalOpen,
  (open) => {
    if (!open) return
    if (ui.editTaskId) {
      const task = findTask(ui.editTaskId)
      if (task) {
        form.value = {
          text: task.text,
          description: task.description || '',
          status: task.status || 'not_started',
          deadline: task.deadline || '',
          labelIds: [...(task.labelIds || [])],
          mainColor: task.mainColor || null,
          color: task.color || null,
        }
      }
    } else {
      form.value = { text: '', description: '', status: 'not_started', deadline: '', labelIds: [], mainColor: null, color: null }
    }
  },
)

function toggleLabel(labelId) {
  const idx = form.value.labelIds.indexOf(labelId)
  if (idx === -1) form.value.labelIds.push(labelId)
  else form.value.labelIds.splice(idx, 1)
}

function submit() {
  if (!form.value.text.trim()) return
  const data = {
    text: form.value.text.trim(),
    description: form.value.description,
    status: form.value.status,
    deadline: form.value.deadline || null,
    labelIds: [...form.value.labelIds],
    mainColor: form.value.mainColor || null,
    color: form.value.color || null,
  }
  if (isEdit.value) {
    updateTask(ui.editTaskId, data)
  } else {
    createTask(data)
  }
  closeTaskModal()
}
</script>

<template>
  <Teleport to="body">
    <Transition name="modal-fade">
      <div v-if="ui.taskModalOpen" class="modal-overlay" @click.self="closeTaskModal">
        <div class="modal" role="dialog" aria-modal="true" :aria-labelledby="'task-modal-title'">
          <div class="modal-header">
            <span class="modal-title" id="task-modal-title">{{ isEdit ? 'Edit task' : 'New task' }}</span>
            <button class="modal-close" @click="closeTaskModal">
              <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>
          </div>

          <form class="modal-body" @submit.prevent="submit">
            <!-- Task name -->
            <div class="field">
              <label class="field-label">Task name <span class="req">*</span></label>
              <input
                v-model="form.text"
                type="text"
                class="field-input"
                placeholder="What needs to be done?"
                autofocus
              />
            </div>

            <!-- Description -->
            <div class="field">
              <label class="field-label">Description</label>
              <textarea
                v-model="form.description"
                class="field-input field-textarea"
                placeholder="Add more detail..."
                rows="4"
              />
            </div>

            <!-- Status + Deadline row -->
            <div class="field-row">
              <div class="field">
                <label class="field-label">Status</label>
                <select v-model="form.status" class="field-input field-select">
                  <option v-for="opt in STATUS_OPTIONS" :key="opt.value" :value="opt.value">
                    {{ opt.label }}
                  </option>
                </select>
              </div>
              <div class="field">
                <label class="field-label">Deadline</label>
                <input v-model="form.deadline" type="date" class="field-input" />
              </div>
            </div>

            <!-- Labels -->
            <div v-if="projectLabels.length > 0" class="field">
              <label class="field-label">Labels</label>
              <div class="label-picker">
                <button
                  v-for="label in projectLabels"
                  :key="label.id"
                  type="button"
                  class="label-option"
                  :class="{ 'label-option--active': form.labelIds.includes(label.id) }"
                  :style="{ '--lc': label.color }"
                  @click="toggleLabel(label.id)"
                >
                  <span class="label-dot"></span>
                  {{ label.name }}
                </button>
              </div>
            </div>
            <div v-else class="field">
              <label class="field-label">Labels</label>
              <p class="field-hint">No labels yet. Create them in Project Settings.</p>
            </div>

            <!-- Card colors -->
            <div class="field">
              <label class="field-label">Main color <span class="field-hint-inline">(background tint)</span></label>
              <div class="cal-color-row">
                <ColorPicker v-model="form.mainColor" small />
                <button v-if="form.mainColor" type="button" class="btn-clear-color" @click="form.mainColor = null">Clear</button>
              </div>
            </div>
            <div class="field">
              <label class="field-label">Accent color <span class="field-hint-inline">(border)</span></label>
              <div class="cal-color-row">
                <ColorPicker v-model="form.color" small />
                <button v-if="form.color" type="button" class="btn-clear-color" @click="form.color = null">Clear</button>
              </div>
            </div>

            <!-- Media (placeholder — backend TODO) -->
            <div class="field">
              <label class="field-label">Attachments</label>
              <div class="media-placeholder">
                <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M18.375 12.739l-7.693 7.693a4.5 4.5 0 01-6.364-6.364l10.94-10.94A3 3 0 1119.5 7.372L8.552 18.32m.009-.01l-.01.01m5.699-9.941l-7.81 7.81a1.5 1.5 0 002.112 2.13" />
                </svg>
                <span>File upload — backend required (see TODO)</span>
              </div>
            </div>

            <!-- Actions -->
            <div class="modal-actions">
              <button type="button" class="btn-ghost" @click="closeTaskModal">Cancel</button>
              <button
                type="submit"
                class="btn-primary"
                :disabled="!form.text.trim()"
              >
                {{ isEdit ? 'Save changes' : 'Create task' }}
              </button>
            </div>
          </form>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<style scoped>
.modal-overlay {
  position: fixed;
  inset: 0;
  z-index: 200;
  background: rgba(0, 0, 0, 0.7);
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 24px;
}
.modal {
  width: 100%;
  max-width: 520px;
  background: var(--color-surface-2);
  border: 1px solid var(--color-border);
  border-radius: 12px;
  display: flex;
  flex-direction: column;
  max-height: calc(100vh - 48px);
  overflow: hidden;
}
.modal-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 16px 20px;
  border-bottom: 1px solid var(--color-border-sub);
  flex-shrink: 0;
}
.modal-title {
  font-size: 14px;
  font-weight: 600;
  color: var(--color-text-1);
}
.modal-close {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 28px;
  height: 28px;
  border: none;
  border-radius: 6px;
  background: transparent;
  color: var(--color-text-3);
  cursor: pointer;
  transition: background 0.15s, color 0.15s;
}
.modal-close:hover {
  background: var(--color-surface-3);
  color: var(--color-text-1);
}
.modal-body {
  padding: 20px;
  overflow-y: auto;
  display: flex;
  flex-direction: column;
  gap: 16px;
}
.field {
  display: flex;
  flex-direction: column;
  gap: 6px;
}
.field-row {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 12px;
}
.field-label {
  font-size: 12px;
  font-weight: 500;
  color: var(--color-text-2);
}
.req {
  color: var(--color-danger);
}
.field-input {
  height: 36px;
  padding: 0 10px;
  background: var(--color-surface-0);
  border: 1px solid var(--color-border);
  border-radius: 6px;
  font-size: 13px;
  color: var(--color-text-1);
  outline: none;
  font-family: inherit;
  transition: border-color 0.15s;
  width: 100%;
}
.field-input::placeholder {
  color: var(--color-text-3);
}
.field-input:focus {
  border-color: var(--color-accent);
}
.field-textarea {
  height: auto;
  padding: 8px 10px;
  resize: vertical;
  min-height: 88px;
}
.field-select {
  cursor: pointer;
  appearance: none;
  background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%2352525f' stroke-width='2'%3E%3Cpath d='M6 9l6 6 6-6'/%3E%3C/svg%3E");
  background-repeat: no-repeat;
  background-position: right 10px center;
  padding-right: 30px;
}
.field-hint {
  font-size: 12px;
  color: var(--color-text-3);
  font-style: italic;
}
.label-picker {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
}
.label-option {
  display: flex;
  align-items: center;
  gap: 6px;
  padding: 4px 10px;
  border-radius: 99px;
  border: 1px solid var(--color-border);
  background: transparent;
  font-size: 12px;
  color: var(--color-text-2);
  cursor: pointer;
  transition: border-color 0.15s, background 0.15s;
}
.label-option:hover {
  border-color: var(--lc, var(--color-accent));
}
.label-option--active {
  border-color: var(--lc, var(--color-accent));
  background: color-mix(in srgb, var(--lc, var(--color-accent)) 15%, transparent);
  color: var(--color-text-1);
}
.label-dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  background: var(--lc, var(--color-accent));
  flex-shrink: 0;
}
.media-placeholder {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 12px;
  border: 1px dashed var(--color-border);
  border-radius: 6px;
  color: var(--color-text-3);
  font-size: 12px;
  font-style: italic;
}
.field-hint-inline {
  font-size: 11px;
  font-weight: 400;
  color: var(--color-text-3);
  margin-left: 4px;
}
.cal-color-row {
  display: flex;
  align-items: center;
  gap: 10px;
  flex-wrap: wrap;
}
.btn-clear-color {
  font-size: 11px;
  color: var(--color-text-3);
  background: none;
  border: 1px solid var(--color-border);
  border-radius: 5px;
  padding: 3px 8px;
  cursor: pointer;
  transition: color 0.15s, border-color 0.15s;
}
.btn-clear-color:hover {
  color: var(--color-text-1);
  border-color: var(--color-text-3);
}
.modal-actions {
  display: flex;
  justify-content: flex-end;
  gap: 8px;
  padding-top: 4px;
}
.btn-primary {
  padding: 0 16px;
  height: 34px;
  border-radius: 6px;
  border: none;
  background: var(--color-accent);
  color: #fff;
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
  transition: background 0.15s, opacity 0.15s;
}
.btn-primary:hover {
  background: var(--color-accent-hover);
}
.btn-primary:disabled {
  opacity: 0.35;
  cursor: not-allowed;
}
.btn-ghost {
  padding: 0 16px;
  height: 34px;
  border-radius: 6px;
  border: 1px solid var(--color-border);
  background: transparent;
  color: var(--color-text-2);
  font-size: 13px;
  cursor: pointer;
  transition: background 0.15s, color 0.15s;
}
.btn-ghost:hover {
  background: var(--color-surface-3);
  color: var(--color-text-1);
}

/* Transition */
.modal-fade-enter-active,
.modal-fade-leave-active {
  transition: opacity 0.2s ease;
}
.modal-fade-enter-active .modal,
.modal-fade-leave-active .modal {
  transition: transform 0.2s ease, opacity 0.2s ease;
}
.modal-fade-enter-from,
.modal-fade-leave-to {
  opacity: 0;
}
.modal-fade-enter-from .modal,
.modal-fade-leave-to .modal {
  transform: translateY(-12px);
  opacity: 0;
}
</style>
