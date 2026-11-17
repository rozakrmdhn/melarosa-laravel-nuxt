<script setup lang="ts">
import { computed, nextTick, ref, watch } from 'vue';

interface Props {
  modelValue?: number | null;
  allowDecimal?: boolean;
  maxDecimals?: number;
  min?: number;
  max?: number;
  maxDigits?: number;
  required?: boolean;
  placeholder?: string;
  size?: 'xs' | 'sm' | 'md' | 'lg';
  fieldLabel?: string;
  disabled?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
  modelValue: null,
  allowDecimal: true,
  maxDecimals: undefined,
  min: undefined,
  max: undefined,
  maxDigits: undefined,
  required: false,
  placeholder: '',
  size: 'sm',
  fieldLabel: 'Nilai',
  disabled: false,
});

const emit = defineEmits<{
  'update:modelValue': [value: number | null];
  'update:error': [error: string | null];
}>();

const wrapperRef = ref<HTMLElement | null>(null);
const rawText = ref<string>(formatInitialValue(props.modelValue));
const errorMessage = ref<string | null>(null);
const isFocused = ref(false);

const inputMode = computed(() => (props.allowDecimal ? 'decimal' : 'numeric'));

function toFiniteNumber(val: unknown): number | null {
  if (val === null || val === undefined || val === '') return null;
  const parsed = typeof val === 'number' ? val : Number(val);
  return Number.isFinite(parsed) ? parsed : null;
}

function formatInitialValue(val: unknown): string {
  const num = toFiniteNumber(val);
  if (num === null) return '';
  if (!props.allowDecimal) return String(Math.trunc(num));
  return String(num);
}

function parseRawNumber(text: string): number | null {
  const trimmed = text.trim();
  if (!trimmed || trimmed === '.') return null;
  const parsed = Number(trimmed);
  return Number.isFinite(parsed) ? parsed : null;
}

function setError(msg: string | null) {
  errorMessage.value = msg;
  emit('update:error', msg);
}

function syncNativeInputDom(sanitized: string, caretOffset?: number) {
  nextTick(() => {
    const inputEl = wrapperRef.value?.querySelector('input');
    if (inputEl && inputEl.value !== sanitized) {
      inputEl.value = sanitized;
      if (caretOffset !== undefined && isFocused.value) {
        const pos = Math.min(Math.max(0, caretOffset), sanitized.length);
        inputEl.setSelectionRange(pos, pos);
      }
    }
  });
}

function sanitizeInput(inputStr: string): { sanitized: string; inlineError: string | null } {
  const label = props.fieldLabel || 'Nilai';

  if (!props.allowDecimal) {
    let inlineError: string | null = null;
    if (/[.,]/.test(inputStr)) {
      inlineError = `${label} tidak boleh menggunakan angka desimal.`;
    } else if (/-/.test(inputStr)) {
      inlineError = `${label} tidak boleh bernilai negatif.`;
    } else if (/[^0-9]/.test(inputStr)) {
      inlineError = `${label} hanya boleh diisi angka.`;
    }

    let sanitized = inputStr.replace(/[^0-9]/g, '');
    if (props.maxDigits && sanitized.length > props.maxDigits) {
      sanitized = sanitized.slice(0, props.maxDigits);
      if (!inlineError) {
        inlineError = `${label} maksimal ${props.maxDigits} digit.`;
      }
    }
    return { sanitized, inlineError };
  }

  // Decimal-enabled numeric sanitization (convert comma separator to dot)
  const normalized = inputStr.replace(/,/g, '.');
  let inlineError: string | null = null;

  if (/-/.test(normalized)) {
    inlineError = `${label} tidak boleh bernilai negatif.`;
  } else if (/[^0-9.]/.test(normalized)) {
    inlineError = `${label} hanya boleh diisi angka dan titik desimal.`;
  }

  let cleaned = '';
  let hasDot = false;
  let decimalCount = 0;

  for (const ch of normalized) {
    if (ch >= '0' && ch <= '9') {
      if (hasDot) {
        if (props.maxDecimals !== undefined && decimalCount >= props.maxDecimals) {
          if (!inlineError) {
            inlineError = `Maksimal ${props.maxDecimals} angka di belakang koma.`;
          }
          continue;
        }
        decimalCount += 1;
      }
      cleaned += ch;
    } else if (ch === '.') {
      if (!hasDot) {
        hasDot = true;
        cleaned = cleaned === '' ? '0.' : `${cleaned}.`;
      } else if (!inlineError) {
        inlineError = 'Tanda desimal hanya boleh digunakan satu kali.';
      }
    }
  }

  return { sanitized: cleaned, inlineError };
}

function validateBounds(num: number | null, text: string): string | null {
  const label = props.fieldLabel || 'Nilai';

  if (text.trim() === '') {
    return props.required ? `${label} wajib diisi.` : null;
  }

  if (num === null || !Number.isFinite(num)) {
    return `${label} bukan angka yang valid.`;
  }

  if (props.min !== undefined && num < props.min) {
    if (props.max !== undefined && !props.allowDecimal && props.maxDigits) {
      return `${label} harus antara ${props.min} sampai ${props.max}.`;
    }
    return `${label} minimal ${props.min}.`;
  }

  if (props.max !== undefined && num > props.max) {
    if (props.min !== undefined && !props.allowDecimal && props.maxDigits) {
      return `${label} harus antara ${props.min} sampai ${props.max}.`;
    }
    return `${label} maksimal ${props.max}.`;
  }

  return null;
}

function handleKeydown(e: KeyboardEvent) {
  if (e.ctrlKey || e.metaKey || e.altKey || e.key.length > 1) return;

  const key = e.key;
  const label = props.fieldLabel || 'Nilai';
  const target = e.target as HTMLInputElement | null;
  const selStart = target?.selectionStart ?? rawText.value.length;
  const selEnd = target?.selectionEnd ?? rawText.value.length;
  const hasSelection = selEnd > selStart;

  if (key === '-') {
    e.preventDefault();
    setError(`${label} tidak boleh bernilai negatif.`);
    return;
  }

  if (!props.allowDecimal) {
    if (key === '.' || key === ',') {
      e.preventDefault();
      setError(`${label} tidak boleh menggunakan angka desimal.`);
      return;
    }
    if (!/^[0-9]$/.test(key)) {
      e.preventDefault();
      setError(`${label} hanya boleh diisi angka.`);
      return;
    }
    if (props.maxDigits && !hasSelection && rawText.value.length >= props.maxDigits) {
      e.preventDefault();
      setError(`${label} maksimal ${props.maxDigits} digit.`);
    }
    return;
  }

  if (key === '.' || key === ',') {
    const remaining = `${rawText.value.slice(0, selStart)}${rawText.value.slice(selEnd)}`;
    if (remaining.includes('.')) {
      e.preventDefault();
      setError('Tanda desimal hanya boleh digunakan satu kali.');
    }
    return;
  }

  if (!/^[0-9]$/.test(key)) {
    e.preventDefault();
    setError(`${label} hanya boleh diisi angka dan titik desimal.`);
    return;
  }

  if (props.maxDecimals !== undefined && !hasSelection) {
    const dotIdx = rawText.value.indexOf('.');
    if (dotIdx !== -1 && selStart > dotIdx) {
      const decimalsAfterDot = rawText.value.length - dotIdx - 1;
      if (decimalsAfterDot >= props.maxDecimals) {
        e.preventDefault();
        setError(`Maksimal ${props.maxDecimals} angka di belakang koma.`);
      }
    }
  }
}

function handleInput(value: string | number) {
  const inputEl = wrapperRef.value?.querySelector('input');
  const currentCaret = inputEl?.selectionStart ?? null;
  const inputStr = String(value ?? '');
  const { sanitized, inlineError } = sanitizeInput(inputStr);

  rawText.value = sanitized;
  if (sanitized !== inputStr) {
    const adjustedCaret =
      currentCaret !== null
        ? Math.max(0, currentCaret + (sanitized.length - inputStr.length))
        : undefined;
    syncNativeInputDom(sanitized, adjustedCaret);
  }

  const parsed = parseRawNumber(sanitized);
  emit('update:modelValue', parsed);

  if (inlineError) {
    setError(inlineError);
    return;
  }

  // Validate integer length/bounds eagerly only once fixed-length digit count is reached
  if (!props.allowDecimal && props.maxDigits && sanitized.length === props.maxDigits) {
    setError(validateBounds(parsed, sanitized));
    return;
  }

  setError(null);
}

function handleFocus() {
  isFocused.value = true;
}

function handleBlur() {
  isFocused.value = false;
  let cleaned = rawText.value.trim();
  if (cleaned.endsWith('.')) {
    cleaned = cleaned.slice(0, -1);
  }
  if (cleaned === '') {
    rawText.value = '';
    emit('update:modelValue', null);
    setError(validateBounds(null, ''));
    return;
  }

  const parsed = parseRawNumber(cleaned);
  if (parsed !== null) {
    rawText.value = String(parsed);
    emit('update:modelValue', parsed);
  }
  setError(validateBounds(parsed, rawText.value));
}

function validate(): boolean {
  let cleaned = rawText.value.trim();
  if (cleaned.endsWith('.')) {
    cleaned = cleaned.slice(0, -1);
    rawText.value = cleaned;
  }
  const parsed = parseRawNumber(cleaned);
  const err = validateBounds(parsed, cleaned);
  setError(err);
  return err === null;
}

function clearValidation() {
  setError(null);
}

watch(
  () => props.modelValue,
  (nextVal) => {
    const normalizedNext = toFiniteNumber(nextVal);
    const currentParsed = parseRawNumber(rawText.value);

    // Keep user's in-progress decimal typing (e.g. "3.") intact while numeric meaning matches
    if (isFocused.value && normalizedNext === currentParsed) {
      return;
    }

    const formatted = formatInitialValue(normalizedNext);
    if (rawText.value !== formatted) {
      rawText.value = formatted;
    }
    if (!isFocused.value) {
      setError(null);
    }
  },
);

defineExpose({
  validate,
  clearValidation,
});
</script>

<template>
  <div
    ref="wrapperRef"
    class="w-full"
    @keydown="handleKeydown"
    @focusin="handleFocus"
    @focusout="handleBlur"
  >
    <UInput
      :model-value="rawText"
      type="text"
      :inputmode="inputMode"
      autocomplete="off"
      :placeholder="placeholder"
      :size="size"
      :disabled="disabled"
      :color="errorMessage ? 'error' : undefined"
      :aria-invalid="Boolean(errorMessage)"
      :ui="{ base: 'tabular-nums' }"
      class="w-full"
      @update:model-value="handleInput"
    />
  </div>
</template>
