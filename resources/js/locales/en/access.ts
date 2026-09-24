/**
 * English Access Management translation dictionary.
 */
export default {
  title: 'Access Management',
  tabs: {
    users: 'User Management',
    roles: 'Role Management',
  },
  users: {
    title: 'User Management',
    searchLabel: 'Search User',
    searchPlaceholder: 'Search NPK or User Name',
    filterRoleLabel: 'Filter Role',
    allRoles: 'All Roles',
    rowsPerPage: 'Rows per page',
    allRows: 'All rows',
    syncHris: 'Sync from USER_HRIS',
    syncing: 'Syncing...',
    userColumn: 'User',
    roleColumn: 'Role',
    syncSuccess: 'Successfully synchronized managers and non-managerial employees data from USER_HRIS.',
    syncError: 'Failed to sync managers from HRIS.',
    roleUpdateSuccess: 'User role updated successfully.',
    roleUpdateError: 'Failed to update user role.',
  },
  roles: {
    title: 'Role Management',
    createNewRole: 'Create New Role',
    rowsPerPage: 'Rows per page',
    allRows: 'All rows',
    rolesColumn: 'Roles',
    actionColumn: 'Action',
    systemBadge: 'System',
    userCount: '{count} user | {count} users',
    editRole: 'Edit Role',
    deleteRole: 'Delete Role',
    groups: {
      dashboard: 'Dashboard',
      master: 'Master Data',
      inventory: 'Inventory',
      requests: 'Requests',
      audit: 'Audit',
      access: 'Access Control',
      karyawan: 'Employee Directory',
      notifications: 'Notifications',
      general: 'General',
    },
    permissions: {
      dashboard: {
        admin: {
          view: 'View Admin Dashboard',
        },
        user: {
          view: 'View User Dashboard',
        },
      },
      master: {
        view: 'View Master Data',
        manage: 'Manage Master Data (Categories, Locations, etc.)',
      },
      inventory: {
        view: 'View Inventory Catalog, Lots & Assets',
        manage: 'Manage Inventory Items, Lots, and Units',
        borrow: 'Perform Direct Unit Borrow & Return',
        manual_request: 'Create Manual Stock Requests',
        status_approval: {
          request: 'Submit Unit Status Change Requests',
          decide: 'Approve or Reject Unit Status Changes',
        },
      },
      requests: {
        create: 'Create and Submit Requisitions (Cart)',
        view_own: 'View Own Requisition History & Cancel',
        approve: 'Approve or Reject Subordinate Requisitions',
        inbox: {
          view: 'View Approved Requests Inbox',
        },
        confirm: 'Confirm and Review Requisitions',
        fulfill: 'Allocate Units/Lots and Confirm Fulfillment',
        handover: 'Schedule and Process Item Handovers',
        returns: 'Process and Confirm Item Returns',
        archive: {
          view: 'View Requisition Archive',
        },
      },
      karyawan: {
        view: 'View Employee Directory and Active Loans',
      },
      audit: {
        view: 'View Inventory Activity Logs and Stock Audits',
      },
      notifications: {
        manage: 'View and Manage Personal Notifications',
      },
      access: {
        manage: 'Manage System Roles and Permissions',
      },
    },
    tooltips: {
      superadminAll: 'Superadmin has all permissions by default',
      togglePermission: 'Toggle {name}',
    },
    names: {
      superadmin: 'Super Administrator',
      admin: 'Administrator',
      ifs_manager: 'IFS Manager',
      manager: 'Department / Project Manager',
      user: 'Employee',
    },
    disabledDeleteReasons: {
      systemRole: 'System-protected roles cannot be deleted.',
      hasUsers: 'Cannot delete role assigned to {count} user(s).',
    },
    modals: {
      createTitle: 'Create New Role',
      editTitle: 'Edit Role',
      roleName: 'Role Name',
      roleNamePlaceholder: 'e.g. Quality Auditor',
      editRoleNamePlaceholder: 'Role name',
      requiredField: '* Required field',
      createButton: 'Create Role',
      saveChanges: 'Save Changes',
      cancel: 'Cancel',
      deleteConfirmItemName: 'Role',
      createSuccess: 'Role created successfully.',
      updateSuccess: 'Role updated successfully.',
      deleteSuccess: 'Role deleted successfully.',
      deleteErrorDefault: 'Failed to delete role.',
      permissionGranted: 'Permission granted successfully.',
      permissionRevoked: 'Permission revoked successfully.',
      permissionUpdateError: 'Failed to update permission.',
      validation: {
        roleNameRequired: 'Role name is required.',
      },
      errors: {
        protectedRoleDelete: "Role '{name}' is a system-protected role and cannot be deleted.",
        roleHasUsersDelete: "Role '{name}' cannot be deleted because it is currently assigned to {count} active user(s). Please reassign all users before deleting this role.",
      },
    },
  },
};
