<script setup>
import { ref, computed } from 'vue'
import {
  notifications, unreadCount,
  markRead, markAllRead,
  archiveNotification, archiveMany,
  deleteNotification, deleteMany,
  clearAll,
} from '@/stores/notificationStore'
import { openTaskDetail } from '@/stores/uiStore'
import { setActiveProject } from '@/stores/projectStore'

const emit = defineEmits(['close'])

const selected = ref(new Set())
const shiftAnchor = ref(null)
const notifDetail = ref(null) // notification being viewed in detail modal

const ICONS = {
  task_start_soon:  { emoji: '⏰', color: '#f76b15' },
  task_start_hour:  { emoji: '🕐', color: '#f5c842' },
  deadline_soon:    { emoji: '📅', color: '#e5484d' },
  deadline_expired: { emoji: '🚨', color: '#e5484d' },
  default:          { emoji: '🔔', color: '#5b5bd6' },
}

function iconFor(type) { return ICONS[type] || ICONS.default }

function formatRelative(iso) {
  const diff = Date.now() - new Date(iso).getTime()
  const m = Math.floor(diff / 60000)
  if (m < 1) return 'just now'
  if (m < 60) return `${m}m ago`
  const h = Math.floor(m / 60)
  if (h < 24) return `${h}h ago`
  return `${Math.floor(h / 24)}d ago`
}

function formatFull(iso) {
  if (!iso) return '—'
  const d = new Date(iso)
  return d.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' }) +
    ' at ' + d.toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit' })
}

function handleClick(event, n) {
  if (event.shiftKey && shiftAnchor.value !== null) {
    // Range-select between shiftAnchor and this item
    const ids = notifications.value.map(n => n.id)
    const a = ids.indexOf(shiftAnchor.value)
    const b = ids.indexOf(n.id)
    const [lo, hi] = a < b ? [a, b] : [b, a]
    for (let i = lo; i <= hi; i++) {
      selected.value.add(ids[i])
    }
  } else if (event.ctrlKey || event.metaKey) {
    if (selected.value.has(n.id)) selected.value.delete(n.id)
    else selected.value.add(n.id)
    shiftAnchor.value = n.id
  } else {
    // Normal click: open detail modal for this notification
    markRead(n.id)
    notifDetail.value = n
    selected.value.clear()
    shiftAnchor.value = n.id
  }
}

function goToTask(n) {
  if (n.taskId) {
    if (n.projectId) setActiveProject(n.projectId)
    openTaskDetail(n.taskId)
    notifDetail.value = null
    emit('close')
  }
}

function toggleSelect(id) {
  if (selected.value.has(id)) selected.value.delete(id)
  else selected.value.add(id)
  shiftAnchor.value = id
}

function deleteSelected() {
  deleteMany([...selected.value])
  selected.value.clear()
}
function archiveSelected() {
  archiveMany([...selected.value])
  selected.value.clear()
}
</script>

<template>
  <Teleport to="body">
    <div class="notif-backdrop" @click.self="emit('close')"></div>
    <div class="notif-panel">
      <!-- Header -->
      <div class="notif-header">
        <div class="notif-header-left">
          <span class="notif-title">Notifications</span>
          <span v-if="unreadCount > 0" class="notif-badge">{{ unreadCount }}</span>
        </div>
        <div class="notif-header-right">
          <button v-if="selected.size > 0" class="notif-action-btn notif-action-btn--danger" @click="deleteSelected" title="Delete selected">
            <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21l-1.022.165M4.772 5.79l1.022.165m0 0l1.08 14.023A2.25 2.25 0 009.118 22h5.764a2.25 2.25 0 002.244-2.077l1.08-14.023"/></svg>
            Delete ({{ selected.size }})
          </button>
          <button v-if="selected.size > 0" class="notif-action-btn" @click="archiveSelected" title="Archive selected">
            <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M20.25 7.5l-.625 10.632a2.25 2.25 0 01-2.247 2.118H6.622a2.25 2.25 0 01-2.247-2.118L3.75 7.5m8.25 3v6.75"/></svg>
            Archive ({{ selected.size }})
          </button>
          <button v-if="unreadCount > 0" class="notif-action-btn" @click="markAllRead" title="Mark all as read">
            <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/></svg>
            Mark all read
          </button>
          <button class="notif-close" @click="emit('close')" aria-label="Close notifications">
            <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
          </button>
        </div>
      </div>

      <!-- Multi-select hint -->
      <div v-if="notifications.length" class="notif-hint">
        <svg width="10" height="10" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" d="M13 16h-1v-4h-1m1-4h.01"/><circle cx="12" cy="12" r="10"/></svg>
        Shift+click to range-select · Ctrl+click to multi-select
      </div>

      <!-- List -->
      <div class="notif-list" v-if="notifications.length > 0">
        <Transition v-for="n in notifications" :key="n.id" name="notif-item">
          <div
            class="notif-item"
            :class="{
              'notif-item--unread': !n.read,
              'notif-item--selected': selected.has(n.id)
            }"
            @click="handleClick($event, n)"
          >
            <div class="notif-item-check" @click.stop="toggleSelect(n.id)">
              <div class="notif-checkbox" :class="{ 'notif-checkbox--checked': selected.has(n.id) }">
                <svg v-if="selected.has(n.id)" width="9" height="9" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
              </div>
            </div>
            <div class="notif-icon" :style="{ color: iconFor(n.type).color }">
              {{ iconFor(n.type).emoji }}
            </div>
            <div class="notif-body">
              <div class="notif-item-title" :class="{ 'notif-item-title--read': n.read }">{{ n.title }}</div>
              <div class="notif-item-body">{{ n.body }}</div>
              <div class="notif-item-time">{{ formatRelative(n.createdAt) }}</div>
            </div>
            <div class="notif-item-actions" @click.stop>
              <button class="notif-item-btn" @click="markRead(n.id); archiveNotification(n.id)" title="Archive">
                <svg width="11" height="11" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M20.25 7.5l-.625 10.632a2.25 2.25 0 01-2.247 2.118H6.622a2.25 2.25 0 01-2.247-2.118L3.75 7.5"/></svg>
              </button>
              <button class="notif-item-btn notif-item-btn--danger" @click="deleteNotification(n.id)" title="Delete">
                <svg width="11" height="11" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
              </button>
            </div>
          </div>
        </Transition>
      </div>

      <div v-else class="notif-empty">
        <svg width="32" height="32" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
        <p>No notifications</p>
      </div>
    </div>
  </Teleport>

  <!-- Notification detail modal -->
  <Teleport to="body">
    <Transition name="notif-detail">
      <div v-if="notifDetail" class="notif-detail-backdrop" @click.self="notifDetail = null">
        <div class="notif-detail-modal" role="dialog" aria-modal="true">
          <div class="notif-detail-hdr">
            <div class="notif-detail-icon" :style="{ color: iconFor(notifDetail.type).color }">
              {{ iconFor(notifDetail.type).emoji }}
            </div>
            <div class="notif-detail-title-col">
              <span class="notif-detail-title">{{ notifDetail.title }}</span>
              <span class="notif-detail-time">{{ formatFull(notifDetail.createdAt) }}</span>
            </div>
            <button class="notif-detail-close" @click="notifDetail = null" aria-label="Close">
              <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
          </div>
          <div class="notif-detail-body">
            <p class="notif-detail-msg">{{ notifDetail.body }}</p>
            <div class="notif-detail-meta">
              <span v-if="notifDetail.projectId" class="notif-detail-meta-row">
                <svg width="11" height="11" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>
                Project ID: {{ notifDetail.projectId }}
              </span>
              <span v-if="notifDetail.groupId" class="notif-detail-meta-row">
                <svg width="11" height="11" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2"/></svg>
                Group: {{ notifDetail.groupId }}
              </span>
            </div>
          </div>
          <div class="notif-detail-footer">
            <button class="notif-detail-dismiss" @click="notifDetail = null">Dismiss</button>
            <button
              v-if="notifDetail.taskId"
              class="notif-detail-goto"
              @click="goToTask(notifDetail)"
            >
              <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 003 8.25v10.5A2.25 2.25 0 005.25 21h10.5A2.25 2.25 0 0018 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25"/></svg>
              Go to task
            </button>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<style scoped>
.notif-backdrop {
  position: fixed;
  inset: 0;
  z-index: 490;
  background: transparent;
}
.notif-panel {
  position: fixed;
  top: 56px;
  right: 12px;
  z-index: 495;
  width: 380px;
  max-height: 560px;
  background: var(--color-surface-2);
  border: 1px solid var(--color-border);
  border-radius: 12px;
  box-shadow: 0 16px 60px rgba(0,0,0,0.5);
  display: flex;
  flex-direction: column;
  overflow: hidden;
  animation: notif-slide-in 0.2s ease;
}
@keyframes notif-slide-in {
  from { opacity: 0; transform: translateY(-8px) scale(0.98); }
  to   { opacity: 1; transform: translateY(0) scale(1); }
}

.notif-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 14px 14px 10px;
  border-bottom: 1px solid var(--color-border);
  flex-shrink: 0;
}
.notif-header-left { display: flex; align-items: center; gap: 8px; }
.notif-header-right { display: flex; align-items: center; gap: 6px; }
.notif-title { font-size: 14px; font-weight: 700; color: var(--color-text-1); }
.notif-badge {
  font-size: 10px; font-weight: 700; padding: 1px 6px;
  border-radius: 99px; background: var(--color-accent);
  color: #fff;
}
.notif-action-btn {
  display: flex; align-items: center; gap: 4px;
  padding: 4px 8px; border-radius: 5px;
  border: 1px solid var(--color-border);
  background: transparent; color: var(--color-text-2);
  font-size: 11px; font-weight: 500; cursor: pointer;
  transition: background 0.1s, color 0.1s, border-color 0.1s;
}
.notif-action-btn:hover { background: var(--color-surface-3); color: var(--color-text-1); }
.notif-action-btn--danger:hover { background: var(--color-danger-bg); color: var(--color-danger); border-color: var(--color-danger); }
.notif-close {
  display: flex; align-items: center; justify-content: center;
  width: 26px; height: 26px; border-radius: 5px;
  border: none; background: transparent; color: var(--color-text-3); cursor: pointer;
  transition: background 0.1s, color 0.1s;
}
.notif-close:hover { background: var(--color-surface-3); color: var(--color-text-1); }

.notif-hint {
  display: flex; align-items: center; gap: 5px;
  padding: 6px 14px;
  font-size: 10px; color: var(--color-text-3);
  border-bottom: 1px solid var(--color-border-sub);
  flex-shrink: 0;
}

.notif-list {
  flex: 1;
  overflow-y: auto;
  padding: 4px 0;
}
.notif-item {
  display: flex;
  align-items: flex-start;
  gap: 10px;
  padding: 10px 14px;
  cursor: pointer;
  transition: background 0.12s;
  position: relative;
  border-bottom: 1px solid var(--color-border-sub);
}
.notif-item:last-child { border-bottom: none; }
.notif-item:hover { background: var(--color-surface-3); }
.notif-item--unread { background: color-mix(in srgb, var(--color-accent) 6%, var(--color-surface-2)); }
.notif-item--selected { background: color-mix(in srgb, var(--color-accent) 14%, var(--color-surface-2)); }

.notif-item-check { flex-shrink: 0; padding-top: 2px; }
.notif-checkbox {
  width: 14px; height: 14px; border-radius: 3px;
  border: 1.5px solid var(--color-border); background: var(--color-surface-0);
  display: flex; align-items: center; justify-content: center;
  transition: background 0.1s, border-color 0.1s;
}
.notif-checkbox--checked {
  background: var(--color-accent); border-color: var(--color-accent); color: #fff;
}

.notif-icon { font-size: 16px; flex-shrink: 0; padding-top: 1px; }

.notif-body { flex: 1; min-width: 0; }
.notif-item-title {
  font-size: 12px; font-weight: 600; color: var(--color-text-1);
  margin-bottom: 2px; line-height: 1.3;
}
.notif-item-title--read { font-weight: 400; color: var(--color-text-2); }
.notif-item-body { font-size: 12px; color: var(--color-text-2); margin-bottom: 3px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.notif-item-time { font-size: 10px; color: var(--color-text-3); }

.notif-item-actions {
  display: flex; align-items: center; gap: 3px;
  opacity: 0; flex-shrink: 0;
  transition: opacity 0.1s;
}
.notif-item:hover .notif-item-actions { opacity: 1; }
.notif-item-btn {
  display: flex; align-items: center; justify-content: center;
  width: 22px; height: 22px; border-radius: 4px;
  border: none; background: transparent; color: var(--color-text-3); cursor: pointer;
  transition: background 0.1s, color 0.1s;
}
.notif-item-btn:hover { background: var(--color-surface-0); color: var(--color-text-1); }
.notif-item-btn--danger:hover { background: var(--color-danger-bg); color: var(--color-danger); }

.notif-empty {
  display: flex; flex-direction: column; align-items: center; justify-content: center;
  gap: 10px; padding: 48px 24px; color: var(--color-text-3); text-align: center;
}
.notif-empty p { font-size: 13px; font-weight: 500; color: var(--color-text-2); }

/* Notification detail modal */
.notif-detail-backdrop {
  position: fixed;
  inset: 0;
  z-index: 510;
  background: rgba(0,0,0,0.55);
  display: flex;
  align-items: center;
  justify-content: center;
}
.notif-detail-modal {
  width: 100%;
  max-width: 400px;
  background: var(--color-surface-2);
  border: 1px solid var(--color-border);
  border-radius: 14px;
  box-shadow: 0 20px 60px rgba(0,0,0,0.5);
  overflow: hidden;
  display: flex;
  flex-direction: column;
}
.notif-detail-hdr {
  display: flex;
  align-items: flex-start;
  gap: 10px;
  padding: 16px 16px 12px;
  border-bottom: 1px solid var(--color-border-sub);
}
.notif-detail-icon { font-size: 22px; flex-shrink: 0; padding-top: 2px; }
.notif-detail-title-col { flex: 1; display: flex; flex-direction: column; gap: 3px; min-width: 0; }
.notif-detail-title { font-size: 14px; font-weight: 700; color: var(--color-text-1); }
.notif-detail-time { font-size: 11px; color: var(--color-text-3); }
.notif-detail-close {
  display: flex; align-items: center; justify-content: center;
  width: 26px; height: 26px; border: none; border-radius: 5px;
  background: transparent; color: var(--color-text-3); cursor: pointer;
  transition: background 0.1s, color 0.1s; flex-shrink: 0;
}
.notif-detail-close:hover { background: var(--color-surface-3); color: var(--color-text-1); }
.notif-detail-body {
  padding: 14px 16px;
  display: flex;
  flex-direction: column;
  gap: 10px;
}
.notif-detail-msg { font-size: 13px; color: var(--color-text-2); line-height: 1.6; }
.notif-detail-meta { display: flex; flex-direction: column; gap: 5px; }
.notif-detail-meta-row {
  display: flex; align-items: center; gap: 6px;
  font-size: 11px; color: var(--color-text-3);
}
.notif-detail-footer {
  display: flex;
  justify-content: flex-end;
  gap: 8px;
  padding: 12px 16px;
  border-top: 1px solid var(--color-border-sub);
}
.notif-detail-dismiss {
  padding: 6px 14px; border-radius: 6px;
  border: 1px solid var(--color-border); background: transparent;
  color: var(--color-text-2); font-size: 12px; cursor: pointer;
  transition: background 0.1s, color 0.1s;
}
.notif-detail-dismiss:hover { background: var(--color-surface-3); color: var(--color-text-1); }
.notif-detail-goto {
  display: flex; align-items: center; gap: 6px;
  padding: 6px 14px; border-radius: 6px; border: none;
  background: var(--color-accent); color: #fff; font-size: 12px; font-weight: 600; cursor: pointer;
  transition: background 0.15s;
}
.notif-detail-goto:hover { background: var(--color-accent-hover); }

/* detail modal transition */
.notif-detail-enter-active, .notif-detail-leave-active { transition: opacity 0.2s ease; }
.notif-detail-enter-from, .notif-detail-leave-to { opacity: 0; }
.notif-detail-enter-active .notif-detail-modal { transition: transform 0.2s cubic-bezier(0.22,1,0.36,1); }
.notif-detail-enter-from .notif-detail-modal { transform: scale(0.95) translateY(8px); }

/* Transition */
.notif-item-enter-active, .notif-item-leave-active { transition: all 0.18s ease; }
.notif-item-enter-from { opacity: 0; transform: translateX(-6px); }
.notif-item-leave-to   { opacity: 0; transform: translateX(6px); }
</style>
