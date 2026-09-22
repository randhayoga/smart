import { describe, it, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import DataTable from '../DataTable.vue';
import { i18n } from '@/locales';
import type { ColumnDef } from '@tanstack/vue-table';

describe('DataTable.vue default sorting rules', () => {
  it('Rule 1: sorts by Code descending when a Code column exists', () => {
    const columns: ColumnDef<any>[] = [
      { accessorKey: 'number', header: 'Nomor' },
      { accessorKey: 'name', header: 'Nama' },
      { accessorKey: 'created_at', header: 'Tanggal' },
    ];
    const data = [
      { number: 'CODE-001', name: 'Item A', created_at: '2024-01-01' },
      { number: 'CODE-002', name: 'Item B', created_at: '2024-01-02' },
    ];

    const wrapper = mount(DataTable, {
      props: {
        columns: columns as any,
        data,
      },
      global: {
        plugins: [i18n],
      },
    });

    const vm = wrapper.vm as any;
    const tableState = vm.table.getState();
    expect(tableState.sorting).toEqual([{ id: 'number', desc: true }]);

    // The rendered rows should have CODE-002 first (descending)
    const rows = vm.table.getRowModel().rows;
    expect(rows[0].original.number).toBe('CODE-002');
    expect(rows[1].original.number).toBe('CODE-001');
  });

  it('Rule 1 Exception: sorts by employee_id ascending for employee tables', () => {
    const columns: ColumnDef<any>[] = [
      { accessorKey: 'employee_id', header: 'NIK' },
      { accessorKey: 'name', header: 'Nama' },
    ];
    const data = [
      { employee_id: 'EMP-02', name: 'Bob' },
      { employee_id: 'EMP-01', name: 'Alice' },
    ];

    const wrapper = mount(DataTable, {
      props: {
        columns: columns as any,
        data,
      },
      global: {
        plugins: [i18n],
      },
    });

    const vm = wrapper.vm as any;
    expect(vm.table.getState().sorting).toEqual([{ id: 'employee_id', desc: false }]);

    // The rendered rows should have EMP-01 first (ascending)
    const rows = vm.table.getRowModel().rows;
    expect(rows[0].original.employee_id).toBe('EMP-01');
    expect(rows[1].original.employee_id).toBe('EMP-02');
  });

  it('Rule 1: sorts by asset_code descending for asset tables', () => {
    const columns: ColumnDef<any>[] = [
      { accessorKey: 'asset_code', header: 'Kode Aset' },
      { accessorKey: 'category', header: 'Kategori' },
    ];
    const data = [
      { asset_code: 'AST-001', category: 'Laptop' },
      { asset_code: 'AST-002', category: 'Monitor' },
    ];

    const wrapper = mount(DataTable, {
      props: {
        columns: columns as any,
        data,
      },
      global: {
        plugins: [i18n],
      },
    });

    const vm = wrapper.vm as any;
    expect(vm.table.getState().sorting).toEqual([{ id: 'asset_code', desc: true }]);
  });

  it('Rule 2: sorts by Date newest (descending) when Code column does not exist but Date exists', () => {
    const columns: ColumnDef<any>[] = [
      { accessorKey: 'name', header: 'Nama' },
      { accessorKey: 'brand', header: 'Brand' },
      { accessorKey: 'lastUpdate', header: 'Terakhir Diperbarui' },
    ];
    const data = [
      { name: 'Item 1', brand: 'Brand A', lastUpdate: '2024-01-01' },
      { name: 'Item 2', brand: 'Brand B', lastUpdate: '2024-02-01' },
    ];

    const wrapper = mount(DataTable, {
      props: {
        columns: columns as any,
        data,
      },
      global: {
        plugins: [i18n],
      },
    });

    const vm = wrapper.vm as any;
    expect(vm.table.getState().sorting).toEqual([{ id: 'lastUpdate', desc: true }]);
  });

  it('Rule 2: sorts by waktu newest for audit logs without primary code', () => {
    const columns: ColumnDef<any>[] = [
      { accessorKey: 'waktu', header: 'Waktu' },
      { accessorKey: 'action_type', header: 'Aktivitas' },
    ];
    const data = [
      { waktu: '2024-01-01 10:00', action_type: 'login' },
      { waktu: '2024-01-02 10:00', action_type: 'logout' },
    ];

    const wrapper = mount(DataTable, {
      props: {
        columns: columns as any,
        data,
      },
      global: {
        plugins: [i18n],
      },
    });

    const vm = wrapper.vm as any;
    expect(vm.table.getState().sorting).toEqual([{ id: 'waktu', desc: true }]);
  });

  it('Rule 3: keeps empty default sort when neither Code nor Date exists', () => {
    const columns: ColumnDef<any>[] = [
      { accessorKey: 'name', header: 'Nama Lokasi' },
      { accessorKey: 'description', header: 'Deskripsi' },
    ];
    const data = [
      { name: 'Gudang A', description: 'Utama' },
      { name: 'Gudang B', description: 'Cabang' },
    ];

    const wrapper = mount(DataTable, {
      props: {
        columns: columns as any,
        data,
      },
      global: {
        plugins: [i18n],
      },
    });

    const vm = wrapper.vm as any;
    expect(vm.table.getState().sorting).toEqual([]);
  });

  it('honors explicit defaultSorting when provided', () => {
    const columns: ColumnDef<any>[] = [
      { accessorKey: 'number', header: 'Nomor' },
      { accessorKey: 'name', header: 'Nama' },
    ];
    const data = [
      { number: '001', name: 'Zeta' },
      { number: '002', name: 'Alpha' },
    ];

    const wrapper = mount(DataTable, {
      props: {
        columns: columns as any,
        data,
        defaultSorting: [{ id: 'name', desc: false }],
      },
      global: {
        plugins: [i18n],
      },
    });

    const vm = wrapper.vm as any;
    expect(vm.table.getState().sorting).toEqual([{ id: 'name', desc: false }]);
  });
});
