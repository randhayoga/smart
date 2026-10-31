<script setup lang="ts">
/**
 * Section 2 of InputManualRequestModal: Item / Asset Selection.
 * Allows selecting an in-stock Consumable Model (with combined Amount + UoM input)
 * or an available ST/LT Physical Asset, plus optional per-item reasoning note,
 * and adding it to the request list via the green "+ Add" button.
 */
import { ref, computed, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { Plus } from 'lucide-vue-next';
import { Button } from '@/Components/ui/button';
import { Field, FieldLabel, FieldContent, FieldError } from '@/Components/ui/field';
import Combobox from '@/Components/Combobox.vue';

export interface CatalogModelItem {
  id: number;
  number: string;
  name: string;
  specification: string;
  brand_name: string;
  uom_name: string;
  type_id: number;
  type_name: string;
  category_name: string;
  image_url: string | null;
  available_stock: number;
}

export interface CatalogAssetItem {
  id: number;
  number: string;
  borrowing_type: 'ST' | 'LT';
  condition: string;
  specification: string;
  image_url: string | null;
  location_name: string;
  model_id: number;
  model_number: string;
  model_name: string;
  brand_name: string;
  uom_name: string;
  type_id: number;
  type_name: string;
  category_name: string;
}

export interface AddedManualItem {
  key: string;
  request_type: 'consumable' | 'st' | 'lt' | 'rent';
  model_id: number;
  asset_id: number | null;
  number: string;
  brand_name: string;
  name: string;
  specification: string;
  category_name: string;
  type_name: string;
  uom_name: string;
  location_name?: string;
  condition?: string;
  image_url: string | null;
  available_stock: number;
  quantity: number;
  reasoning: string;
}

const props = defineProps<{
  requestType: 'consumable' | 'st' | 'lt' | 'rent';
  catalogModels: CatalogModelItem[];
  catalogAssets: CatalogAssetItem[];
  addedItems: AddedManualItem[];
  loadingCatalog?: boolean;
  itemsError?: string;
}>();

const emit = defineEmits<{
  (e: 'add-item', item: AddedManualItem): void;
}>();

const { t } = useI18n();

const selectedModelId = ref<number | null>(null);
const selectedAssetId = ref<number | null>(null);
const quantityInput = ref<number | string>(1);
const itemReasoning = ref('');
const localError = ref<string>('');
const attemptedAdd = ref(false);

watch(() => props.requestType, () => {
  selectedModelId.value = null;
  selectedAssetId.value = null;
  quantityInput.value = 1;
  itemReasoning.value = '';
  localError.value = '';
  attemptedAdd.value = false;
});

const addedModelIds = computed(() => {
  return new Set(
    props.addedItems
      .filter(i => i.request_type === 'consumable')
      .map(i => i.model_id)
  );
});

const addedAssetIds = computed(() => {
  return new Set(
    props.addedItems
      .filter(i => i.asset_id !== null)
      .map(i => i.asset_id as number)
  );
});

const modelOptions = computed(() => {
  return props.catalogModels
    .filter(m => (m.available_stock ?? 0) > 0 && !addedModelIds.value.has(m.id))
    .map(m => {
      const code = m.number || (m as any).code || '';
      const brand = m.brand_name || (m as any).brand || '';
      const cat = m.category_name || (m as any).category || '';
      const type = m.type_name || (m as any).type || '';

      const codePart = code ? `[${code}] ` : '';
      const brandPart = brand ? `${brand} ` : '';
      const specPart = m.specification ? ` ${m.specification}` : '';
      const catPart = cat && type ? ` (${cat} - ${type})` : '';
      return {
        id: m.id,
        name: `${codePart}${brandPart}${m.name}${specPart}${catPart}`,
      };
    });
});

const assetOptions = computed(() => {
  return props.catalogAssets
    .filter(a => !addedAssetIds.value.has(a.id))
    .map(a => {
      const number = a.number || (a as any).asset_number || '';
      const brand = a.brand_name || (a as any).brand || '';
      const name = a.model_name || (a as any).name || '';
      const cat = a.category_name || (a as any).category || '';
      const type = a.type_name || (a as any).type || '';
      const loc = a.location_name || (a as any).location || '';

      const numberPart = number ? `[${number}] ` : '';
      const brandPart = brand ? `${brand} ` : '';
      const specPart = a.specification ? ` ${a.specification}` : '';
      const catPart = cat && type ? ` (${cat} - ${type})` : '';
      const locPart = loc && loc !== '-' ? ` — ${loc}` : '';
      return {
        id: a.id,
        name: `${numberPart}${brandPart}${name}${specPart}${catPart}${locPart}`,
      };
    });
});

const selectedModel = computed(() => {
  if (!selectedModelId.value) return null;
  return props.catalogModels.find(m => m.id === selectedModelId.value) || null;
});

const selectedAsset = computed(() => {
  if (!selectedAssetId.value) return null;
  return props.catalogAssets.find(a => a.id === selectedAssetId.value) || null;
});

const hasModelError = computed(() => {
  return attemptedAdd.value && props.requestType === 'consumable' && !selectedModelId.value;
});

const hasAssetError = computed(() => {
  return attemptedAdd.value && props.requestType !== 'consumable' && !selectedAssetId.value;
});

const hasAmountError = computed(() => {
  if (!selectedModel.value) return false;
  const raw = quantityInput.value;
  if (raw === '' || raw === null || raw === undefined) return true;
  const qty = Number(raw);
  return isNaN(qty) || !Number.isInteger(qty) || qty < 1 || qty > (selectedModel.value.available_stock ?? 0);
});

const amountErrorMessage = computed(() => {
  if (!selectedModel.value) return '';
  const raw = quantityInput.value;
  if (raw === '' || raw === null || raw === undefined) {
    return t('fulfillment.manualRequest.quantityMinError');
  }
  const qty = Number(raw);
  if (isNaN(qty) || !Number.isInteger(qty) || qty < 1) {
    return t('fulfillment.manualRequest.quantityMinError');
  }
  const maxStock = selectedModel.value.available_stock ?? 0;
  if (qty > maxStock) {
    return t('fulfillment.manualRequest.quantityExceedsStock', {
      max: maxStock,
      uom: selectedModel.value.uom_name || 'Unit',
    });
  }
  return '';
});

watch(selectedModelId, () => {
  localError.value = '';
  quantityInput.value = 1;
});

watch(selectedAssetId, () => {
  localError.value = '';
});

const handleAdd = () => {
  localError.value = '';
  attemptedAdd.value = true;

  if (props.requestType === 'consumable') {
    if (!selectedModel.value) {
      localError.value = t('fulfillment.manualRequest.selectConsumableModel');
      return;
    }

    const qty = Number(quantityInput.value);
    if (!Number.isInteger(qty) || qty < 1) {
      localError.value = t('fulfillment.manualRequest.quantityMinError');
      return;
    }

    if (qty > selectedModel.value.available_stock) {
      localError.value = t('fulfillment.manualRequest.quantityExceedsStock', {
        max: selectedModel.value.available_stock,
        uom: selectedModel.value.uom_name,
      });
      return;
    }

    const m = selectedModel.value as any;
    const modelNumber = m.number || m.code || '';
    const brandName = m.brand_name || m.brand || '';
    const categoryName = m.category_name || m.category || '';
    const typeName = m.type_name || m.type || '';
    const uomName = m.uom_name || m.uom || 'Unit';

    emit('add-item', {
      key: `consumable-${m.id}`,
      request_type: 'consumable',
      model_id: m.id,
      asset_id: null,
      number: modelNumber,
      brand_name: brandName,
      name: m.name,
      specification: m.specification || '',
      category_name: categoryName,
      type_name: typeName,
      uom_name: uomName,
      image_url: m.image_url,
      available_stock: m.available_stock,
      quantity: qty,
      reasoning: itemReasoning.value.trim(),
    });

    attemptedAdd.value = false;
    selectedModelId.value = null;
    quantityInput.value = 1;
    itemReasoning.value = '';
    return;
  }

  if (!selectedAsset.value) {
    localError.value = t('fulfillment.manualRequest.selectAsset');
    return;
  }

  const a = selectedAsset.value as any;
  const assetNumber = a.number || a.asset_number || '';
  const brandName = a.brand_name || a.brand || '';
  const modelName = a.model_name || a.name || '';
  const categoryName = a.category_name || a.category || '';
  const typeName = a.type_name || a.type || '';
  const uomName = a.uom_name || a.uom || 'Unit';
  const locationName = a.location_name || a.location || '';

  emit('add-item', {
    key: `asset-${a.id}`,
    request_type: props.requestType,
    model_id: a.model_id,
    asset_id: a.id,
    number: assetNumber,
    brand_name: brandName,
    name: modelName,
    specification: a.specification || '',
    category_name: categoryName,
    type_name: typeName,
    uom_name: uomName,
    location_name: locationName,
    condition: a.condition,
    image_url: a.image_url,
    available_stock: 1,
    quantity: 1,
    reasoning: itemReasoning.value.trim(),
  });

  attemptedAdd.value = false;
  selectedAssetId.value = null;
  itemReasoning.value = '';
};
</script>

<template>
  <div>
    <div class="p-4 rounded-[14px] bg-muted/20 border border-border/80 space-y-4">
      <!-- Consumable Model Selection Row -->
      <div
        v-if="requestType === 'consumable'"
        class="flex flex-col lg:flex-row gap-3 items-start"
      >
        <!-- Consumable Model Combobox (Expanded) -->
        <div class="w-full lg:flex-[5] min-w-0">
          <Field :data-invalid="hasModelError || undefined">
            <FieldLabel>
              <span>{{ t('fulfillment.manualRequest.consumableModel') }}<span class="text-rose-500">*</span></span>
            </FieldLabel>
            <FieldContent>
              <Combobox
                :model-value="selectedModelId"
                :options="modelOptions"
                :placeholder="t('fulfillment.manualRequest.selectConsumableModel')"
                :search-placeholder="t('fulfillment.manualRequest.searchConsumableModel')"
                :default-label="t('fulfillment.manualRequest.selectConsumableModel')"
                :disabled="loadingCatalog"
                :error="hasModelError"
                width-class="w-full"
                bg-class="bg-background h-10"
                :limit="50"
                @update:model-value="(val) => { selectedModelId = val ? Number(val) : null; }"
              />
            </FieldContent>
          </Field>
        </div>

        <!-- Combined Amount + UoM Input (Compact ~110px-120px) -->
        <div class="w-full lg:w-28 lg:shrink-0">
          <Field :data-invalid="hasAmountError || undefined">
            <FieldLabel for="manual-request-item-qty">
              <span>{{ t('fulfillment.manualRequest.amount') }}<span class="text-rose-500">*</span></span>
            </FieldLabel>
            <FieldContent>
              <div
                class="flex w-full rounded-[14px] border bg-background focus-within:ring-2 transition-colors h-10 overflow-hidden"
                :class="[hasAmountError ? 'border-destructive focus-within:ring-destructive/20 focus-within:border-destructive' : 'border-input focus-within:ring-primary/20 focus-within:border-primary']"
              >
                <input
                  id="manual-request-item-qty"
                  v-model="quantityInput"
                  type="number"
                  min="1"
                  :max="selectedModel?.available_stock ?? undefined"
                  :disabled="!selectedModel"
                  class="flex-1 min-w-0 px-2.5 py-2 text-sm bg-transparent border-0 focus:outline-none focus:ring-0 transition-colors h-full disabled:cursor-not-allowed disabled:opacity-50 text-center"
                />
                <span
                  v-if="selectedModel?.uom_name"
                  class="inline-flex items-center px-2 bg-muted/20 text-muted-foreground text-xs border-l border-input select-none font-semibold shrink-0"
                >
                  {{ selectedModel.uom_name }}
                </span>
              </div>
              <p v-if="selectedModel && !hasAmountError" class="text-[11px] text-muted-foreground mt-1 ml-1 truncate" :title="`${selectedModel.available_stock} ${selectedModel.uom_name}`">
                {{ t('fulfillment.manualRequest.availableStock') }}:
                <span class="font-semibold text-foreground">{{ selectedModel.available_stock }}</span>
              </p>
            </FieldContent>
            <FieldError v-if="hasAmountError">{{ amountErrorMessage }}</FieldError>
          </Field>
        </div>

        <!-- Item Notes / Reasoning (Expanded) -->
        <div class="w-full lg:flex-[4] min-w-0">
          <Field>
            <FieldLabel for="manual-request-consumable-item-note">
              <span>{{ t('fulfillment.manualRequest.itemNotes') }}</span>
            </FieldLabel>
            <FieldContent>
              <input
                id="manual-request-consumable-item-note"
                v-model="itemReasoning"
                type="text"
                maxlength="1000"
                :placeholder="t('fulfillment.manualRequest.itemNotesPlaceholder')"
                class="w-full px-4 py-2 text-sm border border-input rounded-[14px] bg-background focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-colors h-10"
                @keydown.enter.prevent="handleAdd"
              />
            </FieldContent>
          </Field>
        </div>

        <!-- Green + Add Button (Compact ~95px) -->
        <div class="w-full lg:w-24 lg:shrink-0 lg:pt-7">
          <Button
            type="button"
            variant="success"
            data-testid="add-manual-item-btn"
            class="w-full h-10 font-semibold flex items-center justify-center gap-1.5"
            :disabled="!selectedModelId"
            @click="handleAdd"
          >
            <Plus class="w-4 h-4" />
            <span>{{ t('fulfillment.manualRequest.addButton') }}</span>
          </Button>
        </div>
      </div>

      <!-- ST / LT Physical Asset Selection Row -->
      <div
        v-else
        class="flex flex-col lg:flex-row gap-3 items-start"
      >
        <!-- Physical Asset Combobox (Expanded) -->
        <div class="w-full lg:flex-[6] min-w-0">
          <Field :data-invalid="hasAssetError || undefined">
            <FieldLabel>
              <span>{{ t('fulfillment.manualRequest.assetItem') }}<span class="text-rose-500">*</span></span>
            </FieldLabel>
            <FieldContent>
              <Combobox
                :model-value="selectedAssetId"
                :options="assetOptions"
                :placeholder="t('fulfillment.manualRequest.selectAsset')"
                :search-placeholder="t('fulfillment.manualRequest.searchAsset')"
                :default-label="t('fulfillment.manualRequest.selectAsset')"
                :disabled="loadingCatalog"
                :error="hasAssetError"
                width-class="w-full"
                bg-class="bg-background h-10"
                :limit="50"
                @update:model-value="(val) => { selectedAssetId = val ? Number(val) : null; }"
              />
            </FieldContent>
          </Field>
        </div>

        <!-- Asset Notes / Reasoning (Expanded) -->
        <div class="w-full lg:flex-[5] min-w-0">
          <Field>
            <FieldLabel for="manual-request-asset-item-note">
              <span>{{ t('fulfillment.manualRequest.itemNotes') }}</span>
            </FieldLabel>
            <FieldContent>
              <input
                id="manual-request-asset-item-note"
                v-model="itemReasoning"
                type="text"
                maxlength="1000"
                :placeholder="t('fulfillment.manualRequest.itemNotesPlaceholder')"
                class="w-full px-4 py-2 text-sm border border-input rounded-[14px] bg-background focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-colors h-10"
                @keydown.enter.prevent="handleAdd"
              />
            </FieldContent>
          </Field>
        </div>

        <!-- Green + Add Button (Compact ~95px) -->
        <div class="w-full lg:w-24 lg:shrink-0 lg:pt-7">
          <Button
            type="button"
            variant="success"
            data-testid="add-manual-item-btn"
            class="w-full h-10 font-semibold flex items-center justify-center gap-1.5"
            :disabled="!selectedAssetId"
            @click="handleAdd"
          >
            <Plus class="w-4 h-4" />
            <span>{{ t('fulfillment.manualRequest.addButton') }}</span>
          </Button>
        </div>
      </div>

      <FieldError v-if="localError || itemsError">{{ localError || itemsError }}</FieldError>
    </div>
  </div>
</template>
