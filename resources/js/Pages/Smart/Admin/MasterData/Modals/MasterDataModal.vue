<script setup lang="ts">
/**
 * Master Data Create & Edit unified modal component.
 * Manages form creation and updates across categories, subcategories, UOMs, brands, organizers, vendors, and locations.
 */
import { ref, computed, watch, onMounted, onUnmounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { useForm } from '@inertiajs/vue3';
import { toast } from 'vue-sonner';
import { X, ChevronDown, Loader2 } from 'lucide-vue-next';
import { Button } from '@/Components/ui/button';
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from '@/Components/ui/dropdown-menu';
import { RadioGroup, RadioGroupItem } from '@/Components/ui/radio-group';
import { Label } from '@/Components/ui/label';
import { Field, FieldLabel, FieldContent, FieldError } from '@/Components/ui/field';
import Switch from '@/Components/ui/switch/Switch.vue';
import LocationCombobox from '@/Components/LocationCombobox.vue';
import Combobox from '@/Components/Combobox.vue';
import { useModalLock } from '@/composables/useModalLock';

export type MasterDataTabKey = 'categories' | 'subcategories' | 'uoms' | 'brands' | 'organizers' | 'vendors' | 'locations';

export interface Category {
  id: number;
  code: string;
  name: string;
}

export interface Subcategory {
  id: number;
  code: string;
  name: string;
  category_id: number;
  is_consumable: boolean;
  category?: Category;
  description?: string;
}

export interface SimpleItem {
  id: number;
  name: string;
  description?: string;
}

export interface VendorItem {
  id: number;
  code: string;
  name: string;
  address: string;
  phone_number: string;
  email?: string;
  description?: string;
  contact_person_1?: string;
  cp_email_1?: string;
  cp_phone_1?: string;
  contact_person_2?: string;
  cp_email_2?: string;
  cp_phone_2?: string;
}

export interface LocationItem {
  id: number;
  name: string;
  parent_id: number | null;
  related_departement?: number | null;
  related_department?: {
    id: number;
    org_name: string;
    org_code?: string;
  } | null;
  is_active: boolean;
  parent?: { id: number; name: string } | null;
  children_count?: number;
  full_name?: string;
}

export interface DepartmentItem {
  id: number | string;
  name: string;
  org_name?: string;
  org_code?: string;
}

interface Props {
  open: boolean;
  mode: 'create' | 'edit';
  activeTab: MasterDataTabKey;
  item?: any;
  categories?: Category[];
  locations?: LocationItem[];
  vendors?: VendorItem[];
  departments?: DepartmentItem[];
}

const props = withDefaults(defineProps<Props>(), {
  open: false,
  mode: 'create',
  activeTab: 'categories',
  item: null,
  categories: () => [],
  locations: () => [],
  vendors: () => [],
  departments: () => [],
});

const emit = defineEmits<{
  (e: 'update:open', value: boolean): void;
  (e: 'close'): void;
}>();

const { t } = useI18n();

useModalLock(computed(() => props.open));

const currentTabSingular = computed(() => t(`masterData.tabSingular.${props.activeTab}`));

const modalMaxWidth = computed(() => {
  if (props.activeTab === 'vendors') {
    return 'max-w-[1000px]';
  }
  if (props.activeTab === 'subcategories') {
    return 'max-w-2xl';
  }
  // categories, locations, brands, uoms, organizers
  return 'max-w-xl';
});

const storeRouteMap: Record<MasterDataTabKey, string> = {
  categories:    'smart.master.categories.store',
  subcategories: 'smart.master.subcategories.store',
  uoms:          'smart.master.uoms.store',
  brands:        'smart.master.brands.store',
  organizers:    'smart.master.organizers.store',
  vendors:       'smart.master.vendors.store',
  locations:     'smart.master.locations.store',
};

const updateRouteMap: Record<MasterDataTabKey, string> = {
  categories:    'smart.master.categories.update',
  subcategories: 'smart.master.subcategories.update',
  uoms:          'smart.master.uoms.update',
  brands:        'smart.master.brands.update',
  organizers:    'smart.master.organizers.update',
  vendors:       'smart.master.vendors.update',
  locations:     'smart.master.locations.update',
};

const form = useForm({
  id: null as number | null,
  code: '',
  name: '',
  description: '',
  address: '',
  phone_number: '',
  email: '',
  contact_person_1: '',
  cp_email_1: '',
  cp_phone_1: '',
  contact_person_2: '',
  cp_email_2: '',
  cp_phone_2: '',
  category_id: null as number | null,
  parent_id: null as number | null,
  related_departement: null as number | null,
  is_active: true,
  is_consumable: '1',
});

const formErrors = ref<Record<string, string>>({
  code: '',
  name: '',
  description: '',
  address: '',
  phone_number: '',
  email: '',
  contact_person_1: '',
  cp_email_1: '',
  cp_phone_1: '',
  contact_person_2: '',
  cp_email_2: '',
  cp_phone_2: '',
  category_id: '',
  parent_id: '',
  related_departement: '',
});

const resetFormErrors = () => {
  formErrors.value = {
    code: '',
    name: '',
    description: '',
    address: '',
    phone_number: '',
    email: '',
    contact_person_1: '',
    cp_email_1: '',
    cp_phone_1: '',
    contact_person_2: '',
    cp_email_2: '',
    cp_phone_2: '',
    category_id: '',
    parent_id: '',
    related_departement: '',
  };
};

// Reactive error clearing
watch(() => form.code, (v) => { if (v && formErrors.value.code) formErrors.value.code = ''; });
watch(() => form.name, (v) => { if (v && formErrors.value.name) formErrors.value.name = ''; });
watch(() => form.category_id, (v) => { if (v && formErrors.value.category_id) formErrors.value.category_id = ''; });
watch(() => form.address, (v) => { if (v && formErrors.value.address) formErrors.value.address = ''; });
watch(() => form.phone_number, (v) => { if (v && formErrors.value.phone_number) formErrors.value.phone_number = ''; });

function getDescendantIds(locId: number): number[] {
  const result: number[] = [];
  const queue = [locId];
  while (queue.length > 0) {
    const current = queue.shift()!;
    const children = (props.locations || []).filter(l => l.parent_id === current);
    for (const child of children) {
      result.push(child.id);
      queue.push(child.id);
    }
  }
  return result;
}

const generateVendorCode = () => {
  const existingVendors = props.vendors || [];
  let nextNumber = 0;
  if (existingVendors.length > 0) {
    const numbers = existingVendors.map(vendor => {
      const match = vendor.code.match(/^VN(\d{4})$/);
      return match ? parseInt(match[1]) : -1;
    });
    nextNumber = Math.max(...numbers) + 1;
    if (nextNumber < 0) nextNumber = 0;
  }
  const formattedNumber = nextNumber.toString().padStart(4, '0');
  form.code = `VN${formattedNumber}`;
  if (formErrors.value.code) {
    formErrors.value.code = '';
  }
};

const populateForm = () => {
  resetFormErrors();
  form.reset();

  if (props.mode === 'edit' && props.item) {
    const item = props.item;
    form.id = item.id;
    form.name = item.name ?? '';
    form.code = item.code ?? '';
    form.description = item.description ?? '';
    form.category_id = item.category_id ?? null;
    form.is_consumable = item.is_consumable ? '1' : '0';
    form.parent_id = item.parent_id ?? null;
    form.related_departement = item.related_departement ?? null;
    form.is_active = Boolean(item.is_active);

    if (props.activeTab === 'vendors') {
      form.address = item.address ?? '';
      form.phone_number = item.phone_number ?? '';
      form.email = item.email ?? '';
      form.contact_person_1 = item.contact_person_1 ?? '';
      form.cp_email_1 = item.cp_email_1 ?? '';
      form.cp_phone_1 = item.cp_phone_1 ?? '';
      form.contact_person_2 = item.contact_person_2 ?? '';
      form.cp_email_2 = item.cp_email_2 ?? '';
      form.cp_phone_2 = item.cp_phone_2 ?? '';
    }
  } else {
    // Create mode
    form.id = null;
    form.code = '';
    form.name = '';
    form.description = '';
    form.address = '';
    form.phone_number = '';
    form.email = '';
    form.contact_person_1 = '';
    form.cp_email_1 = '';
    form.cp_phone_1 = '';
    form.contact_person_2 = '';
    form.cp_email_2 = '';
    form.cp_phone_2 = '';
    form.category_id = null;
    form.parent_id = null;
    form.related_departement = null;
    form.is_active = true;
    form.is_consumable = '1';
  }
};

watch([() => props.open, () => props.item, () => props.mode, () => props.activeTab], ([isOpen]) => {
  if (isOpen) {
    populateForm();
  }
}, { immediate: true });

const closeModal = () => {
  emit('update:open', false);
  emit('close');
  resetFormErrors();
  form.reset();
};

const getPayload = () => {
  if (props.activeTab === 'categories') {
    return props.mode === 'create'
      ? { code: form.code, name: form.name }
      : { id: form.id, code: form.code, name: form.name };
  }

  if (props.activeTab === 'subcategories') {
    if (props.mode === 'create') {
      const category = props.categories?.find(c => c.id === form.category_id);
      const prefix = category ? category.code + '-' : '';
      return {
        category_id: form.category_id,
        code: prefix + form.code,
        name: form.name,
        description: form.description,
        is_consumable: form.is_consumable,
      };
    }
    return {
      id: form.id,
      name: form.name,
      description: form.description,
      is_consumable: form.is_consumable,
    };
  }

  if (props.activeTab === 'locations') {
    return {
      ...(props.mode === 'edit' ? { id: form.id } : {}),
      parent_id: form.parent_id,
      name: form.name,
      related_departement: form.related_departement ? Number(form.related_departement) : null,
      is_active: form.is_active,
    };
  }

  if (props.activeTab === 'brands') {
    return {
      ...(props.mode === 'edit' ? { id: form.id } : {}),
      name: form.name,
      description: form.description,
    };
  }

  if (props.activeTab === 'vendors') {
    return {
      ...(props.mode === 'edit' ? { id: form.id } : {}),
      code: form.code,
      name: form.name,
      address: form.address,
      phone_number: form.phone_number,
      email: form.email,
      description: form.description,
      contact_person_1: form.contact_person_1,
      cp_email_1: form.cp_email_1,
      cp_phone_1: form.cp_phone_1,
      contact_person_2: form.contact_person_2,
      cp_email_2: form.cp_email_2,
      cp_phone_2: form.cp_phone_2,
    };
  }

  // uoms, organizers
  return {
    ...(props.mode === 'edit' ? { id: form.id } : {}),
    name: form.name,
  };
};

const handleErrors = (errors: Record<string, string>) => {
  let matched = false;
  Object.keys(errors).forEach((key) => {
    if (key in formErrors.value) {
      formErrors.value[key] = errors[key];
      matched = true;
    }
  });
  if (!matched && Object.values(errors).length > 0) {
    toast.error(Object.values(errors)[0]);
  }
};

const submit = () => {
  resetFormErrors();
  let hasError = false;

  if (props.activeTab === 'categories') {
    if (props.mode === 'create') {
      if (!form.code || !form.code.trim()) {
        formErrors.value.code = t('masterData.validation.categoryCodeRequired');
        hasError = true;
      } else if (form.code.trim().length < 2 || form.code.trim().length > 4) {
        formErrors.value.code = t('masterData.validation.categoryCodeLength');
        hasError = true;
      }
    }
    if (!form.name || !form.name.trim()) {
      formErrors.value.name = t('masterData.validation.categoryNameRequired');
      hasError = true;
    }
  } else if (props.activeTab === 'subcategories') {
    if (props.mode === 'create') {
      if (!form.category_id) {
        formErrors.value.category_id = t('masterData.validation.parentCategoryRequired');
        hasError = true;
      }
      if (!form.code || !form.code.trim()) {
        formErrors.value.code = t('masterData.validation.subcategoryCodeRequired');
        hasError = true;
      }
    }
    if (!form.name || !form.name.trim()) {
      formErrors.value.name = t('masterData.validation.subcategoryNameRequired');
      hasError = true;
    }
  } else if (props.activeTab === 'vendors') {
    if (!form.code || !form.code.trim()) {
      formErrors.value.code = t('masterData.validation.vendorCodeRequired');
      hasError = true;
    } else if (!/^VN\d{4}$/.test(form.code)) {
      formErrors.value.code = t('masterData.validation.vendorCodeFormat');
      hasError = true;
    }
    if (!form.name || !form.name.trim()) {
      formErrors.value.name = t('masterData.validation.vendorNameRequired');
      hasError = true;
    }
    if (!form.address || !form.address.trim()) {
      formErrors.value.address = t('masterData.validation.vendorAddressRequired');
      hasError = true;
    }
    if (!form.phone_number || !form.phone_number.trim()) {
      formErrors.value.phone_number = t('masterData.validation.vendorPhoneRequired');
      hasError = true;
    }
  } else {
    // uoms, brands, organizers, locations
    if (!form.name || !form.name.trim()) {
      formErrors.value.name = t('masterData.validation.nameRequired', { tab: currentTabSingular.value });
      hasError = true;
    }
  }

  if (hasError) return;

  const submitForm = form.transform(() => getPayload());

  if (props.mode === 'create') {
    submitForm.post(route(storeRouteMap[props.activeTab]), {
      preserveScroll: true,
      onSuccess: () => closeModal(),
      onError: (errors: Record<string, string>) => handleErrors(errors),
    });
  } else {
    if (!form.id) return;
    submitForm.put(route(updateRouteMap[props.activeTab], form.id), {
      preserveScroll: true,
      onSuccess: () => closeModal(),
      onError: (errors: Record<string, string>) => handleErrors(errors),
    });
  }
};

const handleKeyDown = (e: KeyboardEvent) => {
  if (e.key === 'Escape' && props.open) {
    closeModal();
  }
};

onMounted(() => {
  document.addEventListener('keydown', handleKeyDown);
});

onUnmounted(() => {
  document.removeEventListener('keydown', handleKeyDown);
});
</script>

<template>
  <Teleport to="body">
    <Transition
      enter-active-class="ease-out duration-300"
      enter-from-class="opacity-0"
      enter-to-class="opacity-100"
      leave-active-class="ease-in duration-200"
      leave-from-class="opacity-100"
      leave-to-class="opacity-0"
    >
      <div
        v-if="props.open"
        @click="closeModal"
        class="fixed inset-0 z-[100] flex items-center justify-center bg-gray-900/50 backdrop-blur-sm p-4 overscroll-contain"
      >
        <div
          :class="[
            'bg-card text-foreground rounded-[14px] shadow-2xl w-full min-h-[261px] max-h-[90vh] overflow-hidden flex flex-col',
            modalMaxWidth
          ]"
          @click.stop
        >
          <!-- Modal Header -->
          <div class="flex items-center justify-between pt-3 pb-2 px-4 border-b border-border">
            <h3 class="text-lg font-bold text-foreground">
              {{ mode === 'create'
                ? t('masterData.createTitle', { tab: currentTabSingular })
                : t('masterData.editTitle', { tab: currentTabSingular })
              }}
            </h3>
            <button @click="closeModal" class="p-2 hover:bg-muted rounded-full transition-colors">
              <X class="w-5 h-5 text-muted-foreground cursor-pointer" />
            </button>
          </div>

          <!-- Modal Body -->
          <div class="p-6 flex-grow overflow-y-auto overscroll-contain">
            <!-- Categories -->
            <div v-if="activeTab === 'categories'" class="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <Field v-if="mode === 'create'" :data-invalid="!!formErrors.code || undefined">
                <FieldLabel>
                  <span>{{ t('masterData.fields.categoryCode') }}<span class="text-destructive">*</span></span>
                </FieldLabel>
                <FieldContent>
                  <input
                    type="text"
                    v-model="form.code"
                    @input="form.code = form.code.replace(/[^A-Za-z0-9]/g, '').toUpperCase()"
                    maxlength="4"
                    :placeholder="t('masterData.placeholders.categoryCode')"
                    class="w-full px-3 py-2 text-sm border rounded-[14px] bg-background focus:outline-none focus:ring-2 transition-colors"
                    :class="[formErrors.code ? 'border-destructive focus:ring-destructive/20 focus:border-destructive' : 'border-input focus:ring-primary/20 focus:border-primary']"
                  />
                </FieldContent>
                <FieldError v-if="formErrors.code">{{ formErrors.code }}</FieldError>
              </Field>
              <Field v-else :data-invalid="!!formErrors.code || undefined" data-disabled="true">
                <FieldLabel>{{ t('masterData.fields.categoryCodeReadOnly') }}</FieldLabel>
                <FieldContent>
                  <input
                    type="text"
                    :value="form.code"
                    disabled
                    class="w-full px-3 py-2 text-sm border border-input rounded-[14px] bg-muted/50 text-muted-foreground cursor-not-allowed"
                  />
                </FieldContent>
                <FieldError v-if="formErrors.code">{{ formErrors.code }}</FieldError>
              </Field>

              <Field :data-invalid="!!formErrors.name || undefined">
                <FieldLabel>
                  <span>{{ t('masterData.fields.categoryName') }}<span class="text-destructive">*</span></span>
                </FieldLabel>
                <FieldContent>
                  <input
                    type="text"
                    v-model="form.name"
                    maxlength="255"
                    :placeholder="t('masterData.placeholders.categoryName')"
                    class="w-full px-3 py-2 text-sm border rounded-[14px] bg-background focus:outline-none focus:ring-2 transition-colors"
                    :class="[formErrors.name ? 'border-destructive focus:ring-destructive/20 focus:border-destructive' : 'border-input focus:ring-primary/20 focus:border-primary']"
                  />
                </FieldContent>
                <FieldError v-if="formErrors.name">{{ formErrors.name }}</FieldError>
              </Field>
            </div>

            <!-- Subcategories -->
            <div v-else-if="activeTab === 'subcategories'" class="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <!-- Parent category -->
              <Field v-if="mode === 'create'" :data-invalid="!!formErrors.category_id || undefined">
                <FieldLabel>
                  <span>{{ t('masterData.fields.parentCategory') }}<span class="text-destructive">*</span></span>
                </FieldLabel>
                <FieldContent>
                  <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                      <Button
                        variant="outline"
                        :class="[
                          'w-full justify-between rounded-[14px] font-normal',
                          !form.category_id ? 'text-muted-foreground' : 'text-foreground',
                          formErrors.category_id ? '!border-destructive focus:!ring-destructive/20 focus:!border-destructive' : ''
                        ]"
                      >
                        {{ form.category_id ? (props.categories.find(c => c.id === form.category_id)?.name || t('masterData.placeholders.selectParentCategory')) : t('masterData.placeholders.selectParentCategory') }}
                        <ChevronDown class="w-4 h-4 opacity-50" />
                      </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent class="w-(--reka-dropdown-menu-trigger-width) min-w-(--reka-dropdown-menu-trigger-width) rounded-[14px] z-[1001]">
                      <DropdownMenuItem v-for="cat in props.categories" :key="cat.id" @select="form.category_id = cat.id">
                        {{ cat.name }}
                      </DropdownMenuItem>
                    </DropdownMenuContent>
                  </DropdownMenu>
                </FieldContent>
                <FieldError v-if="formErrors.category_id">{{ formErrors.category_id }}</FieldError>
              </Field>
              <Field v-else data-disabled="true">
                <FieldLabel>{{ t('masterData.fields.parentCategory') }}</FieldLabel>
                <FieldContent>
                  <input
                    type="text"
                    :value="props.item?.category?.name ?? ''"
                    disabled
                    class="w-full px-3 py-2 text-sm border border-input rounded-[14px] bg-muted/50 text-muted-foreground cursor-not-allowed"
                  />
                </FieldContent>
              </Field>

              <!-- Subcategory code -->
              <Field v-if="mode === 'create'" :data-invalid="!!formErrors.code || undefined" :data-disabled="!form.category_id || undefined">
                <FieldLabel>
                  <span>{{ t('masterData.columns.codeWithTab', { tab: currentTabSingular }) }}<span class="text-destructive">*</span></span>
                </FieldLabel>
                <FieldContent>
                  <div
                    class="flex rounded-[14px] border bg-background focus-within:ring-2 transition-colors"
                    :class="[
                      { 'opacity-50 bg-muted/50': !form.category_id },
                      formErrors.code ? 'border-destructive focus-within:ring-destructive/20 focus-within:border-destructive' : 'border-input focus-within:ring-primary/20 focus-within:border-primary'
                    ]"
                  >
                    <span class="pl-3 py-2 text-sm text-muted-foreground flex items-center bg-transparent select-none whitespace-nowrap">
                      {{ form.category_id ? (props.categories.find(c => c.id === form.category_id)?.code ?? 'KOD') + '-' : 'KOD-' }}
                    </span>
                    <input
                      type="text"
                      v-model="form.code"
                      @input="form.code = form.code.replace(/[^A-Za-z]/g, '').toUpperCase()"
                      maxlength="4"
                      :disabled="!form.category_id"
                      :placeholder="t('masterData.placeholders.fourUppercaseLetters')"
                      class="w-full pr-3 py-2 text-sm bg-transparent border-none focus:ring-0 focus:outline-none"
                      :class="{ 'cursor-not-allowed': !form.category_id }"
                    />
                  </div>
                </FieldContent>
                <FieldError v-if="formErrors.code">{{ formErrors.code }}</FieldError>
              </Field>
              <Field v-else data-disabled="true">
                <FieldLabel>{{ t('masterData.fields.subcategoryCodeReadOnly') }}</FieldLabel>
                <FieldContent>
                  <input
                    type="text"
                    :value="props.item?.code"
                    disabled
                    class="w-full px-3 py-2 text-sm border border-input rounded-[14px] bg-muted/50 text-muted-foreground cursor-not-allowed"
                  />
                </FieldContent>
              </Field>

              <!-- Name -->
              <Field :data-invalid="!!formErrors.name || undefined" class="sm:col-span-2">
                <FieldLabel>
                  <span>{{ t('masterData.fields.subcategoryName') }}<span class="text-destructive">*</span></span>
                </FieldLabel>
                <FieldContent>
                  <input
                    type="text"
                    v-model="form.name"
                    maxlength="255"
                    :placeholder="t('masterData.placeholders.subcategoryName')"
                    class="w-full px-3 py-2 text-sm border rounded-[14px] bg-background focus:outline-none focus:ring-2 transition-colors"
                    :class="[formErrors.name ? 'border-destructive focus:ring-destructive/20 focus:border-destructive' : 'border-input focus:ring-primary/20 focus:border-primary']"
                  />
                </FieldContent>
                <FieldError v-if="formErrors.name">{{ formErrors.name }}</FieldError>
              </Field>

              <!-- Classification -->
              <Field class="sm:col-span-2" :data-disabled="mode === 'edit' || undefined">
                <FieldLabel>
                  <span>{{ t('masterData.fields.classification') }}<span class="text-destructive">*</span></span>
                </FieldLabel>
                <FieldContent>
                  <RadioGroup v-model="form.is_consumable" :disabled="mode === 'edit'" class="flex gap-6">
                    <div class="flex items-center space-x-2" :class="{ 'opacity-60': mode === 'edit' }">
                      <RadioGroupItem id="sub-consumable-true" value="1" :class="mode === 'edit' ? 'cursor-not-allowed' : 'cursor-pointer'" />
                      <Label for="sub-consumable-true" class="font-normal" :class="mode === 'edit' ? 'cursor-not-allowed' : 'cursor-pointer'">
                        {{ t('masterData.classification.consumable') }}
                      </Label>
                    </div>
                    <div class="flex items-center space-x-2" :class="{ 'opacity-60': mode === 'edit' }">
                      <RadioGroupItem id="sub-consumable-false" value="0" :class="mode === 'edit' ? 'cursor-not-allowed' : 'cursor-pointer'" />
                      <Label for="sub-consumable-false" class="font-normal" :class="mode === 'edit' ? 'cursor-not-allowed' : 'cursor-pointer'">
                        {{ t('masterData.classification.asset') }}
                      </Label>
                    </div>
                  </RadioGroup>
                </FieldContent>
              </Field>

              <!-- Description -->
              <Field class="sm:col-span-2">
                <FieldLabel><span>{{ t('masterData.fields.description') }}</span></FieldLabel>
                <FieldContent>
                  <textarea
                    v-model="form.description"
                    :placeholder="t('masterData.placeholders.subcategoryDescription')"
                    rows="3"
                    class="w-full px-3 py-2 text-sm border rounded-[14px] bg-background focus:outline-none focus:ring-2 transition-colors border-input focus:ring-primary/20 focus:border-primary"
                  />
                </FieldContent>
              </Field>
            </div>

            <!-- Locations -->
            <div v-else-if="activeTab === 'locations'" class="space-y-4">
              <Field>
                <FieldLabel>{{ t('masterData.fields.parentLocation') }}</FieldLabel>
                <FieldContent>
                  <LocationCombobox
                    v-model="form.parent_id"
                    :locations="props.locations"
                    :exclude-ids="mode === 'edit' && props.item ? [props.item.id, ...getDescendantIds(props.item.id)] : []"
                    :placeholder="t('masterData.placeholders.selectParentLocation')"
                    :clearable="true"
                  />
                </FieldContent>
              </Field>

              <Field :data-invalid="!!formErrors.name || undefined">
                <FieldLabel>
                  <span>{{ t('masterData.fields.locationName') }}<span class="text-destructive">*</span></span>
                </FieldLabel>
                <FieldContent>
                  <input
                    type="text"
                    v-model="form.name"
                    maxlength="255"
                    :placeholder="t('masterData.placeholders.locationName')"
                    class="w-full px-3 py-2 text-sm border rounded-[14px] bg-background focus:outline-none focus:ring-2 transition-colors"
                    :class="[formErrors.name ? 'border-destructive focus:ring-destructive/20 focus:border-destructive' : 'border-input focus:ring-primary/20 focus:border-primary']"
                  />
                </FieldContent>
                <FieldError v-if="formErrors.name">{{ formErrors.name }}</FieldError>
              </Field>

              <Field :data-invalid="!!formErrors.related_departement || undefined">
                <FieldLabel>{{ t('masterData.fields.relatedDepartment') }}</FieldLabel>
                <FieldContent>
                  <Combobox
                    v-model="form.related_departement"
                    :options="props.departments"
                    :placeholder="t('masterData.placeholders.selectDepartment')"
                    :search-placeholder="t('masterData.placeholders.searchDepartment')"
                    :default-label="t('masterData.placeholders.noDepartment')"
                    width-class="w-full h-10 px-4"
                  />
                </FieldContent>
                <FieldError v-if="formErrors.related_departement">{{ formErrors.related_departement }}</FieldError>
              </Field>

              <div class="flex justify-between items-center pt-2 border-t border-border/50">
                <span
                  class="text-sm font-medium text-foreground cursor-pointer select-none"
                  @click="form.is_active = !form.is_active"
                >
                  {{ form.is_active ? t('masterData.status.locationActive') : t('masterData.status.locationInactive') }}
                </span>
                <Switch
                  v-model="form.is_active"
                  class="data-[state=checked]:!bg-emerald-600 data-[state=unchecked]:!bg-slate-300 dark:data-[state=unchecked]:!bg-slate-700"
                />
              </div>
            </div>

            <!-- Brands -->
            <div v-else-if="activeTab === 'brands'" class="space-y-4">
              <Field :data-invalid="!!formErrors.name || undefined">
                <FieldLabel>
                  <span>{{ t('masterData.fields.brandName') }}<span class="text-destructive">*</span></span>
                </FieldLabel>
                <FieldContent>
                  <input
                    type="text"
                    v-model="form.name"
                    maxlength="255"
                    :placeholder="t('masterData.placeholders.brandName')"
                    class="w-full px-3 py-2 text-sm border rounded-[14px] bg-background focus:outline-none focus:ring-2 transition-colors"
                    :class="[formErrors.name ? 'border-destructive focus:ring-destructive/20 focus:border-destructive' : 'border-input focus:ring-primary/20 focus:border-primary']"
                  />
                </FieldContent>
                <FieldError v-if="formErrors.name">{{ formErrors.name }}</FieldError>
              </Field>
              <Field>
                <FieldLabel>{{ t('masterData.fields.description') }}</FieldLabel>
                <FieldContent>
                  <textarea
                    v-model="form.description"
                    :placeholder="t('masterData.placeholders.brandDescription')"
                    rows="3"
                    class="w-full px-3 py-2 text-sm border rounded-[14px] bg-background focus:outline-none focus:ring-2 transition-colors border-input focus:ring-primary/20 focus:border-primary"
                  />
                </FieldContent>
              </Field>
            </div>

            <!-- Vendors -->
            <div v-else-if="activeTab === 'vendors'" class="grid grid-cols-1 md:grid-cols-3 gap-4">
              <Field :data-invalid="!!formErrors.code || undefined">
                <FieldLabel>
                  <span>{{ t('masterData.fields.vendorCode') }}<span class="text-destructive">*</span></span>
                </FieldLabel>
                <FieldContent>
                  <div v-if="mode === 'create'" class="flex gap-2 w-full">
                    <input
                      type="text"
                      v-model="form.code"
                      disabled
                      :placeholder="t('masterData.placeholders.vendorCodeNotGenerated')"
                      class="flex-grow px-3 py-2 text-sm border rounded-[14px] bg-muted/30 text-muted-foreground cursor-not-allowed"
                      :class="[formErrors.code ? 'border-destructive' : 'border-input']"
                    />
                    <Button type="button" @click="generateVendorCode" size="lg">
                      {{ t('masterData.actions.generate') }}
                    </Button>
                  </div>
                  <input
                    v-else
                    type="text"
                    :value="form.code"
                    disabled
                    class="w-full px-3 py-2 text-sm border rounded-[14px] bg-muted/30 text-muted-foreground cursor-not-allowed"
                    :class="[formErrors.code ? 'border-destructive' : 'border-input']"
                  />
                </FieldContent>
                <FieldError v-if="formErrors.code">{{ formErrors.code }}</FieldError>
              </Field>

              <Field :data-invalid="!!formErrors.name || undefined" class="md:col-span-2">
                <FieldLabel>
                  <span>{{ t('masterData.fields.vendorName') }}<span class="text-destructive">*</span></span>
                </FieldLabel>
                <FieldContent>
                  <input
                    type="text"
                    v-model="form.name"
                    maxlength="255"
                    :placeholder="t('masterData.placeholders.vendorName')"
                    class="w-full px-3 py-2 text-sm border rounded-[14px] bg-background focus:outline-none focus:ring-2 transition-colors"
                    :class="[formErrors.name ? 'border-destructive focus:ring-destructive/20 focus:border-destructive' : 'border-input focus:ring-primary/20 focus:border-primary']"
                  />
                </FieldContent>
                <FieldError v-if="formErrors.name">{{ formErrors.name }}</FieldError>
              </Field>

              <Field :data-invalid="!!formErrors.phone_number || undefined">
                <FieldLabel>
                  <span>{{ t('masterData.fields.phoneNumber') }}<span class="text-destructive">*</span></span>
                </FieldLabel>
                <FieldContent>
                  <input
                    type="text"
                    v-model="form.phone_number"
                    maxlength="255"
                    :placeholder="t('masterData.placeholders.phoneNumber')"
                    class="w-full px-3 py-2 text-sm border rounded-[14px] bg-background focus:outline-none focus:ring-2 transition-colors"
                    :class="[formErrors.phone_number ? 'border-destructive focus:ring-destructive/20 focus:border-destructive' : 'border-input focus:ring-primary/20 focus:border-primary']"
                  />
                </FieldContent>
                <FieldError v-if="formErrors.phone_number">{{ formErrors.phone_number }}</FieldError>
              </Field>

              <Field :data-invalid="!!formErrors.address || undefined" class="md:col-span-2">
                <FieldLabel>
                  <span>{{ t('masterData.fields.address') }}<span class="text-destructive">*</span></span>
                </FieldLabel>
                <FieldContent>
                  <input
                    type="text"
                    v-model="form.address"
                    maxlength="255"
                    :placeholder="t('masterData.placeholders.address')"
                    class="w-full px-3 py-2 text-sm border rounded-[14px] bg-background focus:outline-none focus:ring-2 transition-colors"
                    :class="[formErrors.address ? 'border-destructive focus:ring-destructive/20 focus:border-destructive' : 'border-input focus:ring-primary/20 focus:border-primary']"
                  />
                </FieldContent>
                <FieldError v-if="formErrors.address">{{ formErrors.address }}</FieldError>
              </Field>

              <Field :data-invalid="!!formErrors.email || undefined">
                <FieldLabel>{{ t('masterData.fields.email') }}</FieldLabel>
                <FieldContent>
                  <input
                    type="email"
                    v-model="form.email"
                    maxlength="255"
                    :placeholder="t('masterData.placeholders.email')"
                    class="w-full px-3 py-2 text-sm border rounded-[14px] bg-background focus:outline-none focus:ring-2 transition-colors border-input focus:ring-primary/20 focus:border-primary"
                  />
                </FieldContent>
                <FieldError v-if="formErrors.email">{{ formErrors.email }}</FieldError>
              </Field>

              <Field class="md:col-span-2">
                <FieldLabel>{{ t('masterData.fields.description') }}</FieldLabel>
                <FieldContent>
                  <input
                    type="text"
                    v-model="form.description"
                    maxlength="255"
                    :placeholder="t('masterData.placeholders.vendorDescription')"
                    class="w-full px-3 py-2 text-sm border rounded-[14px] bg-background focus:outline-none focus:ring-2 transition-colors border-input focus:ring-primary/20 focus:border-primary"
                  />
                </FieldContent>
              </Field>

              <!-- Contact Person 1 Section -->
              <div class="md:col-span-3 border-t pt-4 mt-2">
                <h4 class="text-sm font-semibold text-foreground mb-3">{{ t('masterData.fields.contactPerson1') }}</h4>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                  <Field>
                    <FieldLabel>{{ t('masterData.fields.cp1Name') }}</FieldLabel>
                    <FieldContent>
                      <input
                        type="text"
                        v-model="form.contact_person_1"
                        maxlength="255"
                        :placeholder="t('masterData.placeholders.cp1Name')"
                        class="w-full px-3 py-2 text-sm border rounded-[14px] bg-background focus:outline-none focus:ring-2 transition-colors border-input focus:ring-primary/20 focus:border-primary"
                      />
                    </FieldContent>
                  </Field>
                  <Field>
                    <FieldLabel>{{ t('masterData.fields.cp1Email') }}</FieldLabel>
                    <FieldContent>
                      <input
                        type="email"
                        v-model="form.cp_email_1"
                        maxlength="255"
                        :placeholder="t('masterData.placeholders.cp1Email')"
                        class="w-full px-3 py-2 text-sm border rounded-[14px] bg-background focus:outline-none focus:ring-2 transition-colors border-input focus:ring-primary/20 focus:border-primary"
                      />
                    </FieldContent>
                  </Field>
                  <Field>
                    <FieldLabel>{{ t('masterData.fields.cp1Phone') }}</FieldLabel>
                    <FieldContent>
                      <input
                        type="text"
                        v-model="form.cp_phone_1"
                        maxlength="255"
                        :placeholder="t('masterData.placeholders.cp1Phone')"
                        class="w-full px-3 py-2 text-sm border rounded-[14px] bg-background focus:outline-none focus:ring-2 transition-colors border-input focus:ring-primary/20 focus:border-primary"
                      />
                    </FieldContent>
                  </Field>
                </div>
              </div>

              <!-- Contact Person 2 Section -->
              <div class="md:col-span-3 border-t pt-4 mt-2">
                <h4 class="text-sm font-semibold text-foreground mb-3">{{ t('masterData.fields.contactPerson2') }}</h4>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                  <Field>
                    <FieldLabel>{{ t('masterData.fields.cp2Name') }}</FieldLabel>
                    <FieldContent>
                      <input
                        type="text"
                        v-model="form.contact_person_2"
                        maxlength="255"
                        :placeholder="t('masterData.placeholders.cp2Name')"
                        class="w-full px-3 py-2 text-sm border rounded-[14px] bg-background focus:outline-none focus:ring-2 transition-colors border-input focus:ring-primary/20 focus:border-primary"
                      />
                    </FieldContent>
                  </Field>
                  <Field>
                    <FieldLabel>{{ t('masterData.fields.cp2Email') }}</FieldLabel>
                    <FieldContent>
                      <input
                        type="email"
                        v-model="form.cp_email_2"
                        maxlength="255"
                        :placeholder="t('masterData.placeholders.cp2Email')"
                        class="w-full px-3 py-2 text-sm border rounded-[14px] bg-background focus:outline-none focus:ring-2 transition-colors border-input focus:ring-primary/20 focus:border-primary"
                      />
                    </FieldContent>
                  </Field>
                  <Field>
                    <FieldLabel>{{ t('masterData.fields.cp2Phone') }}</FieldLabel>
                    <FieldContent>
                      <input
                        type="text"
                        v-model="form.cp_phone_2"
                        maxlength="255"
                        :placeholder="t('masterData.placeholders.cp2Phone')"
                        class="w-full px-3 py-2 text-sm border rounded-[14px] bg-background focus:outline-none focus:ring-2 transition-colors border-input focus:ring-primary/20 focus:border-primary"
                      />
                    </FieldContent>
                  </Field>
                </div>
              </div>
            </div>

            <!-- Simple items: UOMs, Organizers -->
            <div v-else class="space-y-4">
              <Field :data-invalid="!!formErrors.name || undefined">
                <FieldLabel>
                  <span>{{ t('masterData.fields.nameWithTab', { tab: currentTabSingular }) }}<span class="text-destructive">*</span></span>
                </FieldLabel>
                <FieldContent>
                  <input
                    type="text"
                    v-model="form.name"
                    maxlength="255"
                    :placeholder="t('masterData.placeholders.nameWithTab', { tab: currentTabSingular })"
                    class="w-full px-3 py-2 text-sm border rounded-[14px] bg-background focus:outline-none focus:ring-2 transition-colors"
                    :class="[formErrors.name ? 'border-destructive focus:ring-destructive/20 focus:border-destructive' : 'border-input focus:ring-primary/20 focus:border-primary']"
                  />
                </FieldContent>
                <FieldError v-if="formErrors.name">{{ formErrors.name }}</FieldError>
              </Field>
            </div>
          </div>

          <!-- Modal Footer -->
          <div class="py-3 px-4 border-t border-border flex items-center justify-between">
            <p class="text-sm text-rose-500 italic font-medium">{{ t('masterData.fields.requiredMarker') }}</p>
            <div class="flex items-center gap-3">
              <Button
                type="button"
                @click.stop="closeModal"
                variant="white"
                size="xl"
              >
                {{ t('common.cancel') }}
              </Button>
              <Button
                type="button"
                @click.stop="submit"
                :disabled="form.processing"
                variant="primary"
                size="xl"
                class="relative"
              >
                <Loader2 v-if="form.processing" class="absolute inset-0 m-auto h-5 w-5 animate-spin" />
                <span :class="{ 'opacity-0': form.processing }">
                  {{ mode === 'create'
                    ? t('masterData.createButton', { tab: currentTabSingular })
                    : t('masterData.saveChanges')
                  }}
                </span>
              </Button>
            </div>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>
