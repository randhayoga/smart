<script setup lang="ts">
/**
 * Role Management Tab component for Access Management.
 * Manages roles, fine-grained permission matrix, role creation, editing, and deletion.
 */
import { ref, computed } from 'vue';
import { ChevronDown, Plus, Pencil, Trash2 } from 'lucide-vue-next';
import { Button } from '@/Components/ui/button';
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from '@/Components/ui/dropdown-menu';
import Heading from '@/Components/Heading.vue';

interface RoleItem {
  id: number;
  name: string;
  label?: string;
  description?: string;
  permissions: string[];
  users_count: number;
}

interface PermissionItem {
  id: number;
  name: string;
  label?: string;
  group?: string;
}

const props = withDefaults(defineProps<{
  roles: RoleItem[];
  permissions: PermissionItem[];
  updatingPermissionKey?: string | null;
}>(), {
  roles: () => [],
  permissions: () => [],
  updatingPermissionKey: null,
});

const emit = defineEmits<{
  (e: 'create-role'): void;
  (e: 'edit-role', role: RoleItem): void;
  (e: 'delete-role', role: RoleItem): void;
  (e: 'toggle-permission', roleId: number, permissionName: string, enabled: boolean): void;
}>();

const PROTECTED_ROLES = ['superadmin', 'admin', 'ifs_manager', 'manager', 'user'];

const isProtectedRole = (name: string): boolean => {
  return PROTECTED_ROLES.includes((name || '').toLowerCase());
};

const isDeleteDisabled = (role: RoleItem): boolean => {
  return isProtectedRole(role.name) || role.users_count > 0;
};

const getDeleteDisabledReason = (role: RoleItem): string => {
  if (isProtectedRole(role.name)) {
    return 'System-protected roles cannot be deleted.';
  }
  if (role.users_count > 0) {
    return `Cannot delete role assigned to ${role.users_count} user(s).`;
  }
  return '';
};

const roleRowsPerPage = ref<string>('50');

const groupedPermissions = computed(() => {
  const groups: Record<string, PermissionItem[]> = {};
  for (const perm of props.permissions) {
    const groupName = perm.group || 'general';
    if (!groups[groupName]) {
      groups[groupName] = [];
    }
    groups[groupName].push(perm);
  }
  return groups;
});

const formatGroupName = (group: string) => {
  const map: Record<string, string> = {
    dashboard: 'Dashboard',
    master: 'Master Data',
    inventory: 'Inventory',
    requests: 'Requests',
    audit: 'Audit',
    access: 'Access Control',
  };
  return map[group] || group.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
};
</script>

<template>
  <div class="px-4 bg-card rounded-xl border border-border shadow-sm overflow-hidden">
    <div class="py-3">
      <Heading as="h2">Role Management</Heading>

      <div class="mt-4 flex flex-col sm:flex-row sm:items-end justify-between gap-3">
        <!-- Right Controls: Rows per page & Create Role button -->
        <div class="flex flex-wrap items-center justify-end gap-3 w-full sm:w-auto sm:ml-auto">
          <div class="flex items-center gap-2 text-sm text-muted-foreground">
            <span class="text-right">Rows per page</span>
            <DropdownMenu>
              <DropdownMenuTrigger asChild>
                <Button variant="outline" class="w-[120px] justify-between rounded-[14px] font-normal">
                  {{ roleRowsPerPage === 'all' ? 'All Rows' : roleRowsPerPage }}
                  <ChevronDown class="w-4 h-4 opacity-50 shrink-0" />
                </Button>
              </DropdownMenuTrigger>
              <DropdownMenuContent class="w-(--reka-dropdown-menu-trigger-width) min-w-(--reka-dropdown-menu-trigger-width) rounded-[14px]">
                <DropdownMenuItem @select="roleRowsPerPage = 'all'">All Rows</DropdownMenuItem>
                <DropdownMenuItem @select="roleRowsPerPage = '10'">10</DropdownMenuItem>
                <DropdownMenuItem @select="roleRowsPerPage = '25'">25</DropdownMenuItem>
                <DropdownMenuItem @select="roleRowsPerPage = '50'">50</DropdownMenuItem>
              </DropdownMenuContent>
            </DropdownMenu>
          </div>

          <Button @click="emit('create-role')" variant="primary" class="rounded-[14px]">
            <Plus class="w-4 h-4 mr-1.5" />
            <span>Create New Role</span>
          </Button>
        </div>
      </div>
    </div>

    <!-- Role Permissions Matrix Table with Sticky Left/Right Columns -->
    <div class="pb-5">
      <div class="rounded-xl border border-border shadow-sm overflow-hidden bg-card">
        <div class="relative w-full overflow-x-auto">
          <table class="w-full caption-bottom text-sm border-collapse">
            <thead class="bg-muted/50 border-b border-border select-none">
              <!-- Row 1: Functional Category Groups -->
              <tr class="border-b border-border/60">
                <th
                  rowspan="2"
                  class="sticky left-0 z-20 bg-muted/95 px-4 py-3 text-left font-semibold text-foreground min-w-[220px] border-r border-border shadow-[1px_0_0_0_hsl(var(--border))]"
                >
                  Roles
                </th>
                <th
                  v-for="(perms, groupName) in groupedPermissions"
                  :key="groupName"
                  :colspan="perms.length"
                  class="px-3 py-2 text-center text-xs font-semibold uppercase tracking-wider text-muted-foreground border-r border-border/60 bg-muted/30"
                >
                  <div class="flex items-center justify-center gap-1.5">
                    <span>{{ formatGroupName(groupName as string) }}</span>
                  </div>
                </th>
                <th
                  rowspan="2"
                  class="sticky right-0 z-20 bg-muted/95 px-4 py-3 text-right font-semibold text-foreground min-w-[100px] border-l border-border shadow-[-1px_0_0_0_hsl(var(--border))]"
                >
                  Action
                </th>
              </tr>

              <!-- Row 2: Individual Permission Headers -->
              <tr>
                <th
                  v-for="perm in props.permissions"
                  :key="perm.id"
                  class="px-2 py-2 text-center text-xs font-medium text-foreground min-w-[120px] max-w-[150px] border-r border-border/40 hover:bg-muted/70 transition-colors"
                  :title="perm.name"
                >
                  <div class="leading-tight line-clamp-2" :title="perm.label || perm.name">
                    {{ perm.label || perm.name }}
                  </div>
                </th>
              </tr>
            </thead>

            <tbody class="divide-y divide-border">
              <tr
                v-for="role in props.roles"
                :key="role.id"
                class="hover:bg-muted/30 transition-colors group"
              >
                <!-- Sticky Role Name & Badges -->
                <td class="sticky left-0 z-10 bg-card group-hover:bg-muted/30 px-4 py-3 border-r border-border shadow-[1px_0_0_0_hsl(var(--border))]">
                  <div class="flex items-center gap-2">
                    <span class="font-semibold text-foreground text-sm">
                      {{ role.label || role.name }}
                    </span>
                    <span
                      v-if="isProtectedRole(role.name)"
                      class="text-[10px] uppercase font-bold tracking-wider px-1.5 py-0.5 rounded bg-amber-500/10 text-amber-600 dark:text-amber-400 border border-amber-500/20"
                    >
                      System
                    </span>
                  </div>
                  <div class="text-xs text-muted-foreground flex items-center gap-2 mt-0.5 font-mono">
                    <span>{{ role.name }}</span>
                    <span>•</span>
                    <span class="font-sans text-[11px]">
                      {{ role.users_count }} user{{ role.users_count === 1 ? '' : 's' }}
                    </span>
                  </div>
                </td>

                <!-- Permission Matrix Checkboxes -->
                <td
                  v-for="perm in props.permissions"
                  :key="perm.id"
                  class="px-2 py-3 text-center border-r border-border/40"
                >
                  <div class="flex items-center justify-center">
                    <input
                      type="checkbox"
                      :checked="role.name === 'superadmin' ? true : role.permissions.includes(perm.name)"
                      :disabled="role.name === 'superadmin' || props.updatingPermissionKey === `${role.id}_${perm.name}`"
                      @change="(e) => emit('toggle-permission', role.id, perm.name, (e.target as HTMLInputElement).checked)"
                      class="h-4 w-4 rounded border-input text-primary focus:ring-primary/20 cursor-pointer disabled:opacity-40 disabled:cursor-not-allowed transition-all accent-primary"
                      :title="role.name === 'superadmin' ? 'Superadmin has all permissions by default' : `Toggle ${perm.label || perm.name}`"
                    />
                  </div>
                </td>

                <!-- Sticky Action Column -->
                <td class="sticky right-0 z-10 bg-card group-hover:bg-muted/30 px-4 py-3 text-right border-l border-border shadow-[-1px_0_0_0_hsl(var(--border))]">
                  <div class="flex items-center justify-end gap-1.5">
                    <Button
                      variant="table-edit"
                      size="icon-sm"
                      title="Edit Role"
                      @click="emit('edit-role', role)"
                    >
                      <Pencil class="w-4 h-4" />
                      <span class="sr-only">Edit Role</span>
                    </Button>
                    <Button
                      variant="table-destructive"
                      size="icon-sm"
                      :title="isDeleteDisabled(role) ? getDeleteDisabledReason(role) : 'Delete Role'"
                      :disabled="isDeleteDisabled(role)"
                      :class="{ 'opacity-40 cursor-not-allowed': isDeleteDisabled(role) }"
                      @click="emit('delete-role', role)"
                    >
                      <Trash2 class="w-4 h-4" />
                      <span class="sr-only">Delete Role</span>
                    </Button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</template>
