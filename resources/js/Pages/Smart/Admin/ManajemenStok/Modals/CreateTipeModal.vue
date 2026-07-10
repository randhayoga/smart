<script setup lang="ts">
import { ref, watch, computed } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { X, ChevronDown, Loader2 } from 'lucide-vue-next';
import { Button } from '@/Components/ui/button';
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from '@/Components/ui/dropdown-menu';
import Combobox from '@/Components/Combobox.vue';
import { Field, FieldLabel, FieldContent, FieldError } from '@/Components/ui/field';

interface Category    { id: number; code: string; name: string; is_consumable: boolean; }
interface Subcategory { id: number; code: string; name: string; category_id: number; }
interface SimpleItem  { id: number; name: string; }

interface Props {
  open: boolean;
  categories: Category[];
  subcategories: Subcategory[];
  uoms: SimpleItem[];
  brands: SimpleItem[];
  barangs: any[];
}

const props = defineProps<Props>();

const emit = defineEmits<{
  (e: 'update:open', value: boolean): void;
  (e: 'success'): void;
}>();

const newItem = useForm({
  code: '',
  category_id: null as number | null,
  subcategory_id: null as number | null,
  brand_id: null as number | null,
  uom_id: null as number | null,
  name: '',
  specification: '',
  photo: null as File | null,
  photoName: ''
});

const errors = ref({
  code: '',
  category_id: '',
  subcategory_id: '',
  uom_id: '',
  brand_id: '',
  name: '',
  photo: '',
});

const resetErrors = () => {
  errors.value = { code: '', category_id: '', subcategory_id: '', uom_id: '', brand_id: '', name: '', photo: '' };
};

const filteredSubcategories = computed(() => {
  return newItem.category_id ? props.subcategories.filter(s => s.category_id == newItem.category_id) : props.subcategories;
});

const generateCode = () => {
  if (!newItem.category_id || !newItem.subcategory_id) return;
  const cat = props.categories.find(c => c.id === newItem.category_id);
  const sub = props.subcategories.find(s => s.id === newItem.subcategory_id);
  if (!cat || !sub) return;

  const catCode = cat.code.trim();
  const subCode = sub.code.trim();
  const subParts = subCode.split('-');
  const subSuffix = subParts[subParts.length - 1];
  const sameSubItems = (props.barangs || []).filter(item => item.subcategory_id == newItem.subcategory_id);
  let nextNumber = 1;
  if (sameSubItems.length > 0) {
    const numbers = sameSubItems.map(item => {
      const parts = item.code.trim().split('-');
      const lastPart = parts[parts.length - 1];
      const num = parseInt(lastPart);
      return isNaN(num) ? 0 : num;
    });
    nextNumber = Math.max(...numbers) + 1;
  }
  const formattedNumber = nextNumber.toString().padStart(4, '0');
  newItem.code = `${catCode}-${subSuffix}-${formattedNumber}`;
};

watch(() => newItem.category_id, () => { newItem.code = ''; newItem.subcategory_id = null; newItem.brand_id = null; });
watch(() => newItem.subcategory_id, () => { newItem.code = ''; });

// Reactive error clearing
watch(() => newItem.code, v => { if (v && errors.value.code) errors.value.code = ''; });
watch(() => newItem.category_id, v => { if (v && errors.value.category_id) errors.value.category_id = ''; });
watch(() => newItem.subcategory_id, v => { if (v && errors.value.subcategory_id) errors.value.subcategory_id = ''; });
watch(() => newItem.uom_id, v => { if (v && errors.value.uom_id) errors.value.uom_id = ''; });
watch(() => newItem.brand_id, v => { if (v && errors.value.brand_id) errors.value.brand_id = ''; });
watch(() => newItem.name, v => { if (v && errors.value.name) errors.value.name = ''; });
watch(() => newItem.photo, v => { if (v && errors.value.photo) errors.value.photo = ''; });

const handleFileUpload = (e: any) => {
  const file = e.target.files[0];
  if (!file) return;
  const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png'];
  if (!allowedTypes.includes(file.type)) { alert('Hanya diperbolehkan file .jpg, .jpeg, atau .png'); return; }
  if (file.size > 1024 * 1024) { alert('Ukuran foto maksimal 1MB'); return; }
  newItem.photo = file;
  newItem.photoName = file.name;
};

const triggerFileInput = () => {
  const input = document.getElementById('create-tipe-photo-upload') as HTMLInputElement;
  input?.click();
};

const closeModal = () => {
  emit('update:open', false);
  resetErrors();
};

const openModal = () => {
  newItem.reset();
  newItem.clearErrors();
  resetErrors();
  newItem.photoName = '';
  emit('update:open', true);
};

watch(() => props.open, (val) => {
  if (val) openModal();
});

const handleSubmit = () => {
  resetErrors();
  let isValid = true;
  if (!newItem.code) { errors.value.code = 'Kode Tipe belum diisi'; isValid = false; }
  if (!newItem.category_id) { errors.value.category_id = 'Kategori belum dipilih'; isValid = false; }
  if (!newItem.subcategory_id) { errors.value.subcategory_id = 'Subkategori belum dipilih'; isValid = false; }
  if (!newItem.uom_id) { errors.value.uom_id = 'Satuan belum dipilih'; isValid = false; }
  if (!newItem.brand_id) { errors.value.brand_id = 'Merek belum dipilih'; isValid = false; }
  if (!newItem.name) { errors.value.name = 'Nama Tipe belum diisi'; isValid = false; }
  if (!newItem.photo) { errors.value.photo = 'Foto default belum dipilih'; isValid = false; }
  if (!isValid) return;

  newItem.transform((data) => ({
    number: data.code,
    subcategory_id: data.subcategory_id,
    brand_id: data.brand_id,
    uom_id: data.uom_id,
    name: data.name,
    specification: data.specification,
    image_url: data.photo,
  })).post('/smart/inventory/barangs', {
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
              <h3 class="text-lg font-bold text-foreground">Pembuatan Tipe Baru</h3>
              <button @click="closeModal" class="p-2 hover:bg-muted rounded-full transition-colors">
                <X class="w-5 h-5 text-muted-foreground cursor-pointer" />
              </button>
            </div>

            <!-- Modal Body -->
            <div class="p-6">
              <div class="grid grid-cols-1 md:grid-cols-2 gap-x-12 gap-y-6">
                <!-- Left Column -->
                <div class="space-y-6">
                  <Field :data-invalid="!!errors.code || undefined">
                    <FieldLabel for="newItemCode"><span>Kode Tipe<span class="text-rose-500">*</span></span></FieldLabel>
                    <FieldContent>
                      <div class="flex gap-2 w-full">
                        <input 
                          type="text" 
                          id="newItemCode"
                          name="code"
                          v-model="newItem.code"
                          disabled
                          placeholder="Kode Tipe belum di-generate" 
                          class="flex-grow px-4 py-2 text-sm border rounded-[14px] bg-muted/30 text-muted-foreground cursor-not-allowed"
                          :class="[errors.code ? 'border-destructive' : 'border-input']"
                        />
                        <Button
                          @click="generateCode"
                          :disabled="!newItem.category_id || !newItem.subcategory_id"
                          size="lg"                       
                        >
                          Generate
                        </Button>
                      </div>
                    </FieldContent>
                    <FieldError v-if="errors.code">{{ errors.code }}</FieldError>
                  </Field>

                  <Field :data-invalid="!!errors.category_id || undefined">
                    <FieldLabel><span>Kategori<span class="text-rose-500">*</span></span></FieldLabel>
                    <FieldContent>
                      <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                          <Button variant="outline" :class="['w-full justify-between rounded-[14px] font-normal h-10 px-4', !newItem.category_id ? 'text-muted-foreground' : 'text-foreground', errors.category_id ? '!border-destructive focus:!ring-destructive/20 focus:!border-destructive' : '']">
                            {{ categories.find(c => c.id === newItem.category_id)?.name || 'Pilih kategori' }}
                            <ChevronDown class="w-4 h-4 opacity-50" />
                          </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="start" class="w-(--reka-dropdown-menu-trigger-width) min-w-(--reka-dropdown-menu-trigger-width) rounded-[14px] z-[1001]">
                          <DropdownMenuItem v-for="cat in categories" :key="cat.id" @select="newItem.category_id = cat.id">
                            {{ cat.name }}
                          </DropdownMenuItem>
                        </DropdownMenuContent>
                      </DropdownMenu>
                    </FieldContent>
                    <FieldError v-if="errors.category_id">{{ errors.category_id }}</FieldError>
                  </Field>

                  <Field :data-invalid="!!errors.subcategory_id || undefined" :data-disabled="!newItem.category_id || undefined">
                    <FieldLabel><span>Subkategori<span class="text-rose-500">*</span></span></FieldLabel>
                    <FieldContent>
                      <Combobox
                        v-model="newItem.subcategory_id"
                        :options="filteredSubcategories"
                        search-placeholder="Cari subkategori..."
                        default-label="Pilih subkategori"
                        width-class="w-full h-10 px-4"
                        :disabled="!newItem.category_id"
                        :error="!!errors.subcategory_id"
                      />
                    </FieldContent>
                    <FieldError v-if="errors.subcategory_id">{{ errors.subcategory_id }}</FieldError>
                  </Field>

                  <Field :data-invalid="!!errors.uom_id || undefined">
                    <FieldLabel><span>Satuan<span class="text-rose-500">*</span></span></FieldLabel>
                    <FieldContent>
                      <Combobox
                        v-model="newItem.uom_id"
                        :options="uoms"
                        search-placeholder="Cari satuan..."
                        default-label="Pilih satuan tipe"
                        width-class="w-full h-10 px-4"
                        :error="!!errors.uom_id"
                      />
                    </FieldContent>
                    <FieldError v-if="errors.uom_id">{{ errors.uom_id }}</FieldError>
                  </Field>
                </div>

                <!-- Right Column -->
                <div class="space-y-6">
                  <Field :data-invalid="!!errors.brand_id || undefined">
                    <FieldLabel><span>Merek<span class="text-rose-500">*</span></span></FieldLabel>
                    <FieldContent>
                      <Combobox
                        v-model="newItem.brand_id"
                        :options="brands"
                        search-placeholder="Cari merek..."
                        default-label="Pilih merek"
                        width-class="w-full h-10 px-4"
                        :error="!!errors.brand_id"
                      />
                    </FieldContent>
                    <FieldError v-if="errors.brand_id">{{ errors.brand_id }}</FieldError>
                  </Field>

                  <Field :data-invalid="!!errors.name || undefined">
                    <FieldLabel for="newItemNama"><span>Nama Tipe<span class="text-rose-500">*</span></span></FieldLabel>
                    <FieldContent>
                      <input 
                        type="text" 
                        id="newItemNama"
                        name="name"
                        v-model="newItem.name"
                        maxlength="255"
                        placeholder="Input nama tipe di sini..." 
                        class="w-full px-4 py-2 text-sm border rounded-[14px] bg-background focus:outline-none focus:ring-2 transition-colors h-10"
                        :class="[errors.name ? 'border-destructive focus:ring-destructive/20 focus:border-destructive' : 'border-input focus:ring-primary/20 focus:border-primary']"
                      />
                    </FieldContent>
                    <FieldError v-if="errors.name">{{ errors.name }}</FieldError>
                  </Field>

                  <Field>
                    <FieldLabel for="newItemSpecification">Spesifikasi</FieldLabel>
                    <FieldContent>
                      <input 
                        type="text" 
                        id="newItemSpecification"
                        name="specification"
                        v-model="newItem.specification"
                        maxlength="255"
                        placeholder="Input spesifikasinya di sini..." 
                        class="w-full px-4 py-2 text-sm border border-input rounded-[14px] bg-background focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-colors h-10"
                      />
                    </FieldContent>
                  </Field>

                  <Field :data-invalid="!!errors.photo || undefined">
                    <FieldLabel for="create-tipe-photo-upload"><span>Foto <span class="italic">default</span><span class="text-rose-500">*</span></span></FieldLabel>
                    <FieldContent>
                      <div class="flex flex-col gap-1 w-full">
                        <div class="flex gap-2 w-full">
                          <div 
                            class="flex-grow min-w-0 px-4 py-2 text-sm border rounded-[14px] bg-muted/10 text-muted-foreground truncate flex items-center h-10"
                            :class="[errors.photo ? 'border-destructive' : 'border-input']"
                          >
                            {{ newItem.photoName || 'Belum ada foto yang dipilih' }}
                          </div>
                          <input 
                            type="file" 
                            id="create-tipe-photo-upload" 
                            name="photo"
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
                        <p class="text-[10px] text-muted-foreground ml-1">Maksimal ukuran 1 MB</p>
                      </div>
                    </FieldContent>
                    <FieldError v-if="errors.photo">{{ errors.photo }}</FieldError>
                  </Field>
                </div>
              </div>
            </div>

            <!-- Modal Footer -->
            <div class="py-3 px-4 border-t border-border flex items-center justify-between">
              <p class="text-sm text-rose-500 italic font-medium">*Wajib diisi</p>
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
                  :disabled="newItem.processing"
                  variant="primary"
                  size="xl"
                  class="relative"
                >
                  <Loader2 v-if="newItem.processing" class="absolute inset-0 m-auto h-5 w-5 animate-spin" />
                  <span :class="{ 'opacity-0': newItem.processing }">
                    Buat Tipe
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
