<script setup>
/**
 * ColorPicker — swatch grid + native color-wheel picker
 * Usage:
 *   <ColorPicker v-model="myColor" />
 *   <ColorPicker v-model="myColor" :presets="['#fff', '#000']" label="Pick a colour" />
 */
import { ref, computed } from 'vue'

const props = defineProps({
  modelValue: { type: String, default: '#5b5bd6' },
  presets: {
    type: Array,
    default: () => [
      '#e5484d', '#f76b15', '#f5c842', '#46a758',
      '#5b5bd6', '#8e4ec6', '#e93d82', '#12a594',
      '#5eb1ef', '#3e63dd', '#d4a017', '#ff8c00',
      '#52525f', '#ffffff',
    ],
  },
  label: { type: String, default: '' },
  small: { type: Boolean, default: false },
})

const emit = defineEmits(['update:modelValue'])

// Null/empty = no selection. Custom = non-null value not in presets.
const nativeInput = ref(null)
const isCustom = computed(() => {
  if (!props.modelValue) return false
  return !props.presets.includes(props.modelValue)
})

// Actual hex fed to the native <input type="color"> (must be a valid hex)
const activeHex = computed(() => props.modelValue || '#5b5bd6')

function pick(color) {
  emit('update:modelValue', color)
}

function openNative() {
  nativeInput.value?.click()
}

function onNativeChange(e) {
  emit('update:modelValue', e.target.value)
}
</script>

<template>
  <div class="cp-wrap" :class="{ 'cp-wrap--small': small }">
    <p v-if="label" class="cp-label">{{ label }}</p>
    <div class="cp-swatches">
      <!-- Preset swatches -->
      <button
        v-for="c in presets"
        :key="c"
        type="button"
        class="cp-swatch"
        :class="{ 'cp-swatch--active': modelValue === c && !isCustom }"
        :style="{ background: c, borderColor: c === '#ffffff' ? '#52525f' : c }"
        :title="c"
        :aria-label="'Pick colour ' + c"
        :aria-pressed="modelValue === c && !isCustom"
        @click="pick(c)"
      />

      <!-- Custom colour button (wraps a hidden native input) -->
      <div class="cp-custom-wrap" :class="{ 'cp-swatch--active': isCustom }" :title="isCustom ? modelValue : 'Custom colour…'">
        <button type="button" class="cp-custom-btn" @click="openNative" :style="isCustom ? { background: modelValue } : {}">
          <span v-if="!isCustom" class="cp-rainbow" aria-hidden="true"></span>
          <span v-else class="cp-custom-hex">{{ modelValue }}</span>
        </button>
        <input
          ref="nativeInput"
          type="color"
          :value="activeHex"
          class="cp-native"
          tabindex="-1"
          aria-hidden="true"
          @input="onNativeChange"
        />
      </div>
    </div>
  </div>
</template>

<style scoped>
.cp-wrap { display: flex; flex-direction: column; gap: 6px; }
.cp-label {
  font-size: 11px;
  font-weight: 600;
  color: var(--color-text-3);
  text-transform: uppercase;
  letter-spacing: 0.04em;
}

.cp-swatches {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
  align-items: center;
}

/* Common swatch shape */
.cp-swatch,
.cp-custom-wrap {
  width: 22px;
  height: 22px;
  border-radius: 50%;
  border: 2px solid transparent;
  transition: transform 0.12s, box-shadow 0.12s, border-color 0.12s;
  cursor: pointer;
  flex-shrink: 0;
}
.cp-swatch {
  padding: 0;
  outline: none;
}
.cp-swatch:hover { transform: scale(1.2); }
.cp-swatch--active {
  border-color: #fff !important;
  box-shadow: 0 0 0 2px var(--color-accent);
  transform: scale(1.15);
}

/* Custom button wrapper */
.cp-custom-wrap {
  position: relative;
  overflow: hidden;
  border: 2px solid var(--color-border);
}
.cp-custom-wrap:hover { transform: scale(1.2); border-color: var(--color-text-2); }
.cp-custom-wrap.cp-swatch--active {
  border-color: #fff !important;
  box-shadow: 0 0 0 2px var(--color-accent);
  transform: scale(1.15);
}

.cp-custom-btn {
  width: 100%;
  height: 100%;
  border: none;
  background: transparent;
  padding: 0;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  overflow: hidden;
}

/* Rainbow gradient for the "pick custom" state */
.cp-rainbow {
  display: block;
  width: 100%;
  height: 100%;
  background: conic-gradient(red, yellow, lime, aqua, blue, magenta, red);
  border-radius: 50%;
}

/* When custom colour is active, show the hex value */
.cp-custom-hex {
  font-size: 5px;
  font-weight: 700;
  color: #fff;
  mix-blend-mode: difference;
  letter-spacing: -0.02em;
  white-space: nowrap;
  pointer-events: none;
}

/* Hidden native input — covers the button so the click opens the OS picker */
.cp-native {
  position: absolute;
  inset: 0;
  width: 100%;
  height: 100%;
  opacity: 0;
  border: none;
  padding: 0;
  cursor: pointer;
  pointer-events: none; /* opened programmatically */
}

/* Small variant */
.cp-wrap--small .cp-swatch,
.cp-wrap--small .cp-custom-wrap {
  width: 18px;
  height: 18px;
}
</style>
