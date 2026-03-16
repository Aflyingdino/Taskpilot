import test from 'node:test'
import assert from 'node:assert/strict'

import { formatShortDate, formatLongDate, isOverdue, formatTime } from '../../src/utils/dates.js'
import { STATUS_META, STATUS_OPTIONS, PRIORITY_OPTIONS, PRESET_COLORS } from '../../src/utils/constants.js'

test('date formatters return expected output shape', () => {
  const iso = '2026-03-02T12:30:00Z'

  assert.equal(formatShortDate(iso), '02 Mar')
  assert.equal(formatLongDate(iso), '02 Mar 2026')
  assert.match(formatTime(iso), /^\d{2}:\d{2}$/)
})

test('isOverdue only marks past dates', () => {
  const now = Date.now()
  const past = new Date(now - 60 * 60 * 1000).toISOString()
  const future = new Date(now + 60 * 60 * 1000).toISOString()

  assert.equal(isOverdue(past), true)
  assert.equal(isOverdue(future), false)
  assert.equal(isOverdue(''), false)
})

test('status and priority constants expose required domain values', () => {
  assert.equal(typeof STATUS_META.not_started.label, 'string')
  assert.equal(typeof STATUS_META.done.color, 'string')
  assert.equal(Array.isArray(STATUS_OPTIONS), true)
  assert.equal(STATUS_OPTIONS.some((opt) => opt.value === 'ready_for_test'), true)

  const priorityValues = PRIORITY_OPTIONS.map((opt) => opt.value)
  assert.deepEqual(priorityValues, ['low', 'medium', 'high', 'urgent'])

  assert.equal(PRESET_COLORS.length >= 10, true)
})
