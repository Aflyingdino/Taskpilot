/**
 * Date formatting utilities — single source of truth for locale-aware formatting.
 * All functions accept an ISO 8601 string or a Date object.
 */

/**
 * Format a date as "DD Mon" (e.g. "02 Mar").
 * Used for task/group deadline chips.
 */
export function formatShortDate(iso) {
  if (!iso) return ''
  return new Date(iso).toLocaleDateString('en-GB', { day: '2-digit', month: 'short' })
}

/**
 * Format a date as "DD Mon YYYY" (e.g. "02 Mar 2026").
 * Used for archive timestamps and verbose displays.
 */
export function formatLongDate(iso) {
  if (!iso) return ''
  return new Date(iso).toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' })
}

/**
 * Returns true when the given ISO date is in the past (and not today).
 * A null/empty input is never considered overdue.
 */
export function isOverdue(iso) {
  if (!iso) return false
  return new Date(iso) < new Date()
}

/**
 * Format a time as "HH:MM" (24-hour) from an ISO string.
 * Used on calendar event blocks.
 */
export function formatTime(iso) {
  if (!iso) return ''
  return new Date(iso).toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit', hour12: false })
}
