<script setup lang="ts">
/**
 * Filter bar and advanced filters panel for NonConsumableListTab.
 */
import { useI18n } from 'vue-i18n';
import { ChevronDown, SlidersHorizontal } from 'lucide-vue-next';
import { Button } from '@/Components/ui/button';
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from '@/Components/ui/dropdown-menu';
import TableSearch from '@/Components/TableSearch.vue';
import Combobox from '@/Components/Combobox.vue';
import LocationCombobox from '@/Components/LocationCombobox.vue';
import ResetFilterButton from '@/Components/ResetFilterButton.vue';

const props = defineProps<{
  searchQuery: string;
  categoryFilter: string;
  subcategoryFilter: string;
  statusFilter: string;
  conditionFilter: string;
  locationFilter: string;
  brandFilter: string;
  organizerFilter: string;
  vendorFilter: string;
  showAdvancedFilters: boolean;
  hasActiveFilters: boolean;
  availableCategories: string[];
  availableSubcategories: string[];
  availableBrands: string[];
  availableStatuses: string[];
  availableConditions: string[];
  locations: any[];
  organizers?: { id: number; name: string }[];
  vendors?: { id: number; name: string }[];
  filterVariant?: 'simple' | 'full';
  hideStatusFilter?: boolean;
  hideConditionFilter?: boolean;
  isAllSoft: boolean;
  cardless?: boolean;
  getStatusLabel: (status: string) => string;
  getConditionLabel: (cond: string) => string;
}>();

const emit = defineEmits<{
  (e: 'update:searchQuery', val: string): void;
  (e: 'update:categoryFilter', val: string): void;
  (e: 'update:subcategoryFilter', val: string): void;
  (e: 'update:statusFilter', val: string): void;
  (e: 'update:conditionFilter', val: string): void;
  (e: 'update:locationFilter', val: string): void;
  (e: 'update:brandFilter', val: string): void;
  (e: 'update:organizerFilter', val: string): void;
  (e: 'update:vendorFilter', val: string): void;
  (e: 'update:showAdvancedFilters', val: boolean): void;
  (e: 'clear'): void;
}>();

const { t } = useI18n();
</script>

<template>
  <!-- Row 1: Filters -->
  <div class="flex flex-wrap items-end justify-between gap-4">
    <div class="flex flex-wrap items-end gap-3 flex-1">
      <!-- Search -->
      <div class="space-y-1.5 flex-1 min-w-[200px] max-w-xs">
        <label for="search-aset" class="text-xs text-muted-foreground font-medium block">{{ t('inventory.filter') }}</label>
        <TableSearch
          id="search-aset"
          name="search"
          :model-value="searchQuery"
          @update:model-value="emit('update:searchQuery', $event)"
          :placeholder="t('inventory.searchAssetPlaceholder')"
          :bg-class="props.cardless ? 'bg-white' : ''"
        />
      </div>

      <!-- Kategori Filter Dropdown -->
      <DropdownMenu v-if="props.filterVariant !== 'simple'">
        <DropdownMenuTrigger asChild>
          <Button variant="outline" :class="['w-[200px] justify-between rounded-[14px] font-normal', props.cardless ? 'bg-white' : '', !categoryFilter ? 'text-muted-foreground' : 'text-foreground']">
            <span class="truncate">{{ categoryFilter || t('inventory.allCategories') }}</span>
            <ChevronDown class="w-4 h-4 opacity-50 shrink-0" />
          </Button>
        </DropdownMenuTrigger>
        <DropdownMenuContent class="w-[200px] rounded-[14px] max-h-60 overflow-y-auto" align="start" :side-offset="4">
          <DropdownMenuItem @select="emit('update:categoryFilter', '')">{{ t('inventory.allCategories') }}</DropdownMenuItem>
          <DropdownMenuItem v-for="cat in availableCategories" :key="cat" @select="emit('update:categoryFilter', cat)">
            {{ cat }}
          </DropdownMenuItem>
        </DropdownMenuContent>
      </DropdownMenu>

      <!-- Subkategori Filter Combobox -->
      <Combobox
        v-if="props.filterVariant !== 'simple'"
        :model-value="subcategoryFilter"
        @update:model-value="emit('update:subcategoryFilter', String($event ?? ''))"
        :options="availableSubcategories"
        :search-placeholder="t('inventory.searchSubcategoryPlaceholder')"
        :default-label="t('inventory.allSubcategories')"
        :bg-class="props.cardless ? 'bg-white' : ''"
      />

      <!-- Status Filter Dropdown -->
      <DropdownMenu v-if="!props.hideStatusFilter">
        <DropdownMenuTrigger asChild>
          <Button variant="outline" :class="['w-[200px] justify-between rounded-[14px] font-normal', props.cardless ? 'bg-white' : '', !statusFilter ? 'text-muted-foreground' : 'text-foreground']">
            <span class="truncate">{{ statusFilter ? getStatusLabel(statusFilter) : t('inventory.allStatuses') }}</span>
            <ChevronDown class="w-4 h-4 opacity-50 shrink-0" />
          </Button>
        </DropdownMenuTrigger>
        <DropdownMenuContent class="w-[200px] rounded-[14px] max-h-60 overflow-y-auto" align="start" :side-offset="4">
          <DropdownMenuItem @select="emit('update:statusFilter', '')">{{ t('inventory.allStatuses') }}</DropdownMenuItem>
          <DropdownMenuItem v-for="st in availableStatuses" :key="st" @select="emit('update:statusFilter', st)">
            {{ getStatusLabel(st) }}
          </DropdownMenuItem>
        </DropdownMenuContent>
      </DropdownMenu>

      <!-- Condition Filter Dropdown -->
      <DropdownMenu v-if="!props.hideConditionFilter && !isAllSoft">
        <DropdownMenuTrigger asChild>
          <Button variant="outline" :class="['w-[200px] justify-between rounded-[14px] font-normal', props.cardless ? 'bg-white' : '', !conditionFilter ? 'text-muted-foreground' : 'text-foreground']">
            <span class="truncate">{{ conditionFilter ? getConditionLabel(conditionFilter) : t('inventory.allConditions') }}</span>
            <ChevronDown class="w-4 h-4 opacity-50 shrink-0" />
          </Button>
        </DropdownMenuTrigger>
        <DropdownMenuContent class="w-[200px] rounded-[14px] max-h-60 overflow-y-auto" align="start" :side-offset="4">
          <DropdownMenuItem @select="emit('update:conditionFilter', '')">{{ t('inventory.allConditions') }}</DropdownMenuItem>
          <DropdownMenuItem v-for="cond in availableConditions" :key="cond" @select="emit('update:conditionFilter', cond)">
            {{ getConditionLabel(cond) }}
          </DropdownMenuItem>
        </DropdownMenuContent>
      </DropdownMenu>

      <!-- Location Filter -->
      <div v-if="!isAllSoft" class="w-[320px]">
        <LocationCombobox
          :model-value="locationFilter"
          @update:model-value="emit('update:locationFilter', String($event ?? ''))"
          :locations="props.locations"
          :placeholder="t('inventory.allLocations')"
          :clearable="true"
          width-class="w-full"
          :bg-class="props.cardless ? 'bg-white' : ''"
        />
      </div>

      <!-- Advanced Filter Toggle Button -->
      <Button
        v-if="props.filterVariant !== 'simple'"
        variant="outline"
        @click="emit('update:showAdvancedFilters', !showAdvancedFilters)"
        :class="['rounded-[14px] font-normal gap-2', props.cardless && !showAdvancedFilters ? 'bg-white' : '', showAdvancedFilters ? 'bg-muted border-primary/30 text-foreground' : 'text-muted-foreground']"
      >
        <SlidersHorizontal class="w-4 h-4 opacity-70" />
        <span>{{ t('inventory.advancedFilter') }}</span>
        <ChevronDown :class="['w-4 h-4 opacity-50 shrink-0 transition-transform duration-200', showAdvancedFilters ? 'rotate-180' : '']" />
      </Button>

      <!-- Reset Filters Button -->
      <Transition
        enter-active-class="transition ease-out duration-200"
        enter-from-class="transform scale-95 opacity-0"
        enter-to-class="transform scale-100 opacity-100"
        leave-active-class="transition ease-in duration-150"
        leave-from-class="transform scale-100 opacity-100"
        leave-to-class="transform scale-95 opacity-0"
      >
        <ResetFilterButton
          v-if="hasActiveFilters"
          @click="emit('clear')"
        />
      </Transition>
    </div>
  </div>

  <!-- Advanced Filters Row -->
  <Transition
    enter-active-class="transition-all ease-out duration-300 transform"
    enter-from-class="-translate-y-4 opacity-0"
    enter-to-class="translate-y-0 opacity-100"
    leave-active-class="transition-all ease-in duration-200 transform"
    leave-from-class="translate-y-0 opacity-100"
    leave-to-class="-translate-y-4 opacity-0"
  >
    <div
      v-if="showAdvancedFilters && props.filterVariant !== 'simple'"
      class="p-4 bg-muted/30 rounded-xl border border-border/80 flex flex-wrap gap-4"
    >
      <!-- Brand Filter -->
      <div class="space-y-1.5 w-[200px]">
        <label class="text-xs text-muted-foreground font-medium block ml-0.5">{{ t('inventory.brand') }}</label>
        <Combobox
          :model-value="brandFilter"
          @update:model-value="emit('update:brandFilter', String($event ?? ''))"
          :options="availableBrands"
          :search-placeholder="t('inventory.searchBrandPlaceholder')"
          :default-label="t('inventory.allBrands')"
          width-class="w-full"
          :bg-class="props.cardless ? 'bg-white' : 'bg-background'"
        />
      </div>

      <!-- Organizer Filter -->
      <div class="space-y-1.5 w-[170px]">
        <label class="text-xs text-muted-foreground font-medium block ml-0.5">{{ t('inventory.organizer') }}</label>
        <DropdownMenu>
          <DropdownMenuTrigger asChild>
            <Button variant="outline" :class="['w-full justify-between rounded-[14px] font-normal', props.cardless ? 'bg-white' : 'bg-background', !organizerFilter ? 'text-muted-foreground' : 'text-foreground']">
              <span class="truncate">{{ organizerFilter ? (props.organizers?.find(o => o.id.toString() === organizerFilter)?.name || t('inventory.allOrganizers')) : t('inventory.allOrganizers') }}</span>
              <ChevronDown class="w-4 h-4 opacity-50 shrink-0" />
            </Button>
          </DropdownMenuTrigger>
          <DropdownMenuContent class="w-(--reka-dropdown-menu-trigger-width) min-w-(--reka-dropdown-menu-trigger-width) rounded-[14px] max-h-60 overflow-y-auto" align="start" :side-offset="4">
            <DropdownMenuItem @select="emit('update:organizerFilter', '')">{{ t('inventory.allOrganizers') }}</DropdownMenuItem>
            <DropdownMenuItem v-for="org in props.organizers" :key="org.id" @select="emit('update:organizerFilter', org.id.toString())">
              {{ org.name }}
            </DropdownMenuItem>
          </DropdownMenuContent>
        </DropdownMenu>
      </div>

      <!-- Vendor Filter -->
      <div class="space-y-1.5 w-3xs">
        <label class="text-xs text-muted-foreground font-medium block ml-0.5">{{ t('inventory.vendor') }}</label>
        <Combobox
          :model-value="vendorFilter"
          @update:model-value="emit('update:vendorFilter', String($event ?? ''))"
          :options="props.vendors || []"
          :search-placeholder="t('inventory.searchVendorPlaceholder')"
          :default-label="t('inventory.allVendors')"
          width-class="w-full"
          :bg-class="props.cardless ? 'bg-white' : 'bg-background'"
        />
      </div>
    </div>
  </Transition>
</template>
