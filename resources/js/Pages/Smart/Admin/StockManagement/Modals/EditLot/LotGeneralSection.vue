<script setup lang="ts">
/**
 * General LOT section for EditLotModal handling LOT Code, Entry Type, PO/Memo, Vendor, and Organizer.
 * Matches InputNewStock LotSection.vue styling and structure.
 */
import { ref, computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { ChevronDown, Trash2 } from 'lucide-vue-next';
import { Button } from '@/Components/ui/button';
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from '@/Components/ui/dropdown-menu';
import Combobox from '@/Components/Combobox.vue';
import { Field, FieldLabel, FieldContent, FieldError } from '@/Components/ui/field';
import { toast } from 'vue-sonner';

interface Props {
  form: any;
  errors: Record<string, string>;
  isSingle: boolean;
  organizers: { id: number; name: string }[];
  vendors: { id: number; name: string }[];
  isBeragamLot: boolean;
  selectedItem?: any;
}

const props = defineProps<Props>();

const { t } = useI18n();

const memoFileInputRef = ref<HTMLInputElement | null>(null);

const filteredVendors = computed(() => {
  return (props.vendors || []).filter(v => v.name?.trim().toLowerCase() !== 'beragam');
});

const hasMemoFile = computed(() => {
  if (props.form.delete_memo) return false;
  return Boolean(props.form.memo_file || props.form.memo_file_name);
});

const onSelectEntryType = (type: 'Procurement' | 'Hibah') => {
  props.form.entry_type = type;
  if (type === 'Procurement') {
    props.form.memo_number = '';
    props.form.date_of_grant = '';
    props.form.memo_file = null;
    props.form.memo_file_name = '';
    props.form.delete_memo = false;
  } else {
    props.form.vendor_id = '';
    props.form.po_number = '';
    props.form.purchase_date = '';
  }
};

const triggerMemoFileInput = () => {
  memoFileInputRef.value?.click();
};

const handleMemoFileUpload = (e: Event) => {
  const target = e.target as HTMLInputElement;
  const file = target.files?.[0];
  if (!file) return;

  const allowedTypes = ['application/pdf', 'image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
  if (!allowedTypes.includes(file.type)) {
    toast.error(t('inventory.invalidDocFormat'));
    target.value = '';
    return;
  }
  if (file.size > 2 * 1024 * 1024) {
    toast.error(t('inventory.fileTooLarge2Mb'));
    target.value = '';
    return;
  }

  props.form.memo_file = file;
  props.form.memo_file_name = file.name;
  props.form.delete_memo = false;
};

const handleDeleteMemoFile = () => {
  props.form.memo_file = null;
  props.form.memo_file_name = '';
  props.form.delete_memo = true;
  if (memoFileInputRef.value) {
    memoFileInputRef.value.value = '';
  }
};

const viewMemoFileInNewTab = () => {
  if (props.form.memo_file) {
    window.open(URL.createObjectURL(props.form.memo_file), '_blank');
  } else if (props.selectedItem?.memo_url && !props.form.delete_memo) {
    window.open('/media/' + props.selectedItem.memo_url, '_blank');
  }
};
</script>

<template>
  <div class="grid grid-cols-1 md:grid-cols-2 gap-x-12 gap-y-6">
    <!-- Left Column: LOT Code, Entry Type, PO Number / Memo Number -->
    <div class="space-y-6">
      <!-- LOT Code -->
      <Field data-disabled>
        <FieldLabel>{{ t('inventory.lotCode') }}</FieldLabel>
        <FieldContent>
          <input
            type="text"
            :value="isSingle ? form.number : t('inventory.cannotBeChangedBulk')"
            disabled
            class="w-full px-4 py-2 text-sm border border-input rounded-[14px] bg-muted/30 text-muted-foreground cursor-not-allowed h-10"
          />
        </FieldContent>
      </Field>

      <!-- Entry Type -->
      <!-- Single Edit: Entry Type DropdownMenu -->
      <Field v-if="isSingle" :data-invalid="!!errors.entry_type || undefined">
        <FieldLabel>
          <span>{{ t('inventory.entryType') }}<span class="text-rose-500">*</span></span>
        </FieldLabel>
        <FieldContent>
          <DropdownMenu>
            <DropdownMenuTrigger asChild>
              <Button
                variant="outline"
                class="w-full justify-between rounded-[14px] font-normal h-10 px-4"
                :class="[errors.entry_type ? 'border-destructive' : '']"
              >
                {{ form.entry_type === 'Procurement' ? t('inventory.procurement') : t('inventory.grant') }}
                <ChevronDown class="w-4 h-4 opacity-50" />
              </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="start" class="w-(--reka-dropdown-menu-trigger-width) min-w-(--reka-dropdown-menu-trigger-width) rounded-[14px] z-[1001]">
              <DropdownMenuItem @select="onSelectEntryType('Procurement')">{{ t('inventory.procurement') }}</DropdownMenuItem>
              <DropdownMenuItem @select="onSelectEntryType('Hibah')">{{ t('inventory.grant') }}</DropdownMenuItem>
            </DropdownMenuContent>
          </DropdownMenu>
        </FieldContent>
        <FieldError v-if="errors.entry_type">{{ errors.entry_type }}</FieldError>
      </Field>

      <!-- Mass Edit: Entry Type (Disabled) -->
      <Field v-else data-disabled>
        <FieldLabel>{{ t('inventory.entryType') }}</FieldLabel>
        <FieldContent>
          <input
            type="text"
            :value="t('inventory.cannotBeChangedBulk')"
            disabled
            class="w-full px-4 py-2 text-sm border border-input rounded-[14px] bg-muted/30 text-muted-foreground cursor-not-allowed h-10"
          />
        </FieldContent>
      </Field>

      <!-- Procurement: PO Number (Single Edit) -->
      <Field v-if="isSingle && form.entry_type === 'Procurement'" :data-invalid="!!errors.po_number || undefined">
        <FieldLabel for="edit-lot-po-number">
          <span>{{ t('inventory.poNumber') }}<span class="text-rose-500">*</span></span>
        </FieldLabel>
        <FieldContent>
          <input
            id="edit-lot-po-number"
            v-model="form.po_number"
            type="text"
            maxlength="255"
            placeholder="PO-001"
            class="w-full px-4 py-2 border rounded-[14px] bg-background focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-colors h-10 text-sm"
            :class="[errors.po_number ? 'border-destructive focus:ring-destructive/20 focus:border-destructive' : 'border-input']"
          />
        </FieldContent>
        <FieldError v-if="errors.po_number">{{ errors.po_number }}</FieldError>
      </Field>

      <!-- Hibah: Memo Number (Single Edit) -->
      <Field v-else-if="isSingle && form.entry_type === 'Hibah'" :data-invalid="!!errors.memo_number || undefined">
        <FieldLabel for="edit-lot-memo-number">
          <span>{{ t('inventory.memoNumber') }}<span class="text-rose-500">*</span></span>
        </FieldLabel>
        <FieldContent>
          <input
            id="edit-lot-memo-number"
            v-model="form.memo_number"
            type="text"
            maxlength="255"
            placeholder="MEMO-001"
            class="w-full px-4 py-2 border rounded-[14px] bg-background focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-colors h-10 text-sm"
            :class="[errors.memo_number ? 'border-destructive focus:ring-destructive/20 focus:border-destructive' : 'border-input']"
          />
        </FieldContent>
        <FieldError v-if="errors.memo_number">{{ errors.memo_number }}</FieldError>
      </Field>
    </div>

    <!-- Right Column: Date, Documents/Vendor, Organizer -->
    <div class="space-y-6">
      <!-- Purchase Date (Single Edit, Procurement) -->
      <Field v-if="isSingle && form.entry_type === 'Procurement'" :data-invalid="!!errors.purchase_date || undefined">
        <FieldLabel for="edit-lot-purchase-date">
          <span>{{ t('inventory.purchaseDate') }}<span class="text-rose-500">*</span></span>
        </FieldLabel>
        <FieldContent>
          <input
            id="edit-lot-purchase-date"
            v-model="form.purchase_date"
            type="date"
            class="w-full px-4 py-2 border rounded-[14px] bg-background focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-colors h-10 text-sm"
            :class="[errors.purchase_date ? 'border-destructive focus:ring-destructive/20 focus:border-destructive' : 'border-input']"
          />
        </FieldContent>
        <FieldError v-if="errors.purchase_date">{{ errors.purchase_date }}</FieldError>
      </Field>

      <!-- Mass Edit: Purchase Date / Date of Grant -->
      <Field v-else-if="!isSingle" :data-invalid="!!errors.purchase_date || undefined">
        <FieldLabel for="edit-lot-purchase-date">
          <span>{{ t('inventory.purchaseOrGrantDate') }}</span>
        </FieldLabel>
        <FieldContent>
          <input
            id="edit-lot-purchase-date"
            v-model="form.purchase_date"
            type="date"
            class="w-full px-4 py-2 border rounded-[14px] bg-background focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-colors h-10 text-sm"
            :class="[errors.purchase_date ? 'border-destructive focus:ring-destructive/20 focus:border-destructive' : 'border-input']"
          />
        </FieldContent>
        <FieldError v-if="errors.purchase_date">{{ errors.purchase_date }}</FieldError>
      </Field>

      <!-- Date of Grant (Single Edit, Hibah) -->
      <Field v-else-if="isSingle && form.entry_type === 'Hibah'" :data-invalid="!!errors.date_of_grant || undefined">
        <FieldLabel for="edit-lot-date-of-grant">
          <span>{{ t('inventory.dateOfGrant') }}<span class="text-rose-500">*</span></span>
        </FieldLabel>
        <FieldContent>
          <input
            id="edit-lot-date-of-grant"
            v-model="form.date_of_grant"
            type="date"
            class="w-full px-4 py-2 border rounded-[14px] bg-background focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-colors h-10 text-sm"
            :class="[errors.date_of_grant ? 'border-destructive focus:ring-destructive/20 focus:border-destructive' : 'border-input']"
          />
        </FieldContent>
        <FieldError v-if="errors.date_of_grant">{{ errors.date_of_grant }}</FieldError>
      </Field>

      <!-- Memo Document File Upload (Single Edit, Hibah) -->
      <Field v-if="isSingle && form.entry_type === 'Hibah'" :data-invalid="!!errors.memo_file || undefined">
        <FieldLabel>
          <span>{{ t('inventory.memoDocument') }}</span>
        </FieldLabel>
        <FieldContent>
          <div class="space-y-1">
            <div class="flex items-center gap-2">
              <div
                class="flex-1 px-3 py-2 text-sm border rounded-[10px] bg-muted/40 truncate h-10 flex items-center"
                :class="[
                  hasMemoFile
                    ? 'cursor-pointer hover:bg-muted/60 hover:text-primary transition-colors text-foreground font-medium underline decoration-dotted'
                    : 'text-muted-foreground cursor-default'
                ]"
                @click="hasMemoFile && viewMemoFileInNewTab()"
              >
                {{ form.memo_file_name || t('inventory.noFileSelected') }}
              </div>
              <input
                ref="memoFileInputRef"
                type="file"
                class="hidden"
                accept=".pdf,.jpg,.jpeg,.png,.webp"
                @change="handleMemoFileUpload"
              />
              <Button
                type="button"
                size="lg"
                class="shrink-0"
                @click="triggerMemoFileInput"
              >
                {{ t('inventory.chooseFile') }}
              </Button>
              <Button
                type="button"
                variant="destructive"
                size="icon"
                class="w-9 shrink-0"
                :disabled="!hasMemoFile"
                @click="handleDeleteMemoFile"
              >
                <Trash2 class="w-4 h-4" />
              </Button>
            </div>
            <p class="text-[10px] text-muted-foreground ml-1">{{ t('inventory.maxFileSize2Mb') }}</p>
          </div>
        </FieldContent>
        <FieldError v-if="errors.memo_file">{{ errors.memo_file }}</FieldError>
      </Field>

      <!-- Vendor (Only if Procurement or mass edit) -->
      <Field
        v-if="!isSingle || form.entry_type === 'Procurement'"
        :data-invalid="(isSingle && !!errors.vendor_id) || undefined"
      >
        <FieldLabel for="edit-lot-vendor">
          <span>{{ t('inventory.vendor') }}<span v-if="isSingle && !isBeragamLot" class="text-rose-500">*</span></span>
        </FieldLabel>
        <FieldContent>
          <Combobox
            id="edit-lot-vendor"
            v-model="form.vendor_id"
            :options="filteredVendors"
            :disabled="isBeragamLot"
            :search-placeholder="t('inventory.searchVendorPlaceholder')"
            :default-label="isBeragamLot ? (t('inventory.vendorMultiple') || 'Beragam') : (isSingle ? t('inventory.selectVendor') : t('inventory.unchanged'))"
            width-class="w-full h-10 px-4"
            :error="isSingle && !!errors.vendor_id"
          />
        </FieldContent>
        <FieldError v-if="isSingle && errors.vendor_id">{{ errors.vendor_id }}</FieldError>
      </Field>

      <!-- Organizer -->
      <Field :data-invalid="(isSingle && !!errors.organizer_id) || undefined">
        <FieldLabel>
          <span>{{ t('inventory.organizer') }}<span v-if="isSingle" class="text-rose-500">*</span></span>
        </FieldLabel>
        <FieldContent>
          <DropdownMenu>
            <DropdownMenuTrigger asChild>
              <Button
                variant="outline"
                :class="['w-full justify-between rounded-[14px] font-normal h-10 px-4', !form.organizer_id ? 'text-muted-foreground' : 'text-foreground', (isSingle && errors.organizer_id) ? 'border-destructive' : '']"
              >
                {{ organizers.find(o => o.id == form.organizer_id)?.name || (isSingle ? t('inventory.selectOrganizer') : t('inventory.unchanged')) }}
                <ChevronDown class="w-4 h-4 opacity-50" />
              </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="start" class="w-(--reka-dropdown-menu-trigger-width) min-w-(--reka-dropdown-menu-trigger-width) rounded-[14px] z-[1001]">
              <DropdownMenuItem v-if="!isSingle" @select="form.organizer_id = ''">{{ t('inventory.unchanged') }}</DropdownMenuItem>
              <DropdownMenuItem v-for="org in organizers" :key="org.id" @select="form.organizer_id = org.id">{{ org.name }}</DropdownMenuItem>
            </DropdownMenuContent>
          </DropdownMenu>
        </FieldContent>
        <FieldError v-if="isSingle && errors.organizer_id">{{ errors.organizer_id }}</FieldError>
      </Field>
    </div>
  </div>
</template>
