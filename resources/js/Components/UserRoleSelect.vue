<script setup lang="ts">
/**
 * Role selector dropdown component for User Management.
 * Constrained to only allow selecting Superadmin, Admin, and User roles.
 */
import { computed } from 'vue';
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from "@/Components/ui/dropdown-menu";
import { Button } from "@/Components/ui/button";
import { ChevronDown, Check } from 'lucide-vue-next';

interface RoleOption {
  id: number;
  name: string;
  label?: string;
}

const props = defineProps<{
  user: {
    id: number;
    employee_id: string;
    employee_name: string;
    role: string;
  };
  roles: RoleOption[];
  disabled?: boolean;
}>();

const emit = defineEmits<{
  (e: 'change', roleName: string): void;
}>();

const ALLOWED_SELECTABLE_ROLES = ['superadmin', 'admin', 'user'];

const formatRoleLabel = (name: string, label?: string) => {
  if (label) return label;
  return name.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
};

const currentLabel = computed(() => {
  const matched = props.roles.find(r => r.name.toLowerCase() === (props.user.role || '').toLowerCase());
  if (matched) return formatRoleLabel(matched.name, matched.label);
  return formatRoleLabel(props.user.role || 'user');
});

// Restrict selectable options to only superadmin, admin, and user
const selectableRoles = computed(() => {
  const allowed = props.roles.filter(r => ALLOWED_SELECTABLE_ROLES.includes(r.name.toLowerCase()));
  if (allowed.length > 0) return allowed;

  // Fallback defaults if roles are not yet populated
  return [
    { id: 1, name: 'superadmin', label: 'Super Administrator' },
    { id: 2, name: 'admin', label: 'Administrator' },
    { id: 5, name: 'user', label: 'Employee' },
  ];
});
</script>

<template>
  <DropdownMenu>
    <DropdownMenuTrigger asChild>
      <Button
        variant="outline"
        size="sm"
        :disabled="props.disabled"
        class="h-8 justify-between rounded-[10px] text-sm font-medium w-xs bg-background border-border hover:bg-muted/50"
      >
        <span class="truncate">{{ currentLabel }}</span>
        <ChevronDown class="ml-2 h-3.5 w-3.5 shrink-0 opacity-50" />
      </Button>
    </DropdownMenuTrigger>
    <DropdownMenuContent align="start" class="w-[180px] rounded-[12px] z-[1001]">
      <DropdownMenuItem
        v-for="role in selectableRoles"
        :key="role.id"
        @select="emit('change', role.name)"
        class="text-sm flex items-center justify-between cursor-pointer py-1.5"
        :class="{ 'font-semibold text-primary bg-primary/5': role.name.toLowerCase() === (props.user.role || '').toLowerCase() }"
      >
        <span>{{ formatRoleLabel(role.name, role.label) }}</span>
        <Check v-if="role.name.toLowerCase() === (props.user.role || '').toLowerCase()" class="h-3.5 w-3.5 text-primary" />
      </DropdownMenuItem>
    </DropdownMenuContent>
  </DropdownMenu>
</template>
