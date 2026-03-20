<script setup>
import { computed, ref } from 'vue'
import { ui, closeTaskDetail, openEditTask } from '@/stores/uiStore'
import { toggleMuteTask, mutedTaskIds } from '@/stores/notificationStore'
import { findTask, addComment, deleteTask, projectLabels, addNote, updateNote, deleteNote, pinComment, deleteComment, editComment, updateTask } from '@/stores/boardStore'
import { activeProject } from '@/stores/projectStore'
import { user } from '@/stores/authStore'
import ColorPicker from './ColorPicker.vue'
import { STATUS_META } from '@/utils/constants'

const task = computed(() => (ui.detailTaskId ? findTask(ui.detailTaskId) : null))
const isTaskMuted = computed(() => task.value ? mutedTaskIds.value.has(task.value.id) : false)

// Permission: owner/admin can always manage notes; assignees can too
const canManageNotes = computed(() => {
  const role = activeProject.value?.role
  if (role === 'owner' || role === 'admin') return true
  if (task.value?.assigneeIds?.includes(user.id)) return true
  return false
})

/* ── Notes ───────────────────────────────────── */
const notes = computed(() => task.value?.notes ?? [])
const expandedNoteId = ref(null)
const showNoteForm = ref(false)
const editingNoteId = ref(null)  // null = new, number = editing existing

const noteForm = ref({
  title: '',
  content: '',
  contentType: 'text',   // 'text' | 'image' | 'video'
  bgColor: '#5b5bd6',
  textColor: '#ffffff',
})

function resetNoteForm() {
  noteForm.value = { title: '', content: '', contentType: 'text', bgColor: '#5b5bd6', textColor: '#ffffff' }
  showNoteForm.value = false
  editingNoteId.value = null
}

function openAddNote() {
  resetNoteForm()
  showNoteForm.value = true
}

function openEditNote(note) {
  noteForm.value = {
    title: note.title,
    content: note.content,
    contentType: note.contentType || 'text',
    bgColor: note.bgColor || '#5b5bd6',
    textColor: note.textColor || '#ffffff',
  }
  editingNoteId.value = note.id
  showNoteForm.value = true
  expandedNoteId.value = null
}

function submitNote() {
  if (!noteForm.value.title.trim() || !task.value) return
  if (editingNoteId.value !== null) {
    updateNote(task.value.id, editingNoteId.value, { ...noteForm.value, title: noteForm.value.title.trim() })
  } else {
    addNote(task.value.id, { ...noteForm.value, title: noteForm.value.title.trim() })
  }
  resetNoteForm()
}

function removeNote(noteId) {
  if (task.value) deleteNote(task.value.id, noteId)
  if (expandedNoteId.value === noteId) expandedNoteId.value = null
}

function toggleNote(noteId) {
  expandedNoteId.value = expandedNoteId.value === noteId ? null : noteId
}

/* ── Comments ──────────────────────────────── */
const newComment = ref('')

const sortedComments = computed(() => {
  const comments = task.value?.comments ?? []
  return [...comments].sort((a, b) => (b.pinned ? 1 : 0) - (a.pinned ? 1 : 0))
})

function submitComment() {
  if (!newComment.value.trim() || !task.value) return
  addComment(ui.detailTaskId, newComment.value.trim())
  newComment.value = ''
}

/* ── Comment editing ──────────────────────────── */
const editingCommentId = ref(null)
const editCommentText  = ref('')

function startEditComment(c) {
  editingCommentId.value = c.id
  editCommentText.value  = c.text
}

function saveEditComment() {
  if (!editCommentText.value.trim() || !task.value || editingCommentId.value === null) return
  editComment(task.value.id, editingCommentId.value, editCommentText.value.trim())
  editingCommentId.value = null
  editCommentText.value  = ''
}

function cancelEditComment() {
  editingCommentId.value = null
  editCommentText.value  = ''
}

function formatDate(iso) {
  if (!iso) return '—'
  return new Date(iso).toLocaleDateString('en-GB', {
    day: '2-digit', month: 'short', year: 'numeric',
  })
}

function formatDateTime(iso) {
  if (!iso) return '—'
  const d = new Date(iso)
  return (
    d.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' }) +
    ' at ' +
    d.toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit' })
  )
}

function getLabel(id) {
  return projectLabels.value.find((l) => l.id === id)
}

function handleDelete() {
  deleteTask(task.value.id)
  closeTaskDetail()
}

/* ── Attachments ──────────────────────────── */
const fileInput = ref(null)

function triggerUpload() {
  fileInput.value?.click()
}

function handleFileUpload(event) {
  const files = Array.from(event.target.files || [])
  if (!files.length || !task.value) return
  const existing = task.value.attachments ?? []
  const added = files.map(file => ({
    id: Date.now() + Math.random(),
    filename: file.name,
    url: URL.createObjectURL(file),
    mime_type: file.type,
    size_bytes: file.size,
    uploaded_at: new Date().toISOString(),
  }))
  updateTask(task.value.id, { attachments: [...existing, ...added] })
  event.target.value = ''
}

function removeAttachment(attachId) {
  if (!task.value) return
  const updated = (task.value.attachments ?? []).filter(a => a.id !== attachId)
  updateTask(task.value.id, { attachments: updated })
}

function formatFileSize(bytes) {
  if (!bytes) return ''
  if (bytes < 1024) return `${bytes} B`
  if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`
  return `${(bytes / (1024 * 1024)).toFixed(1)} MB`
}

function fileIcon(mimeType) {
  if (!mimeType) return 'file'
  if (mimeType.startsWith('image/')) return 'image'
  if (mimeType.startsWith('video/')) return 'video'
  if (mimeType.includes('pdf')) return 'pdf'
  return 'file'
}
</script>

<template>
  <Teleport to="body">
    <Transition name="detail-slide">
      <div v-if="task" class="detail-overlay" @click.self="closeTaskDetail">
        <div class="detail-panel">

          <!-- Header -->
          <div class="detail-header">
            <span class="detail-panel-title">Task details</span>
            <div class="detail-header-actions">
              <button
                class="hdr-btn hdr-btn--mute"
                :class="{ 'hdr-btn--muted': isTaskMuted }"
                @click="toggleMuteTask(task.id)"
                :title="isTaskMuted ? 'Unmute notifications' : 'Mute notifications'"
              >
                <svg v-if="!isTaskMuted" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                </svg>
                <svg v-else width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                  <line x1="3" y1="3" x2="21" y2="21" stroke-linecap="round"/>
                </svg>
                Mute
              </button>
              <button class="hdr-btn" @click="openEditTask(task.id); closeTaskDetail()">
                <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125" />
                </svg>
                Edit
              </button>
              <button class="hdr-btn hdr-btn--danger" @click="handleDelete">
                <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
                </svg>
                Delete
              </button>
              <button class="close-btn" @click="closeTaskDetail">
                <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                </svg>
              </button>
            </div>
          </div>

          <!-- Scrollable body -->
          <div class="detail-body">

            <!-- Task title -->
            <h2 class="task-title">{{ task.text }}</h2>

            <!-- Meta row -->
            <div class="meta-grid">
              <div class="meta-item">
                <span class="meta-key">Status</span>
                <span
                  class="status-pill"
                  :style="{ '--sc': STATUS_META[task.status]?.color ?? '#8a8a9a' }"
                >
                  {{ STATUS_META[task.status]?.label ?? task.status }}
                </span>
              </div>
              <div class="meta-item">
                <span class="meta-key">Deadline</span>
                <span class="meta-val" :class="{ 'meta-val--overdue': task.deadline && new Date(task.deadline) < new Date() && task.status !== 'done' }">
                  {{ task.deadline ? formatDate(task.deadline) : '—' }}
                </span>
              </div>
              <div class="meta-item">
                <span class="meta-key">Created</span>
                <span class="meta-val">{{ formatDateTime(task.createdAt) }}</span>
              </div>
              <div class="meta-item" v-if="task.labelIds?.length">
                <span class="meta-key">Labels</span>
                <div class="meta-labels">
                  <span
                    v-for="id in task.labelIds"
                    :key="id"
                    class="detail-label"
                    :style="{ '--lc': getLabel(id)?.color ?? '#8a8a9a' }"
                  >
                    <span class="detail-label-dot"></span>
                    {{ getLabel(id)?.name ?? '?' }}
                  </span>
                </div>
              </div>
            </div>

            <!-- Description -->
            <div class="section">
              <span class="section-title">Description</span>
              <div v-if="task.description" class="description-body">{{ task.description }}</div>
              <div v-else class="empty-text">No description provided.</div>
            </div>

            <!-- Notes -->
            <div class="section">
              <div class="section-title-row">
                <span class="section-title">Notes <span class="comment-count">{{ notes.length }}</span></span>
                <button v-if="canManageNotes" class="note-add-btn" @click="openAddNote">
                  <svg width="11" height="11" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" stroke-linecap="round">
                    <path d="M12 5v14M5 12h14"/>
                  </svg>
                  Add note
                </button>
              </div>

              <!-- Note pills -->
              <div v-if="notes.length" class="notes-list">
                <div v-for="note in notes" :key="note.id" class="note-wrapper">
                  <!-- Collapsed pill -->
                  <button
                    class="note-pill"
                    :style="{ background: note.bgColor, color: note.textColor }"
                    @click="toggleNote(note.id)"
                  >
                    <span class="note-pill-icon">
                      <svg v-if="note.contentType === 'image'" width="10" height="10" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5" fill="currentColor" stroke="none"/><path d="M21 15l-5-5L5 21"/></svg>
                      <svg v-else-if="note.contentType === 'video'" width="10" height="10" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><polygon points="23 7 16 12 23 17 23 7"/><rect x="1" y="5" width="15" height="14" rx="2"/></svg>
                      <svg v-else width="10" height="10" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
                    </span>
                    {{ note.title }}
                    <svg class="note-pill-chevron" :class="{ 'note-pill-chevron--open': expandedNoteId === note.id }" width="10" height="10" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                  </button>

                  <!-- Expanded content -->
                  <div v-if="expandedNoteId === note.id" class="note-expanded">
                    <div class="note-content">
                      <template v-if="note.contentType === 'text'">
                        <p class="note-text">{{ note.content }}</p>
                      </template>
                      <template v-else-if="note.contentType === 'image'">
                        <img v-if="note.content" :src="note.content" class="note-media" alt="Note image" />
                        <span v-else class="empty-text">No image URL provided.</span>
                      </template>
                      <template v-else-if="note.contentType === 'video'">
                        <video v-if="note.content" :src="note.content" class="note-media" controls />
                        <span v-else class="empty-text">No video URL provided.</span>
                      </template>
                    </div>
                    <div class="note-meta">
                      <span class="note-author">by {{ note.createdBy }}</span>
                      <span class="note-time">{{ formatDateTime(note.createdAt) }}</span>
                      <div v-if="canManageNotes" class="note-actions">
                        <button class="note-action-btn" @click="openEditNote(note)">Edit</button>
                        <button class="note-action-btn note-action-btn--danger" @click="removeNote(note.id)">Delete</button>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
              <div v-else-if="!showNoteForm" class="empty-text">No notes yet.</div>

              <!-- Note form (inline) -->
              <div v-if="showNoteForm && canManageNotes" class="note-form">
                <p class="note-form-title">{{ editingNoteId !== null ? 'Edit note' : 'New note' }}</p>
                <div class="note-form-row">
                  <input v-model="noteForm.title" placeholder="Note title" class="note-input" autofocus />
                </div>
                <!-- Content type toggle -->
                <div class="note-type-row">
                  <button
                    v-for="t in ['text','image','video']"
                    :key="t"
                    class="note-type-btn"
                    :class="{ 'note-type-btn--active': noteForm.contentType === t }"
                    @click="noteForm.contentType = t"
                    type="button"
                  >{{ t }}</button>
                </div>
                <textarea
                  v-if="noteForm.contentType === 'text'"
                  v-model="noteForm.content"
                  placeholder="Note content..."
                  class="note-input note-textarea"
                  rows="3"
                />
                <div v-else class="note-file-row">
                  <input
                    v-model="noteForm.content"
                    type="url"
                    class="note-input"
                    :placeholder="noteForm.contentType === 'image' ? 'Paste image URL...' : 'Paste video URL...'"
                  />
                  <span v-if="noteForm.content" class="note-file-preview-hint">
                    <img v-if="noteForm.contentType === 'image'" :src="noteForm.content" class="note-file-thumb" @error="e => e.target.style.display='none'" />
                    <span v-else>🎬 Video URL saved</span>
                  </span>
                </div>
                <!-- Color pickers -->
                <div class="note-color-row">
                  <ColorPicker v-model="noteForm.bgColor" small label="Background" />
                  <ColorPicker v-model="noteForm.textColor" small label="Text" />
                  <!-- Preview -->
                  <span class="note-pill note-pill--preview" :style="{ background: noteForm.bgColor, color: noteForm.textColor }">
                    {{ noteForm.title || 'Preview' }}
                  </span>
                </div>
                <div class="note-form-actions">
                  <button class="btn-ghost-sm" @click="resetNoteForm" type="button">Cancel</button>
                  <button class="btn-primary-sm" @click="submitNote" :disabled="!noteForm.title.trim()" type="button">
                    {{ editingNoteId !== null ? 'Save' : 'Add note' }}
                  </button>
                </div>
              </div>
            </div>

            <!-- Attachments -->
            <div class="section">
              <div class="section-title-row">
                <span class="section-title">Attachments <span class="comment-count">{{ task.attachments?.length ?? 0 }}</span></span>
                <button class="note-add-btn" @click="triggerUpload">
                  <svg width="11" height="11" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
                  Upload
                </button>
              </div>
              <input ref="fileInput" type="file" multiple class="attach-file-input" @change="handleFileUpload" />
              <div v-if="task.attachments?.length" class="attach-list">
                <div v-for="a in task.attachments" :key="a.id" class="attach-row">
                  <div class="attach-icon">
                    <svg v-if="fileIcon(a.mime_type) === 'image'" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5" fill="currentColor" stroke="none"/><path d="M21 15l-5-5L5 21"/></svg>
                    <svg v-else-if="fileIcon(a.mime_type) === 'video'" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><polygon points="23 7 16 12 23 17 23 7"/><rect x="1" y="5" width="15" height="14" rx="2"/></svg>
                    <svg v-else-if="fileIcon(a.mime_type) === 'pdf'" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="12" y1="18" x2="12" y2="12"/><line x1="9" y1="15" x2="15" y2="15"/></svg>
                    <svg v-else width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                  </div>
                  <div class="attach-info">
                    <a :href="a.url" target="_blank" rel="noopener" class="attach-name">{{ a.filename }}</a>
                    <span class="attach-meta">{{ formatFileSize(a.size_bytes) }}{{ a.size_bytes ? ' · ' : '' }}{{ formatDateTime(a.uploaded_at) }}</span>
                  </div>
                  <button class="attach-remove" @click="removeAttachment(a.id)" title="Remove attachment">
                    <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" d="M6 18L18 6M6 6l12 12"/></svg>
                  </button>
                </div>
              </div>
              <div v-else class="empty-text">No attachments yet.</div>
            </div>

            <!-- Comments -->
            <div class="section">
              <span class="section-title">Comments <span class="comment-count">{{ task.comments?.length ?? 0 }}</span></span>

              <div v-if="task.comments?.length" class="comment-list">
                <div v-for="c in sortedComments" :key="c.id" class="comment" :class="{ 'comment--pinned': c.pinned }">
                  <div class="comment-avatar">U</div>
                  <div class="comment-content">
                    <div class="comment-header" v-if="c.pinned">
                      <svg width="10" height="10" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                      Pinned
                    </div>
                    <!-- Inline edit mode -->
                    <div v-if="editingCommentId === c.id" class="comment-edit-area">
                      <textarea
                        v-model="editCommentText"
                        class="comment-edit-input"
                        rows="2"
                        @keydown.ctrl.enter="saveEditComment"
                        @keydown.escape="cancelEditComment"
                      />
                      <div class="comment-edit-actions">
                        <button class="comment-edit-save" @click="saveEditComment">Save</button>
                        <button class="comment-edit-cancel" @click="cancelEditComment">Cancel</button>
                      </div>
                    </div>
                    <p v-else class="comment-text">{{ c.text }}<span v-if="c.editedAt" class="comment-edited"> (edited)</span></p>
                    <div class="comment-footer">
                      <span class="comment-time">{{ formatDateTime(c.createdAt) }}</span>
                      <div class="comment-actions">
                        <button
                          v-if="c.author && c.author === user.name"
                          class="comment-action-btn"
                          @click="startEditComment(c)"
                          title="Edit"
                        >
                          <svg width="11" height="11" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931z"/></svg>
                        </button>
                        <button class="comment-action-btn" :class="{ 'comment-action-btn--active': c.pinned }" @click="pinComment(task.id, c.id)" :title="c.pinned ? 'Unpin' : 'Pin'">
                          <svg width="11" height="11" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                        </button>
                        <button class="comment-action-btn comment-action-btn--danger" @click="deleteComment(task.id, c.id)" title="Delete">
                          <svg width="11" height="11" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                      </div>
                    </div>
                  </div>
                </div>
              </div>

              <form class="comment-form" @submit.prevent="submitComment">
                <div class="comment-avatar">U</div>
                <div class="comment-input-row">
                  <textarea
                    v-model="newComment"
                    placeholder="Leave a comment..."
                    class="comment-input"
                    rows="2"
                    @keydown.ctrl.enter="submitComment"
                  />
                  <button type="submit" class="comment-submit" :disabled="!newComment.trim()">
                    Post
                  </button>
                </div>
              </form>
            </div>

          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<style scoped>
.detail-overlay {
  position: fixed;
  inset: 0;
  z-index: 200;
  background: rgba(0, 0, 0, 0.5);
  display: flex;
  justify-content: flex-end;
}
.detail-panel {
  width: 100%;
  max-width: 480px;
  height: 100%;
  background: var(--color-surface-1);
  border-left: 1px solid var(--color-border);
  display: flex;
  flex-direction: column;
  overflow: hidden;
}
.detail-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 14px 16px;
  border-bottom: 1px solid var(--color-border-sub);
  flex-shrink: 0;
  gap: 10px;
}
.detail-panel-title {
  font-size: 12px;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  color: var(--color-text-3);
}
.detail-header-actions {
  display: flex;
  align-items: center;
  gap: 6px;
  margin-left: auto;
}
.hdr-btn {
  display: flex;
  align-items: center;
  gap: 5px;
  padding: 5px 10px;
  border-radius: 6px;
  border: 1px solid var(--color-border);
  background: transparent;
  color: var(--color-text-2);
  font-size: 12px;
  cursor: pointer;
  transition: background 0.15s, color 0.15s;
}
.hdr-btn:hover {
  background: var(--color-surface-3);
  color: var(--color-text-1);
}
.hdr-btn--danger:hover {
  background: var(--color-danger-bg);
  color: var(--color-danger);
  border-color: var(--color-danger-bg);
}
.hdr-btn--muted {
  color: #f5c842;
  border-color: color-mix(in srgb, #f5c842 30%, transparent);
}
.hdr-btn--mute:hover {
  background: color-mix(in srgb, #f5c842 15%, transparent);
  color: #f5c842;
}
.close-btn {
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
.close-btn:hover {
  background: var(--color-surface-3);
  color: var(--color-text-1);
}
.detail-body {
  flex: 1;
  overflow-y: auto;
  padding: 20px 16px;
  display: flex;
  flex-direction: column;
  gap: 20px;
}
.task-title {
  font-size: 16px;
  font-weight: 600;
  color: var(--color-text-1);
  line-height: 1.4;
}

/* Meta grid */
.meta-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 12px 16px;
  padding: 14px;
  background: var(--color-surface-2);
  border: 1px solid var(--color-border-sub);
  border-radius: 8px;
}
.meta-item {
  display: flex;
  flex-direction: column;
  gap: 4px;
}
.meta-key {
  font-size: 11px;
  font-weight: 500;
  color: var(--color-text-3);
  text-transform: uppercase;
  letter-spacing: 0.04em;
}
.meta-val {
  font-size: 13px;
  color: var(--color-text-1);
}
.meta-val--overdue {
  color: var(--color-danger);
}
.status-pill {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  font-size: 12px;
  font-weight: 500;
  color: var(--sc, var(--color-text-2));
}
.status-pill::before {
  content: '';
  width: 7px;
  height: 7px;
  border-radius: 50%;
  background: var(--sc, var(--color-text-2));
  flex-shrink: 0;
}
.meta-labels {
  display: flex;
  flex-wrap: wrap;
  gap: 4px;
}
.detail-label {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 2px 8px;
  border-radius: 99px;
  font-size: 11px;
  font-weight: 500;
  color: var(--color-text-1);
  background: color-mix(in srgb, var(--lc, #8a8a9a) 20%, transparent);
  border: 1px solid color-mix(in srgb, var(--lc, #8a8a9a) 40%, transparent);
}
.detail-label-dot {
  width: 6px;
  height: 6px;
  border-radius: 50%;
  background: var(--lc, #8a8a9a);
  flex-shrink: 0;
}

/* Sections */
.section {
  display: flex;
  flex-direction: column;
  gap: 10px;
}
.section-title {
  font-size: 12px;
  font-weight: 600;
  color: var(--color-text-2);
  display: flex;
  align-items: center;
  gap: 6px;
}
.comment-count {
  font-size: 11px;
  padding: 1px 6px;
  border-radius: 99px;
  background: var(--color-surface-3);
  color: var(--color-text-3);
  font-weight: 500;
}
.description-body {
  font-size: 13px;
  color: var(--color-text-1);
  line-height: 1.6;
  white-space: pre-wrap;
  background: var(--color-surface-2);
  border: 1px solid var(--color-border-sub);
  border-radius: 6px;
  padding: 10px 12px;
}
.empty-text {
  font-size: 13px;
  color: var(--color-text-3);
  font-style: italic;
}

/* Comments */
.comment-list {
  display: flex;
  flex-direction: column;
  gap: 12px;
}
.comment {
  display: flex;
  gap: 10px;
}
.comment-avatar {
  width: 28px;
  height: 28px;
  border-radius: 50%;
  background: var(--color-accent);
  color: #fff;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 12px;
  font-weight: 700;
  flex-shrink: 0;
}
.comment-content {
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 4px;
}
.comment-text {
  font-size: 13px;
  color: var(--color-text-1);
  line-height: 1.5;
  background: var(--color-surface-2);
  border: 1px solid var(--color-border-sub);
  border-radius: 6px;
  padding: 8px 10px;
  white-space: pre-wrap;
  word-break: break-word;
}
.comment-time {
  font-size: 11px;
  color: var(--color-text-3);
}
.comment-footer {
  display: flex;
  align-items: center;
  justify-content: space-between;
}
.comment-actions {
  display: flex;
  gap: 4px;
  opacity: 0;
  transition: opacity 0.12s;
}
.comment:hover .comment-actions { opacity: 1; }
.comment-action-btn {
  display: flex; align-items: center; justify-content: center;
  width: 22px; height: 22px; border-radius: 4px;
  border: 1px solid var(--color-border); background: transparent;
  color: var(--color-text-3); cursor: pointer;
  transition: background 0.1s, color 0.1s, border-color 0.1s;
}
.comment-action-btn:hover { background: var(--color-surface-3); color: var(--color-text-1); }
.comment-action-btn--active { color: var(--color-accent); border-color: var(--color-accent); }
.comment-action-btn--danger:hover { background: var(--color-danger-bg); color: var(--color-danger); border-color: var(--color-danger); }
.comment-edited { font-size: 10px; color: var(--color-text-3); margin-left: 4px; }
.comment-edit-area {
  display: flex;
  flex-direction: column;
  gap: 6px;
  margin-bottom: 4px;
}
.comment-edit-input {
  width: 100%;
  padding: 7px 10px;
  background: var(--color-surface-0);
  border: 1px solid var(--color-accent);
  border-radius: 6px;
  font-size: 13px;
  color: var(--color-text-1);
  font-family: inherit;
  resize: vertical;
  outline: none;
  min-height: 56px;
}
.comment-edit-actions {
  display: flex;
  gap: 5px;
}
.comment-edit-save {
  padding: 3px 10px; border-radius: 5px; border: none;
  background: var(--color-accent); color: #fff; font-size: 11px; font-weight: 600; cursor: pointer;
}
.comment-edit-cancel {
  padding: 3px 10px; border-radius: 5px;
  border: 1px solid var(--color-border); background: transparent;
  color: var(--color-text-2); font-size: 11px; cursor: pointer;
}
.comment-header {
  display: flex; align-items: center; gap: 4px;
  font-size: 10px; font-weight: 600; color: var(--color-accent);
  text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 2px;
}
.comment--pinned .comment-text { border-color: var(--color-accent); }
.comment-form {
  display: flex;
  gap: 10px;
  align-items: flex-start;
}
.comment-input-row {
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 6px;
}
.comment-input {
  width: 100%;
  padding: 8px 10px;
  background: var(--color-surface-0);
  border: 1px solid var(--color-border);
  border-radius: 6px;
  font-size: 13px;
  color: var(--color-text-1);
  font-family: inherit;
  resize: vertical;
  outline: none;
  min-height: 60px;
  transition: border-color 0.15s;
}
.comment-input::placeholder {
  color: var(--color-text-3);
}
.comment-input:focus {
  border-color: var(--color-accent);
}
.comment-submit {
  align-self: flex-end;
  padding: 5px 12px;
  height: 28px;
  border-radius: 5px;
  border: none;
  background: var(--color-accent);
  color: #fff;
  font-size: 12px;
  font-weight: 600;
  cursor: pointer;
  transition: background 0.15s, opacity 0.15s;
}
.comment-submit:hover {
  background: var(--color-accent-hover);
}
.comment-submit:disabled {
  opacity: 0.35;
  cursor: not-allowed;
}

/* Notes */
.section-title-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
}
.note-add-btn {
  display: flex;
  align-items: center;
  gap: 4px;
  padding: 3px 8px;
  border-radius: 5px;
  border: 1px solid var(--color-border);
  background: transparent;
  color: var(--color-text-2);
  font-size: 11px;
  cursor: pointer;
  transition: background 0.15s, color 0.15s;
}
.note-add-btn:hover {
  background: var(--color-surface-3);
  color: var(--color-text-1);
}
.notes-list { display: flex; flex-direction: column; gap: 6px; }
.note-wrapper { display: flex; flex-direction: column; gap: 4px; }

.note-pill {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  padding: 4px 10px;
  border-radius: 99px;
  font-size: 12px;
  font-weight: 600;
  border: none;
  cursor: pointer;
  max-width: 100%;
  text-align: left;
  transition: opacity 0.15s;
  align-self: flex-start;
}
.note-pill:hover { opacity: 0.85; }
.note-pill--preview { pointer-events: none; }
.note-pill-icon { flex-shrink: 0; opacity: 0.85; }
.note-pill-chevron {
  flex-shrink: 0;
  margin-left: 2px;
  transition: transform 0.15s;
  opacity: 0.7;
}
.note-pill-chevron--open { transform: rotate(90deg); }

.note-expanded {
  margin-left: 8px;
  padding: 10px 12px;
  background: var(--color-surface-2);
  border: 1px solid var(--color-border-sub);
  border-radius: 8px;
  display: flex;
  flex-direction: column;
  gap: 8px;
}
.note-content { display: flex; flex-direction: column; gap: 6px; }
.note-text {
  font-size: 13px;
  color: var(--color-text-1);
  line-height: 1.5;
  white-space: pre-wrap;
}
.note-media {
  max-width: 100%;
  max-height: 240px;
  border-radius: 6px;
  object-fit: contain;
}
.note-meta {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 11px;
  color: var(--color-text-3);
  flex-wrap: wrap;
}
.note-author { font-weight: 500; }
.note-actions { display: flex; gap: 4px; margin-left: auto; }
.note-action-btn {
  padding: 2px 7px;
  border: 1px solid var(--color-border);
  border-radius: 4px;
  background: transparent;
  color: var(--color-text-2);
  font-size: 11px;
  cursor: pointer;
  transition: background 0.15s, color 0.15s;
}
.note-action-btn:hover { background: var(--color-surface-3); color: var(--color-text-1); }
.note-action-btn--danger:hover { background: var(--color-danger-bg); color: var(--color-danger); border-color: transparent; }

/* Note form */
.note-file-row {
  display: flex;
  align-items: center;
  gap: 10px;
  flex-wrap: wrap;
}
.note-file-label {
  display: flex; align-items: center; gap: 6px;
  padding: 7px 14px; border-radius: 6px;
  border: 1px dashed var(--color-border);
  background: var(--color-surface-2);
  color: var(--color-text-2); font-size: 12px; font-weight: 500;
  cursor: pointer; transition: border-color 0.15s, color 0.15s;
}
.note-file-label:hover { border-color: var(--color-accent); color: var(--color-accent); }
.note-file-input { display: none; }
.note-file-preview-hint { font-size: 11px; color: var(--color-text-3); }
.note-file-thumb { max-height: 48px; border-radius: 4px; border: 1px solid var(--color-border); }

.note-form {
  display: flex;
  flex-direction: column;
  gap: 10px;
  padding: 12px;
  background: var(--color-surface-2);
  border: 1px solid var(--color-border-sub);
  border-radius: 8px;
}
.note-form-title { font-size: 12px; font-weight: 600; color: var(--color-text-2); }
.note-form-row { display: flex; gap: 8px; }
.note-input {
  width: 100%;
  height: 32px;
  padding: 0 10px;
  background: var(--color-surface-0);
  border: 1px solid var(--color-border);
  border-radius: 6px;
  font-size: 13px;
  color: var(--color-text-1);
  font-family: inherit;
  outline: none;
}
.note-input:focus { border-color: var(--color-accent); }
.note-textarea { height: auto; padding: 8px 10px; resize: vertical; }
.note-type-row { display: flex; gap: 4px; }
.note-type-btn {
  padding: 3px 10px;
  font-size: 11px;
  font-weight: 500;
  border: 1px solid var(--color-border);
  border-radius: 99px;
  background: transparent;
  color: var(--color-text-2);
  cursor: pointer;
  text-transform: capitalize;
  transition: background 0.15s, color 0.15s;
}
.note-type-btn--active {
  background: var(--color-accent);
  color: #fff;
  border-color: var(--color-accent);
}
.note-color-row {
  display: flex;
  align-items: center;
  gap: 12px;
  flex-wrap: wrap;
}
.note-color-label {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 11px;
  color: var(--color-text-2);
  font-weight: 500;
}
.note-color-input {
  width: 28px;
  height: 28px;
  border-radius: 4px;
  border: 1px solid var(--color-border);
  padding: 2px;
  cursor: pointer;
  background: transparent;
}
.note-form-actions { display: flex; justify-content: flex-end; gap: 6px; }
.btn-primary-sm {
  padding: 0 12px; height: 28px; border-radius: 5px; border: none;
  background: var(--color-accent); color: #fff; font-size: 12px; font-weight: 600;
  cursor: pointer;
}
.btn-primary-sm:disabled { opacity: 0.35; cursor: not-allowed; }
.btn-ghost-sm {
  padding: 0 12px; height: 28px; border-radius: 5px;
  border: 1px solid var(--color-border); background: transparent;
  color: var(--color-text-2); font-size: 12px; cursor: pointer;
}

/* Transition */
.detail-slide-enter-active,
.detail-slide-leave-active {
  transition: opacity 0.25s ease;
}
.detail-slide-enter-active .detail-panel,
.detail-slide-leave-active .detail-panel {
  transition: transform 0.25s cubic-bezier(0.4, 0, 0.2, 1);
}
.detail-slide-enter-from,
.detail-slide-leave-to {
  opacity: 0;
}
.detail-slide-enter-from .detail-panel,
.detail-slide-leave-to .detail-panel {
  transform: translateX(100%);
}

/* Attachments */
.attach-file-input { display: none; }
.attach-list {
  display: flex;
  flex-direction: column;
  gap: 6px;
  margin-top: 10px;
}
.attach-row {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 8px 10px;
  background: var(--color-surface-2);
  border: 1px solid var(--color-border);
  border-radius: 8px;
  transition: border-color 0.12s;
}
.attach-row:hover { border-color: var(--color-accent); }
.attach-icon {
  color: var(--color-text-3);
  flex-shrink: 0;
  display: flex;
  align-items: center;
}
.attach-info {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: 2px;
}
.attach-name {
  font-size: 13px;
  color: var(--color-accent);
  text-decoration: none;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.attach-name:hover { text-decoration: underline; }
.attach-meta {
  font-size: 11px;
  color: var(--color-text-3);
}
.attach-remove {
  flex-shrink: 0;
  width: 22px;
  height: 22px;
  border: none;
  border-radius: 4px;
  background: transparent;
  color: var(--color-text-3);
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: background 0.1s, color 0.1s;
}
.attach-remove:hover { background: var(--color-danger-bg); color: var(--color-danger); }
</style>
