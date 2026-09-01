<script setup lang="ts">
/**
 * Formulir Peminjaman Aset tab component allowing administrators to assign, update, and complete asset loans.
 */
import { ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import { toast } from 'vue-sonner';
import { CheckCircle } from 'lucide-vue-next';
import { Button } from '@/Components/ui/button';
import StatusBadge from '@/Components/StatusBadge.vue';
import Combobox from '@/Components/Combobox.vue';
import { Field, FieldLabel, FieldContent, FieldError } from '@/Components/ui/field';

interface Props {
  asset: any;
  users?: { id: number; name: string }[];
}

const props = defineProps<Props>();

const borrowUserId = ref<number | string | null>(null);
const borrowStartDate = ref('');
const borrowNote = ref('');
const isBorrowSubmitting = ref(false);
const isFinishSubmitting = ref(false);
const usersList = ref<{ id: number; name: string }[]>([]);
const errors = ref<{ user_id?: string; start_date?: string; note?: string }>({});

const fetchUsers = async () => {
  if (props.users && props.users.length > 0) {
    usersList.value = props.users;
    return;
  }
  try {
    const res = await fetch('/smart/inventory/users');
    if (res.ok) {
      usersList.value = await res.json();
    }
  } catch (err) {
    console.error('Failed to fetch users:', err);
  }
};

watch(() => props.asset, (newAsset) => {
  fetchUsers();
  errors.value = {};
  if (newAsset?.status === 'Dipinjam' && newAsset?.active_borrowing) {
    borrowUserId.value = newAsset.active_borrowing.user_id;
    borrowStartDate.value = newAsset.active_borrowing.start_date || '';
    borrowNote.value = newAsset.active_borrowing.note || '';
  } else {
    borrowUserId.value = null;
    borrowStartDate.value = new Date().toISOString().split('T')[0];
    borrowNote.value = '';
  }
}, { immediate: true, deep: true });

const handleSaveBorrow = () => {
  if (!props.asset) return;
  errors.value = {};

  if (!borrowUserId.value) {
    errors.value.user_id = 'Peminjam wajib dipilih.';
  }
  if (!borrowStartDate.value) {
    errors.value.start_date = 'Tanggal mulai pinjam wajib diisi.';
  }
  if (Object.keys(errors.value).length > 0) {
    toast.error('Mohon lengkapi data peminjaman yang wajib diisi.');
    return;
  }

  isBorrowSubmitting.value = true;
  router.post(
    `/smart/inventory/units/${props.asset.id}/borrow`,
    {
      user_id: borrowUserId.value,
      start_date: borrowStartDate.value,
      note: borrowNote.value,
    },
    {
      preserveScroll: true,
      onSuccess: () => {
        isBorrowSubmitting.value = false;
        errors.value = {};
      },
      onError: (serverErrors: any) => {
        isBorrowSubmitting.value = false;
        errors.value = serverErrors || {};
        if (serverErrors.user_id) toast.error(serverErrors.user_id);
        else if (serverErrors.start_date) toast.error(serverErrors.start_date);
        else if (serverErrors.note) toast.error(serverErrors.note);
        else toast.error('Gagal menyimpan data peminjaman.');
      },
      onFinish: () => {
        isBorrowSubmitting.value = false;
      }
    }
  );
};

const handleFinishBorrow = () => {
  if (!props.asset) return;
  isFinishSubmitting.value = true;
  router.post(
    `/smart/inventory/units/${props.asset.id}/finish-borrow`,
    {},
    {
      preserveScroll: true,
      onSuccess: () => {
        isFinishSubmitting.value = false;
        errors.value = {};
        borrowUserId.value = null;
        borrowStartDate.value = new Date().toISOString().split('T')[0];
        borrowNote.value = '';
      },
      onError: () => {
        isFinishSubmitting.value = false;
        toast.error('Gagal menyelesaikan peminjaman.');
      },
      onFinish: () => {
        isFinishSubmitting.value = false;
      }
    }
  );
};
</script>

<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between pb-3">
      <div>
        <h4 class="text-lg font-bold text-foreground">
          {{ asset?.status === 'Dipinjam' ? 'Informasi Peminjaman Aktif' : 'Formulir Peminjaman Aset' }}
        </h4>
        <p class="text-sm text-muted-foreground mt-0.5">
          {{ asset?.status === 'Dipinjam' 
            ? 'Aset sedang dipinjam. Anda dapat memperbarui informasi atau menyelesaikan peminjaman.' 
            : 'Pilih peminjam dan tanggal mulai untuk mencatat peminjaman aset ini.' 
          }}
        </p>
      </div>
      <StatusBadge :status="asset?.status" class="rounded-sm" />
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
      <!-- Peminjam (User) -->
      <Field :data-invalid="!!errors.user_id || undefined">
        <FieldLabel>
          <span>Peminjam<span class="text-rose-500">*</span></span>
        </FieldLabel>
        <FieldContent>
          <Combobox
            v-model="borrowUserId"
            :options="usersList"
            search-placeholder="Cari nama atau NPK..."
            default-label="Pilih peminjam"
            width-class="w-full h-10 px-4"
            :error="!!errors.user_id"
          />
        </FieldContent>
        <FieldError v-if="errors.user_id">{{ errors.user_id }}</FieldError>
      </Field>

      <!-- Tanggal Mulai Pinjam -->
      <Field :data-invalid="!!errors.start_date || undefined">
        <FieldLabel>
          <span>Tanggal Mulai Pinjam<span class="text-rose-500">*</span></span>
        </FieldLabel>
        <FieldContent>
          <input
            type="date"
            v-model="borrowStartDate"
            :class="[
              'w-full px-4 py-2 text-sm border rounded-[14px] bg-background focus:outline-none focus:ring-2 transition-colors h-10 text-foreground',
              errors.start_date 
                ? 'border-destructive focus:ring-destructive/20 focus:border-destructive' 
                : 'border-input focus:ring-primary/20 focus:border-primary'
            ]"
          />
        </FieldContent>
        <FieldError v-if="errors.start_date">{{ errors.start_date }}</FieldError>
      </Field>

      <!-- Catatan / Keperluan -->
      <div class="md:col-span-2">
        <Field :data-invalid="!!errors.note || undefined">
          <FieldLabel>
            <span>Catatan / Keperluan</span>
          </FieldLabel>
          <FieldContent>
            <textarea
              v-model="borrowNote"
              rows="4"
              placeholder="Tuliskan catatan, keperluan, atau detail peminjaman..."
              :class="[
                'w-full px-4 py-3 text-sm border rounded-[14px] bg-background focus:outline-none focus:ring-2 transition-colors resize-none text-foreground placeholder:text-muted-foreground',
                errors.note 
                  ? 'border-destructive focus:ring-destructive/20 focus:border-destructive' 
                  : 'border-input focus:ring-primary/20 focus:border-primary'
              ]"
            ></textarea>
          </FieldContent>
          <FieldError v-if="errors.note">{{ errors.note }}</FieldError>
        </Field>
      </div>
    </div>

    <!-- Tombol Aksi di dalam Tab Peminjaman -->
    <div class="flex items-center justify-end gap-3 pt-4">
      <Button
        v-if="asset?.status === 'Dipinjam'"
        type="button"
        variant="success"
        size="lg"
        :disabled="isFinishSubmitting || isBorrowSubmitting"
        @click="handleFinishBorrow"
        class="inline-flex items-center gap-2"
      >
        <CheckCircle class="w-4 h-4" />
        {{ isFinishSubmitting ? 'Memproses...' : 'Peminjaman Selesai' }}
      </Button>

      <Button
        type="button"
        variant="primary"
        size="lg"
        :disabled="isBorrowSubmitting || isFinishSubmitting"
        @click="handleSaveBorrow"
        class="inline-flex items-center gap-2"
      >
        {{ isBorrowSubmitting ? 'Menyimpan...' : (asset?.status === 'Dipinjam' ? 'Simpan Perubahan' : 'Simpan Peminjaman') }}
      </Button>
    </div>
  </div>
</template>
