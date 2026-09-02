<script setup lang="ts">
import { computed } from 'vue';
import { Button } from '@/Components/ui/button';
import {
  Dialog,
  DialogContent,
  DialogTitle,
  DialogDescription,
} from '@/Components/ui/dialog';
import { CheckCircle2 } from 'lucide-vue-next';

interface Props {
  open: boolean;
  isBorrow?: boolean;
  title?: string;
  description?: string;
  buttonText?: string;
}

const props = withDefaults(defineProps<Props>(), {
  isBorrow: false,
  title: '',
  description: '',
  buttonText: 'Ke Riwayat Permintaan',
});

const emit = defineEmits<{
  (e: 'update:open', value: boolean): void;
  (e: 'confirm'): void;
  (e: 'close'): void;
}>();

const modalTitle = computed(() => {
  if (props.title) return props.title;
  return `${props.isBorrow ? 'Peminjaman' : 'Permintaan'} Terkirim!`;
});

const modalDescription = computed(() => {
  if (props.description) return props.description;
  const label = props.isBorrow ? 'Peminjaman' : 'Permintaan';
  return `${label} Anda telah berhasil dikirimkan dan sedang menunggu approval. Anda akan mendapat notifikasi ketika permintaan diproses.`;
});

const handleConfirm = () => {
  emit('confirm');
};
</script>

<template>
  <Dialog :open="open" @update:open="val => emit('update:open', val)">
    <DialogContent 
      class="w-full max-w-md rounded-[0.875rem] border border-border bg-card p-6 sm:p-8 shadow-2xl flex flex-col items-center text-center gap-4"
      :show-close-button="false"
    >
      <!-- Ikon sukses -->
      <div class="w-16 h-16 rounded-full bg-green-100 dark:bg-green-950/50 flex items-center justify-center">
        <CheckCircle2 class="w-9 h-9 text-green-600 dark:text-green-400" />
      </div>

      <div class="space-y-1">
        <DialogTitle class="text-lg sm:text-xl font-bold text-foreground">
          {{ modalTitle }}
        </DialogTitle>
        <DialogDescription class="text-sm text-muted-foreground">
          {{ modalDescription }}
        </DialogDescription>
      </div>

      <!-- Tombol aksi -->
      <div class="w-full pt-2">
        <Button
          variant="primary"
          class="w-full rounded-[0.875rem] h-10 text-sm font-semibold"
          @click="handleConfirm"
        >
          {{ buttonText }}
        </Button>
      </div>
    </DialogContent>
  </Dialog>
</template>
