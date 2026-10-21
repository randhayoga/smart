<script setup lang="ts">
/**
 * Model selection and creation section of the Input New Stock modal.
 */
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import Combobox from '@/Components/Combobox.vue';
import Checkbox from '@/Components/ui/checkbox/Checkbox.vue';
import { Field, FieldLabel, FieldContent, FieldError } from '@/Components/ui/field';

interface Category {
  id: number;
  code: string;
  name: string;
}

interface TypeItem {
  id: number;
  code: string;
  name: string;
  category_id: number;
  is_consumable: boolean;
}

interface BrandItem {
  id: number;
  name: string;
}

interface UomItem {
  id: number;
  name: string;
}

interface ModelItem {
  id: number;
  number?: string;
  code?: string;
  name: string;
  specification?: string | null;
  brand_id: number;
  type_id?: number;
  subcategory_id?: number;
  category_id?: number;
  is_consumable?: boolean;
}

const props = defineProps<{
  form: any;
  errors: Record<string, string>;
  categories: Category[];
  types: TypeItem[];
  brands: BrandItem[];
  uoms: UomItem[];
  models: ModelItem[];
}>();

const { t } = useI18n();

const selectedCategory = computed(() => {
  return props.categories.find(c => c.id === props.form.category_id);
});

const isCompCategory = computed(() => {
  if (!selectedCategory.value) return false;
  return selectedCategory.value.code === 'COMP' || selectedCategory.value.name?.toLowerCase() === 'computer';
});

const filteredTypes = computed(() => {
  if (!props.form.category_id) return [];
  return props.types.filter(t => t.category_id == props.form.category_id);
});

const selectedType = computed(() => {
  if (props.form.type_id === null || props.form.type_id === undefined) return undefined;
  return props.types.find(t => t.id == props.form.type_id);
});

const isConsumableSelected = computed(() => {
  if (selectedType.value) {
    return Boolean(selectedType.value.is_consumable);
  }
  if (props.form.type_id !== null && props.form.type_id !== undefined) {
    const t = props.types.find(x => x.id == props.form.type_id);
    if (t) return Boolean(t.is_consumable);
  }
  if (props.form.category_id !== null && props.form.category_id !== undefined) {
    const typesInCat = props.types.filter(t => t.category_id == props.form.category_id);
    if (typesInCat.length > 0 && typesInCat.every(t => Boolean(t.is_consumable))) {
      return true;
    }
  }
  if (props.types.length > 0 && props.types.every(t => Boolean(t.is_consumable))) {
    return true;
  }
  return false;
});

const filteredBrands = computed(() => {
  if (!props.form.type_id) return [];
  return props.brands;
});

const filteredExistingModels = computed(() => {
  if (!props.form.type_id || !props.form.brand_id) return [];
  return props.models
    .filter(m => (m.type_id == props.form.type_id || m.subcategory_id == props.form.type_id) && m.brand_id == props.form.brand_id)
    .map(m => {
      const spec = m.specification?.trim();
      const label = spec ? `${m.name} - ${spec}` : m.name;
      return {
        id: m.id,
        name: label,
      };
    });
});

const onCategoryChange = (val: string | number | null) => {
  props.form.category_id = val ? Number(val) : null;
  props.form.type_id = null;
  props.form.brand_id = null;
  props.form.model_id = null;
  props.form.create_new_type = false;
  props.form.model_name = '';
  props.form.model_specification = '';
  props.form.uom_id = null;
  props.form.min_stock_threshold = null;
  props.form.lot_id = null;
  props.form.create_new_lot = false;
  props.form.input_lot = false;
  props.form.input_asset = false;
};

const onTypeChange = (val: string | number | null) => {
  props.form.type_id = val ? Number(val) : null;
  props.form.brand_id = null;
  props.form.model_id = null;
  props.form.min_stock_threshold = null;
  props.form.lot_id = null;
  props.form.create_new_lot = false;
  props.form.input_asset = false;
};

const onBrandChange = (val: string | number | null) => {
  props.form.brand_id = val ? Number(val) : null;
  props.form.model_id = null;
  props.form.lot_id = null;
  props.form.create_new_lot = false;
  props.form.input_asset = false;
};

const onModelChange = (val: string | number | null) => {
  props.form.model_id = val ? Number(val) : null;
  props.form.lot_id = null;
};

const onToggleCreateNewType = (checked: boolean | 'indeterminate' | null | undefined) => {
  props.form.create_new_type = checked === true;
  if (props.form.create_new_type) {
    props.form.model_id = null;
  }
};
</script>

<template>
  <div class="space-y-6">
    <!-- Initial Cascading Comboboxes: Category, Type, Brand, Existing Model -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-x-12 gap-y-6">
      <!-- Category -->
      <Field :data-invalid="!!errors.category_id || undefined">
        <FieldLabel for="category">
          <span>{{ t('inventory.category') }}<span class="text-rose-500">*</span></span>
        </FieldLabel>
        <FieldContent>
          <Combobox
            :model-value="form.category_id"
            :options="categories"
            :placeholder="t('inventory.selectCategory')"
            :error="!!errors.category_id"
            width-class="w-full"
            @update:model-value="onCategoryChange"
          />
        </FieldContent>
        <FieldError v-if="errors.category_id">{{ errors.category_id }}</FieldError>
      </Field>

      <!-- Type -->
      <Field :data-invalid="!!errors.type_id || undefined">
        <FieldLabel for="type">
          <span>{{ t('inventory.subcategory') }}<span class="text-rose-500">*</span></span>
        </FieldLabel>
        <FieldContent>
          <Combobox
            :model-value="form.type_id"
            :options="filteredTypes"
            :placeholder="t('inventory.selectSubcategory')"
            :disabled="!form.category_id"
            :error="!!errors.type_id"
            width-class="w-full"
            @update:model-value="onTypeChange"
          />
        </FieldContent>
        <FieldError v-if="errors.type_id">{{ errors.type_id }}</FieldError>
      </Field>

      <!-- Brand -->
      <Field :data-invalid="!!errors.brand_id || undefined">
        <FieldLabel for="brand">
          <span>{{ t('inventory.brand') }}<span class="text-rose-500">*</span></span>
        </FieldLabel>
        <FieldContent>
          <Combobox
            :model-value="form.brand_id"
            :options="filteredBrands"
            :placeholder="t('inventory.selectBrand')"
            :disabled="!form.category_id || !form.type_id"
            :error="!!errors.brand_id"
            width-class="w-full"
            @update:model-value="onBrandChange"
          />
        </FieldContent>
        <FieldError v-if="errors.brand_id">{{ errors.brand_id }}</FieldError>
      </Field>

      <!-- Choose existing model -->
      <Field :data-invalid="(!form.create_new_type && !!errors.model_id) || undefined">
        <FieldLabel for="choose-existing-model">
          <span>{{ t('inventory.chooseExistingModel') }}<span v-if="!form.create_new_type" class="text-rose-500">*</span></span>
        </FieldLabel>
        <FieldContent>
          <Combobox
            :model-value="form.model_id"
            :options="filteredExistingModels"
            :placeholder="t('inventory.chooseExistingModel')"
            :disabled="form.create_new_type || !form.type_id || !form.brand_id"
            :error="!form.create_new_type && !!errors.model_id"
            width-class="w-full"
            @update:model-value="onModelChange"
          />
        </FieldContent>
        <FieldError v-if="!form.create_new_type && errors.model_id">{{ errors.model_id }}</FieldError>
      </Field>
    </div>

    <!-- Create new type checkmark -->
    <div class="flex items-center space-x-2 pt-1 pb-1">
      <Checkbox
        id="create-new-type-check"
        :model-value="form.create_new_type"
        @update:model-value="onToggleCreateNewType"
      />
      <label
        for="create-new-type-check"
        class="text-sm font-semibold text-foreground cursor-pointer select-none leading-none peer-disabled:cursor-not-allowed peer-disabled:opacity-70"
      >
        {{ t('inventory.createNewModelWithData') }}
      </label>
    </div>

    <!-- Dynamic Form when "Create new type with these data above" is checked -->
    <div
      v-if="form.create_new_type"
      class="p-4 rounded-[14px] bg-muted/20 border border-border/80 space-y-6"
    >
      <div class="grid grid-cols-1 md:grid-cols-2 gap-x-12 gap-y-6">
        <!-- Left column -->
        <div class="space-y-6">
          <!-- Model Name -->
          <Field :data-invalid="!!errors.model_name || undefined">
            <FieldLabel for="new-model-name">
              <span>{{ t('inventory.modelName') }}<span class="text-rose-500">*</span></span>
            </FieldLabel>
            <FieldContent>
              <input
                id="new-model-name"
                v-model="form.model_name"
                type="text"
                maxlength="255"
                :placeholder="t('inventory.modelNamePlaceholder')"
                class="w-full px-4 py-2 border rounded-[14px] bg-background focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-colors h-10 text-sm"
                :class="[errors.model_name ? 'border-destructive focus:ring-destructive/20 focus:border-destructive' : 'border-input']"
              />
            </FieldContent>
            <FieldError v-if="errors.model_name">{{ errors.model_name }}</FieldError>
          </Field>

          <!-- Specification (longtext) - if category is not COMP -->
          <Field v-if="!isCompCategory" :data-invalid="!!errors.model_specification || undefined">
            <FieldLabel for="new-model-specification">
              <span>{{ t('inventory.specification') }}<span class="text-rose-500">*</span></span>
            </FieldLabel>
            <FieldContent>
              <textarea
                id="new-model-specification"
                v-model="form.model_specification"
                rows="3"
                maxlength="1000"
                :placeholder="t('inventory.specificationPlaceholder')"
                class="w-full px-4 py-2 border rounded-[14px] bg-background focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-colors text-sm resize-none"
                :class="[errors.model_specification ? 'border-destructive focus:ring-destructive/20 focus:border-destructive' : 'border-input']"
              />
            </FieldContent>
            <FieldError v-if="errors.model_specification">{{ errors.model_specification }}</FieldError>
          </Field>
        </div>

        <!-- Right column -->
        <div class="space-y-6">
          <!-- UoM / Satuan -->
          <Field :data-invalid="!!errors.uom_id || undefined">
            <FieldLabel for="new-model-uom">
              <span>{{ t('inventory.uom') }}<span class="text-rose-500">*</span></span>
            </FieldLabel>
            <FieldContent>
              <Combobox
                :model-value="form.uom_id"
                :options="uoms"
                :placeholder="t('inventory.selectUom')"
                :error="!!errors.uom_id"
                width-class="w-full"
                @update:model-value="(val) => { form.uom_id = val ? Number(val) : null; }"
              />
            </FieldContent>
            <FieldError v-if="errors.uom_id">{{ errors.uom_id }}</FieldError>
          </Field>

          <!-- Minimum stock threshold (if type is consumables) -->
          <Field v-if="isConsumableSelected" :data-invalid="!!errors.min_stock_threshold || undefined">
            <FieldLabel for="new-model-min-stock">
              <span>{{ t('inventory.minStockThreshold') }}<span class="text-rose-500">*</span></span>
            </FieldLabel>
            <FieldContent>
              <input
                id="new-model-min-stock"
                v-model="form.min_stock_threshold"
                type="number"
                min="0"
                step="1"
                placeholder="0"
                class="w-full px-4 py-2 border rounded-[14px] bg-background focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-colors h-10 text-sm"
                :class="[errors.min_stock_threshold ? 'border-destructive focus:ring-destructive/20 focus:border-destructive' : 'border-input']"
              />
            </FieldContent>
            <FieldError v-if="errors.min_stock_threshold">{{ errors.min_stock_threshold }}</FieldError>
          </Field>
        </div>
      </div>
    </div>
  </div>
</template>
