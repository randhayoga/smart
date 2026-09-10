<script setup lang="ts">
/**
 * Hierarchical Location Combobox component.
 * Displays self-referencing locations with depth indentation,
 * collapsible/expandable branches (default expanded), and full path display.
 */
import { ref, computed, watch } from 'vue';
import { Check, ChevronsUpDown, ChevronDown, ChevronRight, X } from 'lucide-vue-next';
import { Button } from '@/Components/ui/button';
import { Popover, PopoverContent, PopoverTrigger } from '@/Components/ui/popover';
import {
  Command,
  CommandEmpty,
  CommandInput,
  CommandList,
} from '@/Components/ui/command';

export interface LocationItem {
  id: number | string;
  name: string;
  parent_id?: number | string | null;
  is_active?: boolean;
  full_name?: string;
  children?: LocationItem[];
  [key: string]: any;
}

const props = withDefaults(defineProps<{
  modelValue: string | number | null;
  locations: LocationItem[];
  placeholder?: string;
  searchPlaceholder?: string;
  emptyText?: string;
  defaultLabel?: string;
  widthClass?: string;
  disabled?: boolean;
  error?: boolean;
  clearable?: boolean;
  excludeIds?: (number | string)[];
  activeOnly?: boolean;
}>(), {
  placeholder: 'Pilih Lokasi...',
  searchPlaceholder: 'Cari lokasi...',
  emptyText: 'Lokasi tidak ditemukan.',
  defaultLabel: '',
  widthClass: 'w-full',
  disabled: false,
  error: false,
  clearable: false,
  excludeIds: () => [],
  activeOnly: false,
});

const emit = defineEmits<{
  (e: 'update:modelValue', value: string | number | null): void;
  (e: 'change', location: LocationItem | null): void;
}>();

const open = ref(false);
const searchQuery = ref('');
const collapsedIds = ref<Set<number | string>>(new Set());

// Build lookup map for quickly traversing parent chains
const locationMap = computed(() => {
  const map = new Map<number | string, LocationItem>();
  props.locations.forEach(loc => {
    map.set(loc.id, loc);
  });
  return map;
});

// Helper to compute full path breadcrumb string
function getFullName(loc: LocationItem): string {
  if (loc.full_name) return loc.full_name;
  const chain: string[] = [loc.name];
  let current = loc;
  const visited = new Set<number | string>([loc.id]);
  while (current.parent_id && locationMap.value.has(current.parent_id)) {
    const parent = locationMap.value.get(current.parent_id)!;
    if (visited.has(parent.id)) break; // Prevent cycle loops
    visited.add(parent.id);
    chain.unshift(parent.name);
    current = parent;
  }
  return chain.join(', ');
}

// Map of id -> full name string
const fullNameMap = computed(() => {
  const map = new Map<number | string, string>();
  props.locations.forEach(loc => {
    map.set(loc.id, getFullName(loc));
  });
  return map;
});

// Label for currently selected value
const selectedLabel = computed(() => {
  if (props.modelValue === null || props.modelValue === '' || props.modelValue === undefined) {
    return props.defaultLabel || props.placeholder;
  }
  if (fullNameMap.value.has(props.modelValue)) {
    return fullNameMap.value.get(props.modelValue)!;
  }
  const found = props.locations.find(l => String(l.id) === String(props.modelValue));
  return found ? getFullName(found) : (props.defaultLabel || props.placeholder);
});

// Build tree hierarchy
interface TreeNode extends LocationItem {
  depth: number;
  hasChildren: boolean;
  childrenList: TreeNode[];
  fullName: string;
}

const treeHierarchy = computed<TreeNode[]>(() => {
  const excludedSet = new Set(props.excludeIds.map(String));
  
  // Filter active or excluded
  const eligible = props.locations.filter(loc => {
    if (excludedSet.has(String(loc.id))) return false;
    if (props.activeOnly && loc.is_active === false && String(loc.id) !== String(props.modelValue)) {
      return false;
    }
    return true;
  });

  const childrenMap = new Map<string | number, LocationItem[]>();
  eligible.forEach(loc => {
    const pid = loc.parent_id ?? 'root';
    if (!childrenMap.has(pid)) {
      childrenMap.set(pid, []);
    }
    childrenMap.get(pid)!.push(loc);
  });

  // Collect root items (no parent_id or parent is not in eligible)
  const eligibleIds = new Set(eligible.map(e => String(e.id)));
  const roots: LocationItem[] = [];
  eligible.forEach(loc => {
    if (!loc.parent_id || !eligibleIds.has(String(loc.parent_id))) {
      roots.push(loc);
    }
  });

  function buildFromRoots(nodes: LocationItem[], depth: number): TreeNode[] {
    return nodes.map(item => {
      const children = childrenMap.get(item.id) || [];
      const childTreeNodes = buildFromRoots(children, depth + 1);
      return {
        ...item,
        depth,
        hasChildren: childTreeNodes.length > 0,
        childrenList: childTreeNodes,
        fullName: fullNameMap.value.get(item.id) || item.name,
      };
    });
  }

  return buildFromRoots(roots, 0);
});

// Set of node IDs that match the search query (or are descendants/ancestors of matches)
const matchingNodeIds = computed<Set<number | string>>(() => {
  const q = searchQuery.value.trim().toLowerCase();
  if (!q) return new Set();

  const matched = new Set<number | string>();

  function addAllDescendants(node: TreeNode) {
    matched.add(node.id);
    node.childrenList.forEach(child => addAllDescendants(child));
  }

  function searchNode(node: TreeNode): boolean {
    const selfMatch = node.name.toLowerCase().includes(q) || node.fullName.toLowerCase().includes(q);
    let childMatch = false;
    node.childrenList.forEach(child => {
      if (searchNode(child)) {
        childMatch = true;
      }
    });

    if (selfMatch) {
      addAllDescendants(node);
      return true;
    }

    if (childMatch) {
      matched.add(node.id);
      return true;
    }

    return false;
  }

  treeHierarchy.value.forEach(root => searchNode(root));
  return matched;
});

// Flatten tree according to expanded/collapsed state and search filter
const visibleFlattenedNodes = computed<TreeNode[]>(() => {
  const q = searchQuery.value.trim().toLowerCase();
  const matched = matchingNodeIds.value;
  const result: TreeNode[] = [];

  function traverse(nodes: TreeNode[]) {
    nodes.forEach(node => {
      if (q && !matched.has(node.id)) {
        return; // Exclude node if it doesn't match search
      }

      const isCollapsed = collapsedIds.value.has(node.id);
      result.push({
        ...node,
        isCollapsed,
      });

      if (node.hasChildren && !isCollapsed) {
        traverse(node.childrenList);
      }
    });
  }

  traverse(treeHierarchy.value);
  return result;
});

function toggleExpand(id: number | string, e?: Event) {
  if (e) e.stopPropagation();
  const next = new Set(collapsedIds.value);
  if (next.has(id)) {
    next.delete(id);
  } else {
    next.add(id);
  }
  collapsedIds.value = next;
}

watch(searchQuery, (newVal, oldVal) => {
  if (newVal !== oldVal) {
    collapsedIds.value = new Set();
  }
});

function handleSelect(id: number | string | null) {
  emit('update:modelValue', id);
  const found = props.locations.find(l => String(l.id) === String(id));
  emit('change', found || null);
  open.value = false;
}

function handleClear(e: Event) {
  e.stopPropagation();
  emit('update:modelValue', null);
  emit('change', null);
}

// Reset search query when popover closes
watch(open, (isOpen) => {
  if (!isOpen) {
    searchQuery.value = '';
    collapsedIds.value = new Set();
  }
});
</script>

<template>
  <Popover v-model:open="open">
    <PopoverTrigger asChild>
      <Button
        variant="outline"
        role="combobox"
        :aria-expanded="open"
        :disabled="disabled"
        :class="[
          widthClass,
          'justify-between rounded-[14px] font-normal',
          !modelValue && modelValue !== 0 ? 'text-muted-foreground' : 'text-foreground',
          error ? '!border-destructive focus:!ring-destructive/20 focus:!border-destructive' : ''
        ]"
      >
        <span class="truncate">{{ selectedLabel }}</span>
        <div v-if="clearable && (modelValue !== null && modelValue !== '' && modelValue !== undefined)" class="flex items-center gap-1 shrink-0 ml-2">
          <X
            class="h-4 w-4 opacity-50 hover:opacity-100 cursor-pointer"
            @click="handleClear"
          />
          <ChevronsUpDown class="h-4 w-4 opacity-50" />
        </div>
        <ChevronsUpDown v-else class="ml-2 h-4 w-4 shrink-0 opacity-50" />
      </Button>
    </PopoverTrigger>
    <PopoverContent class="w-(--reka-popover-trigger-width) min-w-(--reka-popover-trigger-width) p-0 rounded-[14px] overflow-hidden z-[10000]" align="start">
      <Command :highlight-on-hover="true">
        <CommandInput
          v-model="searchQuery"
          :placeholder="searchPlaceholder"
        />
        <CommandList class="p-1">
          <div v-if="visibleFlattenedNodes.length === 0 && !defaultLabel" class="py-6 text-center text-sm text-muted-foreground">
            {{ emptyText }}
          </div>
          <template v-else>
            <!-- Optional Default / Clear Option -->
            <div
              v-if="defaultLabel"
              class="relative flex cursor-pointer select-none items-center rounded-sm px-2 py-1.5 text-sm outline-none hover:bg-accent hover:text-accent-foreground"
              @click="handleSelect('')"
            >
              <Check :class="['mr-2 h-4 w-4 shrink-0', !modelValue && modelValue !== 0 ? 'opacity-100' : 'opacity-0']" />
              <span class="text-foreground">{{ defaultLabel }}</span>
            </div>

            <!-- Flattened Hierarchy Tree Nodes -->
            <div
              v-for="node in visibleFlattenedNodes"
              :key="node.id"
              class="relative flex cursor-pointer select-none items-center rounded-sm px-2 py-1.5 text-sm outline-none hover:bg-accent hover:text-accent-foreground transition-colors"
              @click="handleSelect(node.id)"
            >
              <!-- Checkmark -->
              <Check
                :class="[
                  'mr-2 h-4 w-4 shrink-0',
                  String(modelValue) === String(node.id) ? 'opacity-100' : 'opacity-0'
                ]"
              />

              <!-- Indentation & Expand/Collapse Toggle -->
              <div class="flex items-center shrink-0" :style="{ paddingLeft: `${node.depth * 16}px` }">
                <button
                  v-if="node.hasChildren"
                  type="button"
                  class="p-0.5 mr-1 rounded hover:bg-muted/80 text-muted-foreground hover:text-foreground transition-colors inline-flex items-center justify-center"
                  title="Buka/Tutup Cabang"
                  @click="toggleExpand(node.id, $event)"
                >
                  <ChevronDown v-if="!collapsedIds.has(node.id)" class="h-3.5 w-3.5" />
                  <ChevronRight v-else class="h-3.5 w-3.5" />
                </button>
                <span v-else class="w-4 mr-1 inline-block" />
              </div>

              <!-- Node Name -->
              <span class="truncate flex-1" :title="node.fullName">
                {{ node.name }}
              </span>

              <!-- Inactive Tag if any -->
              <span v-if="node.is_active === false" class="ml-2 text-[10px] px-1.5 py-0.5 rounded bg-muted text-muted-foreground font-medium shrink-0">
                Nonaktif
              </span>
            </div>
          </template>
        </CommandList>
      </Command>
    </PopoverContent>
  </Popover>
</template>
