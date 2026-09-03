<script setup lang="ts">
import { computed } from 'vue';
import { Button } from '@/Components/ui/button';
import {
  Dialog,
  DialogContent,
  DialogTitle,
  DialogDescription,
} from '@/Components/ui/dialog';
import { AlertCircle } from 'lucide-vue-next';

interface Props {
  open: boolean;
  title?: string;
  description?: string;
  buttonText?: string;
}

const props = withDefaults(defineProps<Props>(), {
  title: 'Gagal Mengajukan Permintaan',
  description: '',
  buttonText: 'Tutup',
});

const emit = defineEmits<{
  (e: 'update:open', value: boolean): void;
  (e: 'close'): void;
}>();

const modalTitle = computed(() => {
  return props.title || 'Gagal Mengajukan Permintaan';
});

/**
 * Translates common English validation or system error messages into Indonesian.
 */
const translateErrorMessage = (msg: string): string => {
  if (!msg) return '';
  const trimmed = msg.trim();

  if (/the end date field must be a date after or equal to start date/i.test(trimmed)) {
    return 'Tanggal selesai peminjaman harus sama dengan atau setelah tanggal mulai peminjaman.';
  }
  if (/the start date field must be a date after or equal to today/i.test(trimmed)) {
    return 'Tanggal mulai peminjaman tidak boleh di masa lalu.';
  }
  if (/the alasan field is required/i.test(trimmed)) {
    return 'Alasan wajib diisi.';
  }
  if (/the pemanfaatan field is required/i.test(trimmed)) {
    return 'Pemanfaatan wajib dipilih.';
  }
  if (/the departemen field is required/i.test(trimmed)) {
    return 'Departemen wajib dipilih untuk pemanfaatan corporate.';
  }
  if (/the project field is required/i.test(trimmed)) {
    return 'Project wajib dipilih untuk pemanfaatan project.';
  }
  if (/the items field is required/i.test(trimmed)) {
    return 'Barang yang dipilih wajib ada.';
  }

  return msg;
};

const modalDescription = computed(() => {
  if (!props.description) {
    return 'Terjadi kesalahan saat memproses permintaan Anda. Silakan periksa kembali formulir atau hubungi administrator.';
  }
  return translateErrorMessage(props.description);
});

const handleClose = () => {
  emit('update:open', false);
  emit('close');
};
</script>

<template>
  <Dialog :open="open" @update:open="val => emit('update:open', val)">
    <DialogContent 
      class="w-full max-w-md rounded-[0.875rem] border border-border bg-card p-6 sm:p-8 shadow-2xl flex flex-col items-center text-center gap-4"
      :show-close-button="false"
    >
      <!-- Error Icon -->
      <div class="w-16 h-16 rounded-full bg-rose-100 dark:bg-rose-950/50 flex items-center justify-center">
        <AlertCircle class="w-9 h-9 text-rose-600 dark:text-rose-400" />
      </div>

      <div class="space-y-1">
        <DialogTitle class="text-lg sm:text-xl font-bold text-foreground">
          {{ modalTitle }}
        </DialogTitle>
        <DialogDescription class="text-sm text-muted-foreground whitespace-pre-line">
          {{ modalDescription }}
        </DialogDescription>
      </div>

      <!-- Action Button -->
      <div class="w-full pt-2">
        <Button
          variant="primary"
          class="w-full rounded-[0.875rem] h-10 text-sm font-semibold"
          @click="handleClose"
        >
          {{ buttonText }}
        </Button>
      </div>
    </DialogContent>
  </Dialog>
</template>
