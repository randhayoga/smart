<script setup lang="ts">
import { ref, watch, computed } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { toast } from 'vue-sonner';
import { X, Loader2 } from 'lucide-vue-next';
import { Button } from '@/Components/ui/button';
import Combobox from '@/Components/Combobox.vue';
import { Field, FieldLabel, FieldContent, FieldError } from '@/Components/ui/field';

interface SimpleItem { id: number; name: string; }

interface Props {
  open: boolean;
  items: any[];
  uoms: SimpleItem[];
  brands: SimpleItem[];
}

const props = defineProps<Props>();

const emit = defineEmits<{
  (e: 'update:open', value: boolean): void;
  (e: 'success'): void;
}>();

const isSingle = computed(() => props.items.length === 1);
const selectedItem = computed(() => isSingle.value ? props.items[0] : null);

const form = useForm({
  ids: [] as number[],
  uom_id: null as number | null,
  brand_id: null as number | null,
  name: '',
  specification: '',
  photo: null as File | null,
  photoName: ''
});

const errors = ref({
  uom_id: '',
  brand_id: '',
  name: '',
});

const resetErrors = () => {
  errors.value = { uom_id: '', brand_id: '', name: '' };
};

// Reactive error clearing
watch(() => form.uom_id, v => { if (v && errors.value.uom_id) errors.value.uom_id = ''; });
watch(() => form.brand_id, v => { if (v && errors.value.brand_id) errors.value.brand_id = ''; });
watch(() => form.name, v => { if (v && errors.value.name) errors.value.name = ''; });

// Initialize form when modal opens
watch(() => props.open, (val) => {
  if (!val) return;
  form.reset();
  form.clearErrors();
  resetErrors();
  form.ids = props.items.map(r => r.id);

  // Explicitly reset
  form.uom_id = null;
  form.brand_id = null;
  form.name = '';
  form.specification = '';
  form.photo = null;
  form.photoName = '';

  if (isSingle.value && selectedItem.value) {
    form.uom_id = selectedItem.value.uom_id;
    form.brand_id = selectedItem.value.brand_id;
    form.name = selectedItem.value.name;
    form.specification = selectedItem.value.specification;
    form.photo = null;
    form.photoName = selectedItem.value.image_url ? selectedItem.value.image_url.split('/').pop() : '';
  }
});

const closeModal = () => {
  emit('update:open', false);
  form.ids = [];
  form.uom_id = null;
  form.brand_id = null;
  form.name = '';
  form.specification = '';
  form.photo = null;
  form.photoName = '';
  form.clearErrors();
  resetErrors();
};

const handleFileUpload = (e: any) => {
  const file = e.target.files[0];
  if (!file) return;
  const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png'];
  if (!allowedTypes.includes(file.type)) { alert('Hanya diperbolehkan file .jpg, .jpeg, atau .png'); return; }
  if (file.size > 1024 * 1024) { alert('Ukuran foto maksimal 1MB'); return; }
  form.photo = file;
  form.photoName = file.name;
};

const triggerFileInput = () => {
  const input = document.getElementById('edit-tipe-photo-upload') as HTMLInputElement;
  input?.click();
};

const viewImageInNewTab = () => {
  if (form.photo) {
    const url = URL.createObjectURL(form.photo);
    window.open(url, '_blank');
  } else if (selectedItem.value && selectedItem.value.image_url) {
    window.open('/storage/' + selectedItem.value.image_url, '_blank');
  }
};

const handleSubmit = () => {
  resetErrors();

  if (isSingle.value) {
    let isValid = true;
    if (!form.uom_id) { errors.value.uom_id = 'Satuan belum dipilih'; isValid = false; }
    if (!form.brand_id) { errors.value.brand_id = 'Merek belum dipilih'; isValid = false; }
    if (!form.name) { errors.value.name = 'Nama Tipe belum diisi'; isValid = false; }
    if (!isValid) return;
  } else {
    const hasAtLeastOneField = !!(
      form.uom_id || form.brand_id || form.name || form.specification || form.photo
    );
    if (!hasAtLeastOneField) {
      toast.error('Harap isi minimal satu input untuk melakukan perubahan massal.');
      return;
    }
  }

  form.transform((data) => {
    const formData: any = {
      _method: 'PUT',
      ids: data.ids,
    };
    if (isSingle.value) {
      formData.number = selectedItem.value ? selectedItem.value.code : '';
      formData.subcategory_id = selectedItem.value ? selectedItem.value.subcategory_id : null;
      formData.uom_id = data.uom_id;
      formData.brand_id = data.brand_id;
      formData.name = data.name;
      formData.specification = data.specification;
      if (data.photo) formData.image_url = data.photo;
    } else {
      if (data.uom_id) formData.uom_id = data.uom_id;
      if (data.brand_id) formData.brand_id = data.brand_id;
      if (data.name) formData.name = data.name;
      if (data.specification) formData.specification = data.specification;
      if (data.photo) formData.image_url = data.photo;
    }
    return formData;
  }).post(isSingle.value && selectedItem.value ? `/smart/inventory/barangs/${selectedItem.value.id}` : '/smart/inventory/barangs/bulk', {
    onSuccess: () => {
      closeModal();
      emit('success');
    },
  });
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
      <div v-if="open" @click="closeModal" class="fixed inset-0 z-[100] flex items-center justify-center bg-black/40 backdrop-blur-sm p-4">
        <Transition
          enter-active-class="ease-out duration-200"
          enter-from-class="opacity-0 scale-95"
          enter-to-class="opacity-100 scale-100"
          leave-active-class="ease-in duration-150"
          leave-from-class="opacity-100 scale-100"
          leave-to-class="opacity-0 scale-95"
        >
          <div 
            v-if="open" 
            class="bg-card w-full max-w-[1000px] rounded-[14px] shadow-2xl overflow-hidden flex flex-col" 
            @click.stop
          >
            <!-- Modal Header -->
            <div class="flex items-center justify-between pt-3 pb-2 px-4 border-b border-border">
              <h3 class="text-lg font-bold text-foreground">
                {{ isSingle ? 'Edit Tipe' : 'Edit Tipe Terpilih' }}
              </h3>
              <button @click="closeModal" class="p-2 hover:bg-muted rounded-full transition-colors">
                <X class="w-5 h-5 text-muted-foreground cursor-pointer" />
              </button>
            </div>

            <!-- Modal Body -->
            <div class="p-6">
              <div class="grid grid-cols-1 md:grid-cols-2 gap-x-12 gap-y-6">
                <!-- Left Column -->
                <div class="space-y-6">
                  <Field data-disabled>
                    <FieldLabel>Kode Tipe</FieldLabel>
                    <FieldContent>
                      <input 
                        type="text" 
                        :value="selectedItem ? selectedItem.code : 'Tidak dapat diubah'"
                        disabled
                        class="w-full px-4 py-2 text-sm border border-input rounded-[14px] bg-muted/30 text-muted-foreground cursor-not-allowed h-10"
                      />
                    </FieldContent>
                  </Field>

                  <Field data-disabled>
                    <FieldLabel>Kategori</FieldLabel>
                    <FieldContent>
                      <input 
                        type="text" 
                        :value="selectedItem ? selectedItem.category : 'Tidak dapat diubah'"
                        disabled
                        class="w-full px-4 py-2 text-sm border border-input rounded-[14px] bg-muted/30 text-muted-foreground cursor-not-allowed h-10"
                      />
                    </FieldContent>
                  </Field>

                  <Field data-disabled>
                    <FieldLabel>Subkategori</FieldLabel>
                    <FieldContent>
                      <input 
                        type="text" 
                        :value="selectedItem ? selectedItem.subcategory : 'Tidak dapat diubah'"
                        disabled
                        class="w-full px-4 py-2 text-sm border border-input rounded-[14px] bg-muted/30 text-muted-foreground cursor-not-allowed h-10"
                      />
                    </FieldContent>
                  </Field>

                   <Field :data-invalid="(isSingle && !!errors.uom_id) || undefined">
                    <FieldLabel>
                      <span>Satuan<span v-if="isSingle" class="text-rose-500">*</span></span>
                    </FieldLabel>
                    <FieldContent>
                      <Combobox
                        v-model="form.uom_id"
                        :options="uoms"
                        search-placeholder="Cari satuan..."
                        :default-label="isSingle ? 'Pilih satuan tipe' : 'Tidak berubah'"
                        width-class="w-full h-10 px-4"
                        :error="isSingle && !!errors.uom_id"
                      />
                    </FieldContent>
                    <FieldError v-if="isSingle && errors.uom_id">{{ errors.uom_id }}</FieldError>
                  </Field>
                </div>

                <!-- Right Column -->
                <div class="space-y-6">
                  <Field :data-invalid="(isSingle && !!errors.brand_id) || undefined">
                    <FieldLabel>
                      <span>Merek<span v-if="isSingle" class="text-rose-500">*</span></span>
                    </FieldLabel>
                    <FieldContent>
                      <Combobox
                        v-model="form.brand_id"
                        :options="brands"
                        search-placeholder="Cari merek..."
                        :default-label="isSingle ? 'Pilih merek' : 'Tidak berubah'"
                        width-class="w-full h-10 px-4"
                        :error="isSingle && !!errors.brand_id"
                      />
                    </FieldContent>
                    <FieldError v-if="isSingle && errors.brand_id">{{ errors.brand_id }}</FieldError>
                  </Field>

                  <Field :data-invalid="(isSingle && !!errors.name) || undefined">
                    <FieldLabel>
                      <span>Nama Tipe<span v-if="isSingle" class="text-rose-500">*</span></span>
                    </FieldLabel>
                    <FieldContent>
                      <input 
                        type="text" 
                        v-model="form.name"
                        maxlength="255"
                        :placeholder="isSingle ? 'Input nama tipe di sini...' : 'Tidak berubah'" 
                        class="w-full px-4 py-2 text-sm border rounded-[14px] bg-background focus:outline-none focus:ring-2 transition-colors h-10"
                        :class="[isSingle && errors.name ? 'border-destructive focus:ring-destructive/20 focus:border-destructive' : 'border-input focus:ring-primary/20 focus:border-primary']"
                      />
                    </FieldContent>
                    <FieldError v-if="isSingle && errors.name">{{ errors.name }}</FieldError>
                  </Field>

                  <Field>
                    <FieldLabel>Spesifikasi</FieldLabel>
                    <FieldContent>
                      <input 
                        type="text" 
                        v-model="form.specification"
                        maxlength="255"
                        :placeholder="isSingle ? 'Input spesifikasinya di sini...' : 'Tidak berubah'" 
                        class="w-full px-4 py-2 text-sm border border-input rounded-[14px] bg-background focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-colors h-10"
                      />
                    </FieldContent>
                  </Field>

                  <Field>
                    <FieldLabel>Foto <span class="italic">default</span></FieldLabel>
                    <FieldContent>
                      <div class="flex flex-col gap-1 w-full">
                        <div class="flex gap-2 w-full">
                          <div 
                            class="flex-grow min-w-0 px-4 py-2 text-sm border border-input rounded-[14px] bg-muted/10 truncate flex items-center h-10"
                            :class="[
                              (form.photo || (selectedItem && selectedItem.image_url)) 
                                ? 'cursor-pointer hover:bg-muted/20 hover:text-primary transition-colors text-foreground font-medium underline decoration-dotted' 
                                : 'text-muted-foreground cursor-default'
                            ]"
                            @click="(form.photo || (selectedItem && selectedItem.image_url)) && viewImageInNewTab()"
                          >
                            {{ form.photoName || (isSingle ? 'Belum ada foto yang dipilih' : 'Tidak berubah') }}
                          </div>
                          <input 
                            type="file" 
                            id="edit-tipe-photo-upload" 
                            class="hidden" 
                            accept=".jpg,.jpeg,.png"
                            @change="handleFileUpload"
                          />
                          <Button 
                            @click="triggerFileInput"
                            size="lg"
                          >
                            Pilih File
                          </Button>
                        </div>
                        <p class="text-[10px] text-muted-foreground ml-1">Maksimal ukuran 1 MB (.jpg, .jpeg, .png)</p>
                      </div>
                    </FieldContent>
                  </Field>
                </div>
              </div>
            </div>

            <!-- Modal Footer -->
            <div class="py-3 px-4 border-t border-border flex items-center justify-between">
              <p class="text-sm text-rose-500 italic font-medium">
                {{ isSingle ? '*Wajib diisi' : '*Kosongkan input yang tidak ingin diubah' }}
              </p>
              <div class="flex items-center gap-3">
                <Button 
                  @click="closeModal"
                  variant="white"
                  size="xl"
                >
                  Batal
                </Button>
                <Button 
                  @click="handleSubmit"
                  :disabled="form.processing"
                  variant="primary"
                  size="xl"
                  class="relative"
                >
                  <Loader2 v-if="form.processing" class="absolute inset-0 m-auto h-5 w-5 animate-spin" />
                  <span :class="{ 'opacity-0': form.processing }">
                    {{ isSingle ? 'Simpan Perubahan' : 'Simpan Perubahan Massal' }}
                  </span>
                </Button>
              </div>
            </div>
          </div>
        </Transition>
      </div>
    </Transition>
  </Teleport>
</template>
