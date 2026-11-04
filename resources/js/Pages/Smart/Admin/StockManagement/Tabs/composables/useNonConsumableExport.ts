import type { ComputedRef, Ref } from 'vue';
import { toast } from 'vue-sonner';
import { printManajemenStok } from '@/utils/printManajemenStok';
import { exportCSV } from '@/utils/exportCSV';
import { exportExcel } from '@/utils/exportExcel';
import { formatLocation } from './useNonConsumableColumns';

export function useNonConsumableExport(options: {
  dataTableRef: Ref<any>;
  filteredUnits: ComputedRef<any[]>;
  isAllSoft: ComputedRef<boolean>;
  locationFilter: Ref<string>;
  locations: () => any[];
  customPrintHandler?: (items: any[]) => void;
  t: (key: string, params?: any) => string;
}) {
  const { dataTableRef, filteredUnits, isAllSoft, locationFilter, locations, customPrintHandler, t } = options;

  const getExportData = () => {
    if (!dataTableRef.value || !dataTableRef.value.table) return filteredUnits.value;
    const selectedRows = dataTableRef.value.table.getFilteredSelectedRowModel().rows;
    if (selectedRows.length > 0) {
      return selectedRows.map((row: any) => row.original);
    }
    return dataTableRef.value.table.getFilteredRowModel().rows.map((row: any) => row.original);
  };

  const getExportPayload = () => {
    const data = getExportData();
    const headers = [
      t('inventory.assetCode'),
      t('inventory.name'),
      t('inventory.brand'),
      t('inventory.category'),
      t('inventory.subcategory'),
      t('inventory.status'),
    ];
    if (!isAllSoft.value) {
      headers.push(t('inventory.condition'));
    }
    headers.push(t('inventory.value'));
    if (!isAllSoft.value) {
      headers.push(t('inventory.storageLocation'));
    }
    headers.push(t('inventory.tnkbShort'));

    const rows = data.map((item: any) => {
      const row = [
        item.number,
        item.barang_nama,
        item.barang_brand,
        item.barang_category,
        item.barang_subcategory,
        item.status,
      ];
      if (!isAllSoft.value) {
        row.push(item.condition);
      }
      row.push(item.price);
      if (!isAllSoft.value) {
        row.push(formatLocation(item.location, item.floor, item.room));
      }
      row.push(item.vehicle_registration || '-');
      return row;
    });
    return { headers, rows };
  };

  const handleExportCSV = () => {
    const { headers, rows } = getExportPayload();
    if (rows.length === 0) return;
    exportCSV(headers, rows, 'daftar_aset_export');
  };

  const handleExportExcel = () => {
    const { headers, rows } = getExportPayload();
    if (rows.length === 0) return;
    exportExcel(headers, rows, 'daftar_aset_export');
  };

  const handlePrint = () => {
    const data = getExportData();
    if (!data || data.length === 0) {
      toast.error(t('inventory.noDataToPrint'));
      return;
    }

    if (customPrintHandler) {
      customPrintHandler(data);
      return;
    }

    const locs = locations();
    const filteredLokasi = locationFilter.value
      ? locs?.find((l: any) => String(l.id) === String(locationFilter.value))?.name || locationFilter.value
      : null;

    printManajemenStok(data, {
      lokasi: filteredLokasi,
    });
  };

  return {
    getExportData,
    getExportPayload,
    handleExportCSV,
    handleExportExcel,
    handlePrint,
  };
}
