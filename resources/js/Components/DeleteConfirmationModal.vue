<script setup lang="ts">
import { ref, watch } from 'vue';
import { X } from 'lucide-vue-next';

interface Props {
  isOpen: boolean;
  itemCount: number;
  itemName?: string;
  expectedPassword?: string; // For simulation, in real app handle via API
}

const props = withDefaults(defineProps<Props>(), {
  itemName: 'Barang',
  expectedPassword: 'password'
});

const emit = defineEmits(['close', 'confirm']);

const confirmPassword = ref('');
const passwordError = ref('');

// Reset state when modal opens/closes
watch(() => props.isOpen, (newVal) => {
  if (newVal) {
    confirmPassword.value = '';
    passwordError.value = '';
  }
});

const handleConfirm = () => {
  if (!confirmPassword.value) {
    passwordError.value = 'Kata sandi wajib diisi';
    return;
  }

  // Simulated password check
  if (confirmPassword.value !== props.expectedPassword) {
    passwordError.value = 'Kata sandi salah';
    return;
  }

  emit('confirm');
};
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
            class="bg-card w-full max-w-[886px] min-h-[269px] rounded-[14px] shadow-2xl overflow-hidden flex flex-col"
            @click.stop
          >
            <!-- Modal Header -->
            <div class="flex items-center justify-between p-5 border-b border-border">
              <h3 class="text-lg font-bold text-foreground">Konfirmasi Penghapusan {{ itemName }}</h3>
              <button @click="emit('close')" class="p-2 hover:bg-muted rounded-full transition-colors">
                <X class="w-5 h-5 text-muted-foreground" />
              </button>
            </div>

            <!-- Modal Body -->
            <div class="p-6 space-y-4 flex-grow">
              <div class="space-y-1">
                <p class="text-[#CC0000] font-medium">
                  Apakah Anda yakin untuk menghapus {{ itemCount }} {{ itemName }} yang Anda pilih?
                </p>
                <p class="text-[#CC0000] font-bold">
                  Menghapus {{ itemName }} akan menghapus semua data terkait yang menjadi bagian dari {{ itemName.toLowerCase() }} tersebut!
                </p>
              </div>

              <p class="text-sm text-muted-foreground">
                Ketik kata sandi Anda lalu tekan tombol Konfirmasi untuk menghapus {{ itemCount }} {{ itemName.toLowerCase() }} yang Anda pilih<span class="text-rose-500">*</span>
              </p>

              <div class="space-y-1">
                <input 
                  type="password" 
                  v-model="confirmPassword"
                  placeholder="Ketik di sini..." 
                  class="w-full px-4 py-3 border border-input rounded-[14px] bg-background focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-colors"
                  :class="{ 'border-rose-500 focus:ring-rose-500/20 focus:border-rose-500': passwordError }"
                  @keyup.enter="handleConfirm"
                />
                <p v-if="passwordError" class="text-xs text-rose-500 ml-1">{{ passwordError }}</p>
              </div>
            </div>

            <!-- Modal Footer -->
            <div class="p-5 bg-muted/30 border-t border-border flex items-center justify-between">
              <p class="text-xs text-rose-500 italic font-medium">*Wajib diisi</p>
              <div class="flex items-center gap-3">
                <button 
                  @click="emit('close')"
                  class="px-6 py-2.5 bg-background border border-input hover:bg-muted text-foreground text-sm font-medium rounded-[14px] transition-colors shadow-sm"
                >
                  Batal
                </button>
                <button 
                  @click="handleConfirm"
                  class="px-6 py-2.5 bg-[#CC0000] hover:bg-[#AA0000] text-white text-sm font-medium rounded-[14px] transition-colors shadow-sm active:scale-[0.98]"
                >
                  Konfirmasi Penghapusan
                </button>
              </div>
            </div>
          </div>
        </Transition>
      </div>
    </Transition>
  </Teleport>
</template>
