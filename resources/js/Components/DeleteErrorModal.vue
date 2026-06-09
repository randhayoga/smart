<script setup lang="ts">
import { X, AlertTriangle } from 'lucide-vue-next';

interface Props {
  isOpen: boolean;
  errorMessage: string;
  title?: string;
}

const props = withDefaults(defineProps<Props>(), {
  title: 'Gagal Menghapus Item',
});

const emit = defineEmits(['close']);
</script>

<template>
  <Teleport to="body">
    <Transition
      enter-active-class="ease-out duration-200"
      enter-from-class="opacity-0"
      enter-to-class="opacity-100"
      leave-active-class="ease-in duration-150"
      leave-from-class="opacity-100"
      leave-to-class="opacity-0"
    >
      <div v-if="isOpen" class="fixed inset-0 z-[100] flex items-center justify-center bg-black/40 backdrop-blur-sm p-4">
        <Transition
          enter-active-class="ease-out duration-200"
          enter-from-class="opacity-0 scale-95"
          enter-to-class="opacity-100 scale-100"
          leave-active-class="ease-in duration-150"
          leave-from-class="opacity-100 scale-100"
          leave-to-class="opacity-0 scale-95"
        >
          <div 
            v-if="isOpen"
            class="bg-card w-full max-w-[576px] rounded-[14px] shadow-2xl overflow-hidden flex flex-col"
            @click.stop
          >
            <!-- Modal Header -->
            <div class="flex items-center p-1 justify-between border-b border-border">
              <h3 class="text-lg font-bold text-foreground p-2">Pemberitahuan</h3>
              <button @click="emit('close')" class="p-2 hover:bg-muted rounded-full transition-colors">
                <X class="w-5 h-5 text-muted-foreground" />
              </button>
            </div>

            <!-- Modal Body -->
            <div class="p-6 flex flex-col items-center text-center space-y-4 flex-grow">
              <div class="w-12 h-12 rounded-full bg-rose-50 dark:bg-rose-950/20 flex items-center justify-center text-[#CC0000]">
                <AlertTriangle class="w-6 h-6" />
              </div>
              <div class="space-y-2">
                <h4 class="text-destructive font-bold text-base">{{ title }}</h4>
                <p class="text-sm text-muted-foreground leading-relaxed whitespace-pre-line">{{ errorMessage }}</p>
              </div>
            </div>

            <!-- Modal Footer -->
            <div class="p-4 bg-muted/30 border-t border-border flex items-center justify-center">
              <button 
                @click="emit('close')"
                class="w-full py-2.5 border border-input rounded-[14px] hover:bg-muted transition-colors shadow-card active:scale-[0.98]"
              >
                Mengerti
              </button>
            </div>
          </div>
        </Transition>
      </div>
    </Transition>
  </Teleport>
</template>
