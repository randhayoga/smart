<script setup lang="ts">
/**
 * Superadmin Access Management Page.
 * Orchestrates User Management and Role Management tabs, role creation/editing, and permissions.
 */
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
import { toast } from 'vue-sonner';
import { useModalLock } from '@/composables/useModalLock';
import AppLayout from '@/Layouts/AppLayout.vue';
import { X, Loader2 } from 'lucide-vue-next';
import { Button } from '@/Components/ui/button';
import { Field, FieldLabel, FieldContent, FieldError } from '@/Components/ui/field';
import Tabs from '@/Components/Tabs.vue';
import DeleteConfirmationModal from '@/Components/DeleteConfirmationModal.vue';
import DeleteErrorModal from '@/Components/DeleteErrorModal.vue';
import UserManagementTab from '@/Pages/Smart/Superadmin/tabs/UserManagementTab.vue';
import RoleManagementTab from '@/Pages/Smart/Superadmin/tabs/RoleManagementTab.vue';

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

interface PermissionItem {
  id: number;
  name: string;
  label?: string;
  group?: string;
}

interface Props {
  users: UserItem[];
  roles: RoleItem[];
  permissions: PermissionItem[];
}

const props = withDefaults(defineProps<Props>(), {
  users: () => [],
  roles: () => [],
  permissions: () => [],
});

const PROTECTED_ROLES = ['superadmin', 'admin', 'ifs_manager', 'manager', 'user'];

const isProtectedRole = (name: string): boolean => {
  return PROTECTED_ROLES.includes((name || '').toLowerCase());
};

const tabs = [
  { id: 'users', label: 'User Management' },
  { id: 'roles', label: 'Role Management' },
];

const currentTab = ref<string>('users');

// ==========================================
// User Management Actions
// ==========================================
const updatingUserId = ref<number | null>(null);

const handleRoleChange = (userId: number, roleName: string) => {
  updatingUserId.value = userId;
  router.put(
    route('smart.access.users.role.update', userId),
    { role: roleName },
    {
      preserveScroll: true,
      onSuccess: () => {
        toast.success('User role updated successfully.');
      },
      onError: (errors: Record<string, string>) => {
        toast.error(errors.role || 'Failed to update user role.');
      },
      onFinish: () => {
        updatingUserId.value = null;
      },
    }
  );
};

const isSyncing = ref(false);

const handleSyncManagers = () => {
  isSyncing.value = true;
  router.post(
    route('smart.access.sync-managers'),
    {},
    {
      preserveScroll: true,
      onSuccess: (page) => {
        const msg = (page.props.flash as any)?.success || 'Successfully synchronized managers and non-managerial employees data from USER_HRIS.';
        toast.success(msg);
      },
      onError: () => {
        toast.error('Failed to sync managers from HRIS.');
      },
      onFinish: () => {
        isSyncing.value = false;
      },
    }
  );
};

// ==========================================
// Role Management Actions
// ==========================================
const updatingPermissionKey = ref<string | null>(null);

const handleTogglePermission = (roleId: number, permissionName: string, enabled: boolean) => {
  const key = `${roleId}_${permissionName}`;
  updatingPermissionKey.value = key;

  router.put(
    route('smart.access.roles.permissions.update', roleId),
    {
      permission: permissionName,
      granted: enabled,
      enabled: enabled,
    },
    {
      preserveScroll: true,
      onSuccess: () => {
        toast.success(`Permission ${enabled ? 'granted' : 'revoked'} successfully.`);
      },
      onError: (errors: Record<string, string>) => {
        toast.error(errors.permission || errors.granted || errors.enabled || 'Failed to update permission.');
      },
      onFinish: () => {
        updatingPermissionKey.value = null;
      },
    }
  );
};

// ==========================================
// Create Role Modal
// ==========================================
const isCreateModalOpen = ref(false);
const createRoleForm = useForm({
  name: '',
});
const createFormErrors = ref<{ name?: string }>({});

const openCreateModal = () => {
  createRoleForm.reset();
  createRoleForm.clearErrors();
  createFormErrors.value = {};
  isCreateModalOpen.value = true;
};

const closeCreateModal = () => {
  if (createRoleForm.processing) return;
  isCreateModalOpen.value = false;
};

const submitCreateRole = () => {
  createFormErrors.value = {};
  const trimmed = createRoleForm.name.trim();
  if (!trimmed) {
    createFormErrors.value.name = 'Role name is required.';
    return;
  }
  createRoleForm.name = trimmed;
  createRoleForm.post(route('smart.access.roles.store'), {
    preserveScroll: true,
    onSuccess: () => {
      toast.success('Role created successfully.');
      isCreateModalOpen.value = false;
      createRoleForm.reset();
    },
    onError: (errors: Record<string, string>) => {
      createFormErrors.value = errors;
    },
  });
};

// ==========================================
// Edit Role Modal
// ==========================================
const isEditModalOpen = ref(false);
const editingRole = ref<RoleItem | null>(null);
const editRoleForm = useForm({
  name: '',
});
const editFormErrors = ref<{ name?: string }>({});

const openEditModal = (role: RoleItem) => {
  editingRole.value = role;
  // Bug 3 Fix: Pre-fill with the human-readable label rather than internal identifier
  editRoleForm.name = role.label || role.name;
  editRoleForm.clearErrors();
  editFormErrors.value = {};
  isEditModalOpen.value = true;
};

const closeEditModal = () => {
  if (editRoleForm.processing) return;
  isEditModalOpen.value = false;
  editingRole.value = null;
};

const submitUpdateRole = () => {
  if (!editingRole.value) return;
  editFormErrors.value = {};
  const trimmed = editRoleForm.name.trim();
  if (!trimmed) {
    editFormErrors.value.name = 'Role name is required.';
    return;
  }
  editRoleForm.name = trimmed;
  editRoleForm.put(route('smart.access.roles.update', editingRole.value.id), {
    preserveScroll: true,
    onSuccess: () => {
      toast.success('Role updated successfully.');
      isEditModalOpen.value = false;
      editingRole.value = null;
    },
    onError: (errors: Record<string, string>) => {
      editFormErrors.value = errors;
    },
  });
};

// ==========================================
// Delete Role Modal
// ==========================================
const isDeleteModalOpen = ref(false);
const roleToDelete = ref<RoleItem | null>(null);
const deleteForm = useForm({});

const isErrorModalOpen = ref(false);
const errorModalMessage = ref('');

const openDeleteModal = (role: RoleItem) => {
  if (isProtectedRole(role.name)) {
    errorModalMessage.value = `Role '${role.label || role.name}' is a system-protected role and cannot be deleted.`;
    isErrorModalOpen.value = true;
    return;
  }
  if (role.users_count > 0) {
    errorModalMessage.value = `Role '${role.label || role.name}' cannot be deleted because it is currently assigned to ${role.users_count} active user(s). Please reassign all users before deleting this role.`;
    isErrorModalOpen.value = true;
    return;
  }
  roleToDelete.value = role;
  isDeleteModalOpen.value = true;
};

const closeDeleteModal = () => {
  if (deleteForm.processing) return;
  isDeleteModalOpen.value = false;
  roleToDelete.value = null;
};

const handleConfirmDelete = () => {
  if (!roleToDelete.value) return;
  deleteForm.delete(route('smart.access.roles.destroy', roleToDelete.value.id), {
    preserveScroll: true,
    onSuccess: () => {
      toast.success('Role deleted successfully.');
      isDeleteModalOpen.value = false;
      roleToDelete.value = null;
    },
    onError: (errors: Record<string, string>) => {
      toast.error((errors as any)?.role || 'Failed to delete role.');
    },
  });
};

// Modal locking & ESC listener
useModalLock(computed(() => isCreateModalOpen.value || isEditModalOpen.value));

const closeOnEscape = (e: KeyboardEvent) => {
  if (e.key === 'Escape') {
    if (isCreateModalOpen.value) closeCreateModal();
    if (isEditModalOpen.value) closeEditModal();
    if (isDeleteModalOpen.value) closeDeleteModal();
    if (isErrorModalOpen.value) isErrorModalOpen.value = false;
  }
};

onMounted(() => {
  document.addEventListener('keydown', closeOnEscape);
});

onUnmounted(() => {
  document.removeEventListener('keydown', closeOnEscape);
});
</script>

<template>
  <AppLayout title="Access Management">
    <div class="space-y-1">
      <!-- Top Pill Tabs -->
      <Tabs v-model="currentTab" :tabs="tabs" />

      <!-- Tab 1: User Management -->
      <div v-show="currentTab === 'users'">
        <UserManagementTab
          :users="props.users"
          :roles="props.roles"
          :updating-user-id="updatingUserId"
          :is-syncing="isSyncing"
          @change-role="handleRoleChange"
          @sync-managers="handleSyncManagers"
        />
      </div>

      <!-- Tab 2: Role Management -->
      <div v-show="currentTab === 'roles'">
        <RoleManagementTab
          :roles="props.roles"
          :permissions="props.permissions"
          :updating-permission-key="updatingPermissionKey"
          @create-role="openCreateModal"
          @edit-role="openEditModal"
          @delete-role="openDeleteModal"
          @toggle-permission="handleTogglePermission"
        />
      </div>
    </div>

    <!-- Create Role Modal -->
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
          v-if="isCreateModalOpen"
          @click="closeCreateModal"
          class="fixed inset-0 z-[100] flex items-center justify-center bg-gray-900/50 backdrop-blur-sm p-4 overscroll-contain"
        >
          <div
            class="bg-card text-foreground rounded-[14px] shadow-2xl w-full min-h-[200px] max-w-[500px] overflow-hidden flex flex-col"
            @click.stop
          >
            <!-- Modal Header -->
            <div class="flex items-center justify-between pt-3 pb-2 px-4 border-b border-border">
              <h3 class="text-lg font-bold text-foreground">Create New Role</h3>
              <button @click="closeCreateModal" class="p-2 hover:bg-muted rounded-full transition-colors">
                <X class="w-5 h-5 text-muted-foreground cursor-pointer" />
              </button>
            </div>

            <!-- Modal Body -->
            <div class="p-6 flex-grow overflow-y-auto overscroll-contain">
              <Field :data-invalid="!!createFormErrors.name || undefined">
                <FieldLabel><span>Role Name<span class="text-destructive">*</span></span></FieldLabel>
                <FieldContent>
                  <input
                    type="text"
                    v-model="createRoleForm.name"
                    maxlength="255"
                    placeholder="e.g. Quality Auditor"
                    class="w-full px-3 py-2 text-sm border rounded-[14px] bg-background focus:outline-none focus:ring-2 transition-colors"
                    :class="[createFormErrors.name ? 'border-destructive focus:ring-destructive/20 focus:border-destructive' : 'border-input focus:ring-primary/20 focus:border-primary']"
                    @keydown.enter.prevent="submitCreateRole"
                  />
                </FieldContent>
                <FieldError v-if="createFormErrors.name">{{ createFormErrors.name }}</FieldError>
              </Field>
            </div>

            <!-- Modal Footer -->
            <div class="py-3 px-4 border-t border-border flex items-center justify-between">
              <p class="text-sm text-rose-500 italic font-medium">* Required field</p>
              <div class="flex items-center gap-3">
                <Button @click="closeCreateModal" variant="white" size="xl">
                  Cancel
                </Button>
                <Button @click="submitCreateRole" variant="primary" :disabled="createRoleForm.processing" size="xl" class="relative">
                  <Loader2 v-if="createRoleForm.processing" class="absolute inset-0 m-auto h-5 w-5 animate-spin" />
                  <span :class="{ 'opacity-0': createRoleForm.processing }">
                    Create Role
                  </span>
                </Button>
              </div>
            </div>
          </div>
        </div>
      </Transition>
    </Teleport>

    <!-- Edit Role Modal -->
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
          v-if="isEditModalOpen"
          @click="closeEditModal"
          class="fixed inset-0 z-[100] flex items-center justify-center bg-gray-900/50 backdrop-blur-sm p-4 overscroll-contain"
        >
          <div
            class="bg-card text-foreground rounded-[14px] shadow-2xl w-full min-h-[200px] max-w-[500px] overflow-hidden flex flex-col"
            @click.stop
          >
            <!-- Modal Header -->
            <div class="flex items-center justify-between pt-3 pb-2 px-4 border-b border-border">
              <h3 class="text-lg font-bold text-foreground">Edit Role</h3>
              <button @click="closeEditModal" class="p-2 hover:bg-muted rounded-full transition-colors">
                <X class="w-5 h-5 text-muted-foreground cursor-pointer" />
              </button>
            </div>

            <!-- Modal Body -->
            <div class="p-6 flex-grow overflow-y-auto overscroll-contain">
              <Field :data-invalid="!!editFormErrors.name || undefined">
                <FieldLabel><span>Role Name<span class="text-destructive">*</span></span></FieldLabel>
                <FieldContent>
                  <input
                    type="text"
                    v-model="editRoleForm.name"
                    maxlength="255"
                    placeholder="Role name"
                    class="w-full px-3 py-2 text-sm border rounded-[14px] bg-background focus:outline-none focus:ring-2 transition-colors"
                    :class="[editFormErrors.name ? 'border-destructive focus:ring-destructive/20 focus:border-destructive' : 'border-input focus:ring-primary/20 focus:border-primary']"
                    @keydown.enter.prevent="submitUpdateRole"
                  />
                </FieldContent>
                <FieldError v-if="editFormErrors.name">{{ editFormErrors.name }}</FieldError>
              </Field>
            </div>

            <!-- Modal Footer -->
            <div class="py-3 px-4 border-t border-border flex items-center justify-between">
              <p class="text-sm text-rose-500 italic font-medium">* Required field</p>
              <div class="flex items-center gap-3">
                <Button @click="closeEditModal" variant="white" size="xl">
                  Cancel
                </Button>
                <Button @click="submitUpdateRole" variant="primary" :disabled="editRoleForm.processing" size="xl" class="relative">
                  <Loader2 v-if="editRoleForm.processing" class="absolute inset-0 m-auto h-5 w-5 animate-spin" />
                  <span :class="{ 'opacity-0': editRoleForm.processing }">
                    Save Changes
                  </span>
                </Button>
              </div>
            </div>
          </div>
        </div>
      </Transition>
    </Teleport>

    <!-- Delete Role Modal -->
    <DeleteConfirmationModal
      :is-open="isDeleteModalOpen"
      :item-count="1"
      :item-name="'Role'"
      :fields="roleToDelete ? [{ label: 'Role Name', value: roleToDelete.label || roleToDelete.name }] : []"
      :processing="deleteForm.processing"
      @close="closeDeleteModal"
      @confirm="handleConfirmDelete"
    />

    <!-- Cannot Delete Warning Modal -->
    <DeleteErrorModal
      :is-open="isErrorModalOpen"
      :error-message="errorModalMessage"
      @close="isErrorModalOpen = false"
    />
  </AppLayout>
</template>
