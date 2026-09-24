<script setup lang="ts">
/**
 * User Management Tab component for Access Management.
 * Manages user-role assignments, search, role filtering, pagination, and HRIS sync.
 */
import { ref, computed, h } from 'vue';
import { useI18n } from 'vue-i18n';
import { ChevronDown, ArrowUpDown, RefreshCw } from 'lucide-vue-next';
import { Button } from '@/Components/ui/button';
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from '@/Components/ui/dropdown-menu';
import Heading from '@/Components/Heading.vue';
import TableSearch from '@/Components/TableSearch.vue';
import DataTable from '@/Components/DataTable.vue';
import UserRoleSelect from '@/Components/UserRoleSelect.vue';
import type { ColumnDef } from '@tanstack/vue-table';

interface UserItem {
  id: number;
  employee_id: string;
  employee_name: string;
  display_name: string;
  role: string;
  roles?: string[];
}

interface RoleItem {
  id: number;
  name: string;
  label?: string;
  description?: string;
  permissions: string[];
  users_count: number;
}

const props = withDefaults(defineProps<{
  users: UserItem[];
  roles: RoleItem[];
  updatingUserId?: number | null;
  isSyncing?: boolean;
}>(), {
  users: () => [],
  roles: () => [],
  updatingUserId: null,
  isSyncing: false,
});

const emit = defineEmits<{
  (e: 'change-role', userId: number, roleName: string): void;
  (e: 'sync-managers'): void;
}>();

const { t, te } = useI18n();

const getRoleDisplay = (role: RoleItem | string) => {
  const roleName = typeof role === 'string' ? role : role.name;
  const roleLabel = typeof role === 'string' ? undefined : role.label;
  const i18nKey = `access.roles.names.${roleName.toLowerCase()}`;
  if (te(i18nKey)) {
    return t(i18nKey);
  }
  return roleLabel || roleName.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
};

const userSearchQuery = ref('');
const selectedRoleFilter = ref<string>('all');
const userRowsPerPage = ref<string>('50');

const userPageSize = computed(() => {
  if (userRowsPerPage.value === 'all') {
    return filteredUsers.value.length || 50;
  }
  return parseInt(userRowsPerPage.value, 10) || 50;
});

const filteredUsers = computed(() => {
  if (!selectedRoleFilter.value || selectedRoleFilter.value === 'all') {
    return props.users;
  }
  const filterKey = selectedRoleFilter.value.toLowerCase();
  return props.users.filter(u => {
    if ((u.role || '').toLowerCase() === filterKey) return true;
    if (u.roles && u.roles.some(r => r.toLowerCase() === filterKey)) return true;
    return false;
  });
});

const userColumns = computed<ColumnDef<UserItem>[]>(() => [
  {
    accessorKey: 'display_name',
    header: ({ column }) => {
      return h(
        Button,
        {
          variant: 'ghost',
          onClick: () => column.toggleSorting(column.getIsSorted() === 'asc'),
          class: 'px-2 hover:bg-transparent font-semibold text-foreground justify-start',
        },
        () => [
          t('access.users.userColumn'),
          h(ArrowUpDown, { class: 'ml-2 h-4 w-4 text-muted-foreground' }),
        ]
      );
    },
    cell: ({ row }) => {
      return h('div', { class: 'pl-2 text-foreground font-medium text-sm' }, row.original.display_name);
    },
  },
  {
    id: 'role',
    header: () => h('div', { class: 'font-semibold text-foreground' }, t('access.users.roleColumn')),
    cell: ({ row }) => {
      return h(UserRoleSelect, {
        user: row.original,
        roles: props.roles,
        disabled: props.updatingUserId === row.original.id,
        onChange: (roleName: string) => emit('change-role', row.original.id, roleName),
      });
    },
  },
]);
</script>

<template>
  <div class="px-4 bg-card rounded-xl border border-border shadow-sm overflow-hidden">
    <div class="py-3">
      <Heading as="h2">{{ $t('access.users.title') }}</Heading>

      <div class="mt-4 flex flex-col sm:flex-row sm:items-end justify-between gap-3">
        <!-- Search & Filter on Left -->
        <div class="flex items-end gap-3 w-full max-w-xl">
          <div class="space-y-1.5 flex-1 max-w-xs">
            <label class="text-xs text-muted-foreground font-medium block">{{ $t('access.users.searchLabel') }}</label>
            <TableSearch
              v-model="userSearchQuery"
              :placeholder="$t('access.users.searchPlaceholder')"
            />
          </div>

          <!-- Filter by Role -->
          <div class="flex-1 w-[240px] space-y-1.5">
            <label class="text-xs text-muted-foreground font-medium block">{{ $t('access.users.filterRoleLabel') }}</label>
            <DropdownMenu>
              <DropdownMenuTrigger asChild>
                <Button
                  variant="outline"
                  class="w-full justify-between rounded-[14px] font-normal"
                  :class="selectedRoleFilter === 'all' ? 'text-muted-foreground' : 'text-foreground'"
                >
                  <span class="truncate capitalize">
                    {{ selectedRoleFilter === 'all' ? $t('access.users.allRoles') : getRoleDisplay(props.roles.find(r => r.name === selectedRoleFilter) || selectedRoleFilter) }}
                  </span>
                  <ChevronDown class="w-4 h-4 opacity-50 shrink-0" />
                </Button>
              </DropdownMenuTrigger>
              <DropdownMenuContent class="w-(--reka-dropdown-menu-trigger-width) min-w-(--reka-dropdown-menu-trigger-width) rounded-[14px] z-[1001]">
                <DropdownMenuItem @select="selectedRoleFilter = 'all'">
                  {{ $t('access.users.allRoles') }}
                </DropdownMenuItem>
                <DropdownMenuItem
                  v-for="role in props.roles"
                  :key="role.id"
                  @select="selectedRoleFilter = role.name"
                  class="capitalize"
                >
                  {{ getRoleDisplay(role) }}
                </DropdownMenuItem>
              </DropdownMenuContent>
            </DropdownMenu>
          </div>
        </div>

        <!-- Rows per page & Action Button on Right -->
        <div class="flex flex-wrap items-center justify-end gap-3 w-full sm:w-auto sm:ml-auto">
          <div class="flex items-center gap-2 text-sm text-muted-foreground">
            <span class="text-right">{{ $t('access.users.rowsPerPage') }}</span>
            <DropdownMenu>
              <DropdownMenuTrigger asChild>
                <Button variant="outline" class="w-[120px] justify-between rounded-[14px] font-normal">
                  {{ userRowsPerPage === 'all' ? $t('access.users.allRows') : userRowsPerPage }}
                  <ChevronDown class="w-4 h-4 opacity-50 shrink-0" />
                </Button>
              </DropdownMenuTrigger>
              <DropdownMenuContent class="w-(--reka-dropdown-menu-trigger-width) min-w-(--reka-dropdown-menu-trigger-width) rounded-[14px]">
                <DropdownMenuItem @select="userRowsPerPage = 'all'">{{ $t('access.users.allRows') }}</DropdownMenuItem>
                <DropdownMenuItem @select="userRowsPerPage = '10'">10</DropdownMenuItem>
                <DropdownMenuItem @select="userRowsPerPage = '25'">25</DropdownMenuItem>
                <DropdownMenuItem @select="userRowsPerPage = '50'">50</DropdownMenuItem>
              </DropdownMenuContent>
            </DropdownMenu>
          </div>

          <!-- Action button: Sync from USER_HRIS -->
          <Button
            @click="emit('sync-managers')"
            variant="primary"
            :disabled="props.isSyncing"
          >
            <RefreshCw class="w-4 h-4 mr-1.5" :class="{ 'animate-spin': props.isSyncing }" />
            <span>{{ props.isSyncing ? $t('access.users.syncing') : $t('access.users.syncHris') }}</span>
          </Button>
        </div>
      </div>
    </div>

    <!-- DataTable -->
    <div class="pb-5">
      <DataTable
        :columns="userColumns"
        :data="filteredUsers"
        :filter-value="userSearchQuery"
        :page-size="userPageSize"
        :show-selection-count="false"
        :default-sorting="[{ id: 'display_name', desc: false }]"
      />
    </div>
  </div>
</template>
