import { ref } from 'vue';

/**
 * Shared reactive state for table search across TableSearch and DataTable components.
 * When typing in TableSearch, isTableSearching is set to true immediately to trigger
 * skeleton loading states on tables exceeding the row threshold (default > 50 rows).
 */
const isTableSearching = ref(false);

export function useTableSearch() {
  const setTableSearching = (val: boolean) => {
    isTableSearching.value = val;
  };

  return {
    isTableSearching,
    setTableSearching,
  };
}
