<script setup lang="ts">
/**
 * Consumable section for EditLotModal handling Total Stock, Unit Price, Location, Burden, Project, and Photo.
 * Matches InputNewStock LotSection.vue styling and layout.
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
import LocationCombobox from '@/Components/LocationCombobox.vue';
import CurrencyInput from '@/Components/CurrencyInput.vue';
import { Field, FieldLabel, FieldContent, FieldError } from '@/Components/ui/field';
import { compressImageIfNeeded } from '@/utils/imageCompressor';
import { toast } from 'vue-sonner';

interface Props {
  form: any;
  errors: Record<string, string>;
  isSingle: boolean;
  selectedItem?: any;
  locations: any[];
  projects: any[];
  lotUom?: string;
  isImageDeleted: boolean;
}

const props = defineProps<Props>();

const emit = defineEmits<{
  (e: 'update:isImageDeleted', value: boolean): void;
}>();

const { t } = useI18n();

const projectOptions = computed(() => {
  return (props.projects || [])
    .filter(p => Boolean((p.no_project || p.project_no)?.trim() && p.project_name?.trim()))
    .map(p => ({
      id: p.id_project || p.id,
      name: `[${p.no_project || p.project_no}] ${p.project_name}`
    }));
});

const hasImage = computed(() => {
  if (!props.isSingle) return Boolean(props.form.image_url);
  if (props.form.image_url) return true;
  if (props.isImageDeleted) return false;
  return Boolean(props.selectedItem?.imageUrl || props.selectedItem?.image_url);
});

const onSelectBurden = (burden: string) => {
  props.form.burden = burden;
  if (burden !== 'Project') {
    props.form.project_id = '';
  }
};

const triggerFileInput = () => {
  const input = document.getElementById('edit-lot-photo-upload') as HTMLInputElement;
  input?.click();
};

const handleFileUpload = async (e: any) => {
  const file = e.target.files?.[0];
  if (!file) return;

  const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
  if (!allowedTypes.includes(file.type)) {
    toast.error(t('inventory.invalidFileFormat'));
    return;
  }
  if (file.size > 1024 * 1024) {
    toast.error(t('inventory.fileTooLarge1Mb'));
    return;
  }

  const processed = await compressImageIfNeeded(file);
  emit('update:isImageDeleted', false);
  props.form.image_url = processed;
  props.form.image_url_name = processed.name;
  props.form.delete_image = false;
};

const handleDeletePhoto = () => {
  emit('update:isImageDeleted', true);
  props.form.image_url = null;
  props.form.image_url_name = '';
  props.form.delete_image = true;
  const input = document.getElementById('edit-lot-photo-upload') as HTMLInputElement;
  if (input) input.value = '';
};

const viewImageInNewTab = () => {
  if (props.form.image_url) {
    window.open(URL.createObjectURL(props.form.image_url), '_blank');
  } else if (!props.isImageDeleted && props.isSingle && props.selectedItem) {
    const imageUrl = props.selectedItem.imageUrl || props.selectedItem.image_url;
    if (imageUrl) window.open('/media/' + imageUrl, '_blank');
  }
};
</script>

<template>
  <div class="grid grid-cols-1 md:grid-cols-2 gap-x-12 gap-y-6 pt-4 border-t border-border">
    <!-- Left Column: Total Stock, Location, Unit Price -->
    <div class="space-y-6">
      <!-- Total Stock (Single Edit, Disabled) -->
      <Field v-if="isSingle" data-disabled>
        <FieldLabel for="edit-lot-total-stock">
          <span>{{ t('inventory.totalStock') }}</span>
        </FieldLabel>
        <FieldContent>
          <div class="flex w-full rounded-[14px] border border-input bg-muted/30 text-muted-foreground h-10 overflow-hidden">
            <input
              id="edit-lot-total-stock"
              type="text"
              :value="selectedItem?.initial_quantity ?? selectedItem?.initialQuantity ?? t('inventory.cannotBeChanged')"
              disabled
              class="flex-1 min-w-0 px-4 py-2 text-sm bg-transparent border-0 focus:outline-none focus:ring-0 cursor-not-allowed h-full text-muted-foreground"
            />
            <span v-if="lotUom" class="inline-flex items-center px-3 bg-muted/20 text-muted-foreground text-sm border-l border-input select-none font-medium">
              {{ lotUom }}
            </span>
          </div>
        </FieldContent>
      </Field>

      <!-- Location (Single Edit) -->
      <Field v-if="isSingle" :data-invalid="!!errors.location_id || undefined">
        <FieldLabel>
          <span>{{ t('inventory.location') }}<span class="text-rose-500">*</span></span>
        </FieldLabel>
        <FieldContent>
          <LocationCombobox
            v-model="form.location_id"
            :locations="locations"
            :placeholder="t('inventory.selectLocation')"
            :error="!!errors.location_id"
            :active-only="true"
            :clearable="false"
          />
        </FieldContent>
        <FieldError v-if="errors.location_id">{{ errors.location_id }}</FieldError>
      </Field>

      <!-- Location (Mass Edit) -->
      <Field v-else :data-invalid="!!errors.location_id || undefined">
        <FieldLabel>
          <span>{{ t('inventory.location') }}</span>
        </FieldLabel>
        <FieldContent>
          <LocationCombobox
            v-model="form.location_id"
            :locations="locations"
            :placeholder="t('inventory.unchanged')"
            :error="!!errors.location_id"
            :active-only="true"
            :clearable="true"
          />
        </FieldContent>
        <FieldError v-if="errors.location_id">{{ errors.location_id }}</FieldError>
      </Field>

      <!-- Unit Price (Single Edit) -->
      <Field v-if="isSingle" :data-invalid="!!errors.unit_price || undefined">
        <FieldLabel for="edit-lot-unit-price">
          <span>{{ t('inventory.unitPrice') }}</span>
        </FieldLabel>
        <FieldContent>
          <CurrencyInput
            id="edit-lot-unit-price"
            v-model="form.unit_price"
            :error="!!errors.unit_price"
            :placeholder="t('inventory.unitPricePlaceholder')"
          />
        </FieldContent>
        <FieldError v-if="errors.unit_price">{{ errors.unit_price }}</FieldError>
      </Field>

      <!-- Unit Price (Mass Edit) -->
      <Field v-else :data-invalid="!!errors.unit_price || undefined">
        <FieldLabel for="edit-lot-unit-price-bulk">
          <span>{{ t('inventory.unitPrice') }}</span>
        </FieldLabel>
        <FieldContent>
          <CurrencyInput
            id="edit-lot-unit-price-bulk"
            v-model="form.unit_price"
            :error="!!errors.unit_price"
            :placeholder="t('inventory.unchanged')"
          />
        </FieldContent>
        <FieldError v-if="errors.unit_price">{{ errors.unit_price }}</FieldError>
      </Field>
    </div>

    <!-- Right Column: Burden, Project, Photo -->
    <div class="space-y-6">
      <!-- Burden -->
      <Field :data-invalid="(isSingle && !!errors.burden) || undefined">
        <FieldLabel>
          <span>{{ t('inventory.burden') }}<span v-if="isSingle" class="text-rose-500">*</span></span>
        </FieldLabel>
        <FieldContent>
          <DropdownMenu>
            <DropdownMenuTrigger asChild>
              <Button
                variant="outline"
                class="w-full justify-between rounded-[14px] font-normal h-10 px-4"
                :class="[(isSingle && errors.burden) ? 'border-destructive' : '']"
              >
                {{ form.burden === 'Project' ? t('inventory.project') : (form.burden === 'Corporate' ? t('inventory.corporate') : (!isSingle ? t('inventory.unchanged') : t('inventory.corporate'))) }}
                <ChevronDown class="w-4 h-4 opacity-50" />
              </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="start" class="w-(--reka-dropdown-menu-trigger-width) min-w-(--reka-dropdown-menu-trigger-width) rounded-[14px] z-[1001]">
              <DropdownMenuItem v-if="!isSingle" @select="onSelectBurden('Tidak berubah')">{{ t('inventory.unchanged') }}</DropdownMenuItem>
              <DropdownMenuItem @select="onSelectBurden('Corporate')">{{ t('inventory.corporate') }}</DropdownMenuItem>
              <DropdownMenuItem @select="onSelectBurden('Project')">{{ t('inventory.project') }}</DropdownMenuItem>
            </DropdownMenuContent>
          </DropdownMenu>
        </FieldContent>
        <FieldError v-if="isSingle && errors.burden">{{ errors.burden }}</FieldError>
      </Field>

      <!-- Project Combobox (if Burden = Project) -->
      <Field v-if="form.burden === 'Project'" :data-invalid="!!errors.project_id || undefined">
        <FieldLabel for="edit-lot-project">
          <span>{{ t('inventory.project') }}<span class="text-rose-500">*</span></span>
        </FieldLabel>
        <FieldContent>
          <Combobox
            id="edit-lot-project"
            v-model="form.project_id"
            :options="projectOptions"
            :placeholder="t('inventory.selectProject')"
            :search-placeholder="t('inventory.searchProjectPlaceholder')"
            :default-label="t('inventory.selectProject')"
            width-class="w-full h-10 px-4"
            :error="!!errors.project_id"
          />
        </FieldContent>
        <FieldError v-if="errors.project_id">{{ errors.project_id }}</FieldError>
      </Field>

      <!-- Photo LOT -->
      <Field :data-invalid="!!errors.image_url || undefined">
        <FieldLabel>
          <span>{{ t('inventory.photo') }}</span>
        </FieldLabel>
        <FieldContent>
          <div class="space-y-1">
            <div class="flex items-center gap-2">
              <div
                class="flex-1 px-3 py-2 text-sm border rounded-[10px] bg-muted/40 truncate h-10 flex items-center"
                :class="[
                  hasImage
                    ? 'cursor-pointer hover:bg-muted/60 hover:text-primary transition-colors text-foreground font-medium underline decoration-dotted'
                    : 'text-muted-foreground cursor-default'
                ]"
                @click="hasImage && viewImageInNewTab()"
              >
                {{ form.image_url_name || (isSingle ? t('inventory.noPhotoSelected') : t('inventory.unchanged')) }}
              </div>
              <input
                id="edit-lot-photo-upload"
                type="file"
                class="hidden"
                accept=".jpg,.jpeg,.png,.webp"
                @change="handleFileUpload"
              />
              <Button
                type="button"
                size="lg"
                class="shrink-0"
                @click="triggerFileInput"
              >
                {{ t('inventory.chooseFile') }}
              </Button>
              <Button
                type="button"
                variant="destructive"
                size="icon"
                class="w-9 shrink-0"
                :disabled="!hasImage"
                @click="handleDeletePhoto"
              >
                <Trash2 class="w-4 h-4" />
              </Button>
            </div>
            <p class="text-[10px] text-muted-foreground ml-1">{{ t('inventory.maxFileSize1Mb') }}</p>
          </div>
        </FieldContent>
        <FieldError v-if="errors.image_url">{{ errors.image_url }}</FieldError>
      </Field>
    </div>
  </div>
</template>
