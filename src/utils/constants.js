/**
 * Shared application constants
 * Single source of truth for status, priority, and other domain enumerations.
 */

/** Task statuses with display metadata. */
export const STATUS_META = {
  not_started:    { label: 'Not started',    color: '#8a8a9a' },
  started:        { label: 'Started',        color: '#5b5bd6' },
  ready_for_test: { label: 'Ready for test', color: '#f5c842' },
  done:           { label: 'Done',           color: '#46a758' },
}

/** Task statuses as an ordered array for <select> / <option> elements. */
export const STATUS_OPTIONS = Object.entries(STATUS_META).map(([value, { label }]) => ({
  value,
  label,
}))

/** Group / task priority levels. */
export const PRIORITY_OPTIONS = [
  { value: 'low',    label: '↓ Low' },
  { value: 'medium', label: '→ Medium' },
  { value: 'high',   label: '↑ High' },
  { value: 'urgent', label: '!! Urgent' },
]

/** Default accent colors available project-wide. */
export const PRESET_COLORS = [
  '#e5484d', '#f76b15', '#f5c842', '#46a758',
  '#5b5bd6', '#8e4ec6', '#e93d82', '#12a594',
  '#5eb1ef', '#3e63dd', '#d4a017', '#ff8c00',
  '#52525f', '#ffffff',
]
