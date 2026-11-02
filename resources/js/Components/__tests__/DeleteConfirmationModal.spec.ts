import { describe, it, expect, beforeEach } from 'vitest';
import { mount } from '@vue/test-utils';
import DeleteConfirmationModal from '../DeleteConfirmationModal.vue';
import { i18n, setI18nLanguage } from '@/locales';

describe('DeleteConfirmationModal.vue', () => {
  beforeEach(() => {
    setI18nLanguage('id');
  });

  const mountModal = (props: any, slots: any = {}) => {
    return mount(DeleteConfirmationModal, {
      props: {
        isOpen: true,
        itemCount: 1,
        ...props,
      },
      slots,
      global: {
        plugins: [i18n],
        stubs: {
          Teleport: true,
          Transition: false,
        },
      },
    });
  };

  describe('Standard Deletion Mode', () => {
    it('renders delete confirmation title, message, and notice for single item', () => {
      const wrapper = mountModal({
        itemCount: 1,
        itemName: 'Tipe',
      });

      expect(wrapper.text()).toContain('Konfirmasi Penghapusan');
      expect(wrapper.text()).toContain('Apakah Anda yakin untuk menghapus Tipe yang Anda pilih?');
    });

    it('renders delete confirmation message for multiple items', () => {
      const wrapper = mountModal({
        itemCount: 5,
        itemName: 'Barang',
      });

      expect(wrapper.text()).toContain('Apakah Anda yakin untuk menghapus 5 Barang yang Anda pilih?');
    });
  });

  describe('Model Deletion Mode', () => {
    const sampleModel = {
      id: 1,
      code: 'MOD-001',
      number: 'MOD-001',
      category: 'Elektronik',
      type: 'Laptop',
      subcategory: 'Laptop',
      brand: 'Lenovo',
      name: 'ThinkPad T14',
      specification: 'Core i7 / 16GB',
      lastUpdate: '20-10-2026 14:00',
      amount: 15,
    };

    it('renders single model rich details correctly in Indonesian', () => {
      setI18nLanguage('id');
      const wrapper = mountModal({
        itemCount: 1,
        itemName: 'Tipe Barang',
        itemData: sampleModel,
      });

      expect(wrapper.text()).toContain('Konfirmasi Penghapusan');
      expect(wrapper.text()).toContain('MOD-001');
      expect(wrapper.text()).toContain('Elektronik');
      expect(wrapper.text()).toContain('Laptop');
      expect(wrapper.text()).toContain('Lenovo');
      expect(wrapper.text()).toContain('ThinkPad T14');
      expect(wrapper.text()).toContain('Core i7 / 16GB');
      expect(wrapper.text()).toContain('20-10-2026 14:00');
      expect(wrapper.text()).toContain('15');
      // Ensure it does not mistakenly treat it as an asset/unit
      expect(wrapper.text()).not.toContain('Nomor Aset');
    });

    it('renders single model rich details correctly in English', () => {
      setI18nLanguage('en');
      const wrapper = mountModal({
        itemCount: 1,
        itemName: 'Item Type',
        itemData: sampleModel,
      });

      expect(wrapper.text()).toContain('Confirm Deletion');
      expect(wrapper.text()).toContain('Model Number');
      expect(wrapper.text()).toContain('MOD-001');
      expect(wrapper.text()).toContain('Category');
      expect(wrapper.text()).toContain('Type');
      expect(wrapper.text()).toContain('Brand');
      expect(wrapper.text()).toContain('Lenovo');
      expect(wrapper.text()).toContain('Name');
      expect(wrapper.text()).toContain('ThinkPad T14');
      expect(wrapper.text()).toContain('Specification');
      expect(wrapper.text()).toContain('Last Update');
      expect(wrapper.text()).toContain('Total');
      // Ensure it does not mistakenly treat it as an asset/unit
      expect(wrapper.text()).not.toContain('Asset Number');
    });

    it('hides specification details and row when model category is COMP (Computer)', () => {
      setI18nLanguage('en');
      const compModel = {
        ...sampleModel,
        category: 'Computer',
        category_code: 'COMP',
      };
      const wrapper = mountModal({
        itemCount: 1,
        itemName: 'Item Type',
        itemData: compModel,
      });

      expect(wrapper.text()).toContain('Model Number');
      expect(wrapper.text()).toContain('Computer');
      expect(wrapper.text()).toContain('ThinkPad T14');
      // Specification row & details should NOT be present
      expect(wrapper.text()).not.toContain('Specification');
      expect(wrapper.text()).not.toContain('Core i7 / 16GB');
    });
  });

  describe('Asset Deletion Mode', () => {
    const sampleAsset = {
      number: 'AST-001',
      type: 'Asset',
      classification: 'Aset',
      status: 'Tersedia',
      condition: 'Bagus',
      location: 'Warehouse A',
      price: 15000000,
      lot_id: 1,
    };

    it('renders condition and location for physical assets', () => {
      setI18nLanguage('en');
      const wrapper = mountModal({
        itemCount: 1,
        itemName: 'Asset',
        itemData: sampleAsset,
      });

      const text = wrapper.text();
      expect(text).toContain('AST-001');
      expect(text).toContain('Condition');
      expect(text).toContain('Location');
    });

    it('hides condition and location for software assets', () => {
      setI18nLanguage('en');
      const softwareAsset = {
        ...sampleAsset,
        category: 'Software',
        category_code: 'SOFT',
        condition: null,
        location: null,
      };

      const wrapper = mountModal({
        itemCount: 1,
        itemName: 'Asset',
        itemData: softwareAsset,
      });

      const text = wrapper.text();
      expect(text).toContain('AST-001');
      expect(text).not.toContain('Condition');
      expect(text).not.toContain('Location');
    });
  });

  describe('Asset Status Change Approval Mode', () => {
    const statusApprovalItem = {
      id: 10,
      asset_code: 'AST-001',
      brand: 'Lenovo',
      nama: 'ThinkPad T14',
      status_label: 'Rusak',
      proposed_condition: 'Rusak',
      previous_status: 'Tersedia',
      previous_condition: 'Bagus',
      admin_note: 'LCD retak parah',
    };

    const statusApprovalItem2 = {
      id: 11,
      asset_code: 'AST-002',
      brand: 'Acer',
      nama: 'Swift Go',
      status_label: 'Hilang',
      proposed_condition: 'Hilang',
      previous_status: 'Tersedia',
      previous_condition: 'Bagus',
      admin_note: 'Hilang saat dinas',
    };

    it('renders single approval confirmation when approving status change in Indonesian', () => {
      setI18nLanguage('id');
      const wrapper = mountModal({
        itemCount: 1,
        itemName: 'Perubahan Status Aset',
        actionType: 'approved',
        itemData: [statusApprovalItem],
      });

      expect(wrapper.text()).toContain('Konfirmasi Approval');
      expect(wrapper.text()).toContain('Apakah Anda yakin untuk meng-approve penghapusan aset ini?');
      expect(wrapper.text()).not.toContain('Tindakan ini tidak dapat dibatalkan');
      expect(wrapper.text()).toContain('AST-001');
      expect(wrapper.text()).toContain('Bagus');
      expect(wrapper.text()).toContain('Rusak');
      expect(wrapper.text()).toContain('Catatan penghapusan');
      expect(wrapper.text()).toContain('LCD retak parah');
    });

    it('renders single rejection confirmation when rejecting status change in Indonesian', () => {
      setI18nLanguage('id');
      const wrapper = mountModal({
        itemCount: 1,
        itemName: 'Perubahan Status Aset',
        actionType: 'rejected',
        itemData: [statusApprovalItem],
      });

      expect(wrapper.text()).toContain('Konfirmasi Penolakan');
      expect(wrapper.text()).toContain('Apakah Anda yakin untuk menolak penghapusan aset ini?');
      expect(wrapper.text()).not.toContain('Tindakan ini tidak dapat dibatalkan');
    });

    it('renders bulk approval and rejection confirmations in Indonesian (> 1 selection)', () => {
      setI18nLanguage('id');
      const approveWrapper = mountModal({
        itemCount: 2,
        itemName: 'Perubahan Status Aset',
        actionType: 'approved',
        itemData: [statusApprovalItem, statusApprovalItem2],
      });
      expect(approveWrapper.text()).toContain('Apakah Anda yakin untuk meng-approve 2 penghapusan aset ini?');
      expect(approveWrapper.text()).toContain('AST-001');
      expect(approveWrapper.text()).toContain('LCD retak parah');
      expect(approveWrapper.text()).toContain('AST-002');
      expect(approveWrapper.text()).toContain('Hilang saat dinas');

      const rejectWrapper = mountModal({
        itemCount: 2,
        itemName: 'Perubahan Status Aset',
        actionType: 'rejected',
        itemData: [statusApprovalItem, statusApprovalItem2],
      });
      expect(rejectWrapper.text()).toContain('Apakah Anda yakin untuk menolak 2 penghapusan aset ini?');
    });

    it('renders single and bulk approval/rejection confirmations in English', () => {
      setI18nLanguage('en');
      const singleApprove = mountModal({
        itemCount: 1,
        itemName: 'Perubahan Status Aset',
        actionType: 'approved',
        itemData: [statusApprovalItem],
      });
      expect(singleApprove.text()).toContain('Are you sure you want to approve this asset deletion?');

      const singleReject = mountModal({
        itemCount: 1,
        itemName: 'Perubahan Status Aset',
        actionType: 'rejected',
        itemData: [statusApprovalItem],
      });
      expect(singleReject.text()).toContain('Are you sure you want to reject this asset deletion?');

      const bulkApprove = mountModal({
        itemCount: 2,
        itemName: 'Perubahan Status Aset',
        actionType: 'approved',
        itemData: [statusApprovalItem, statusApprovalItem2],
      });
      expect(bulkApprove.text()).toContain('Are you sure you want to approve 2 selected assets deletion?');

      const bulkReject = mountModal({
        itemCount: 2,
        itemName: 'Perubahan Status Aset',
        actionType: 'rejected',
        itemData: [statusApprovalItem, statusApprovalItem2],
      });
      expect(bulkReject.text()).toContain('Are you sure you want to reject 2 selected assets deletion?');
    });

    it('hides condition and storage location for software assets in status change approval', () => {
      setI18nLanguage('en');
      const softwareStatusItem = {
        ...statusApprovalItem,
        category: 'Software',
        category_code: 'SOFT',
        unit_details: {
          location: 'HQ Server Room',
        },
      };

      const wrapper = mountModal({
        itemCount: 1,
        itemName: 'Perubahan Status Aset',
        actionType: 'approved',
        itemData: [softwareStatusItem],
      });

      const text = wrapper.text();
      expect(text).toContain('AST-001');
      expect(text).not.toContain('Condition');
      expect(text).not.toContain('Proposed Condition');
      expect(text).not.toContain('Storage Location');
    });
  });

  describe('Asset Activation Approval Mode', () => {
    const activationItem1 = {
      id: 1,
      asset_code: 'AST-AKT-001',
      brand: 'Dell',
      nama: 'Latitude 5420',
      category: 'Elektronik',
      subcategory: 'Laptop',
      unit_details: {
        type: 'LT',
        classification: 'Aset',
        status: 'Belum Diverifikasi',
        condition: 'Bagus',
        lot_code: 'LOT-2026-001',
        location: 'Gd. Utama',
        floor: 'Lt. 2',
        room: 'Server Room',
      },
    };

    const activationItem2 = {
      id: 2,
      asset_code: 'AST-AKT-002',
      brand: 'Apple',
      nama: 'MacBook Pro',
      category: 'Elektronik',
      subcategory: 'Laptop',
      unit_details: {
        type: 'LT',
        classification: 'Aset',
        status: 'Belum Diverifikasi',
        condition: 'Bagus',
        lot_code: 'LOT-2026-002',
        location: 'Gd. Utama',
      },
    };

    it('renders activation approval confirmation in Indonesian', () => {
      setI18nLanguage('id');
      const wrapper = mountModal({
        itemCount: 1,
        itemName: 'Aktivasi Aset Baru',
        actionType: 'approved',
        itemData: [activationItem1],
      });

      expect(wrapper.text()).toContain('Konfirmasi Approval');
      expect(wrapper.text()).toContain('Apakah Anda yakin untuk meng-approve aktivasi aset ini?');
      expect(wrapper.text()).not.toContain('Tindakan ini tidak dapat dibatalkan');
      expect(wrapper.text()).toContain('AST-AKT-001');
      expect(wrapper.text()).toContain('Belum Diverifikasi');
    });

    it('renders activation rejection confirmation in Indonesian', () => {
      setI18nLanguage('id');
      const wrapper = mountModal({
        itemCount: 1,
        itemName: 'Aktivasi Aset Baru',
        actionType: 'rejected',
        itemData: [activationItem1],
      });

      expect(wrapper.text()).toContain('Konfirmasi Penolakan');
      expect(wrapper.text()).toContain('Apakah Anda yakin untuk menolak aktivasi aset ini?');
      expect(wrapper.text()).not.toContain('Tindakan ini tidak dapat dibatalkan');
    });

    it('renders activation approval confirmation in English', () => {
      setI18nLanguage('en');
      const wrapper = mountModal({
        itemCount: 1,
        itemName: 'New Asset Activation',
        actionType: 'approved',
        itemData: [activationItem1],
      });

      expect(wrapper.text()).toContain('Confirm Approval');
      expect(wrapper.text()).toContain('Are you sure you want to approve this asset activation?');
      expect(wrapper.text()).not.toContain('This action cannot be undone');
    });

    it('renders activation rejection confirmation in English', () => {
      setI18nLanguage('en');
      const wrapper = mountModal({
        itemCount: 1,
        itemName: 'New Asset Activation',
        actionType: 'rejected',
        itemData: [activationItem1],
      });

      expect(wrapper.text()).toContain('Confirm Rejection');
      expect(wrapper.text()).toContain('Are you sure you want to reject this asset activation?');
      expect(wrapper.text()).not.toContain('This action cannot be undone');
    });

    it('renders bulk activation approval with multiple items and count', () => {
      setI18nLanguage('id');
      const wrapper = mountModal({
        itemCount: 2,
        itemName: 'Aktivasi Aset Baru',
        actionType: 'approved',
        itemData: [activationItem1, activationItem2],
      });

      expect(wrapper.text()).toContain('Konfirmasi Approval');
      expect(wrapper.text()).toContain('Apakah Anda yakin untuk meng-approve 2 aktivasi aset yang Anda pilih?');
      expect(wrapper.text()).toContain('AST-AKT-001');
      expect(wrapper.text()).toContain('AST-AKT-002');
    });

    const softwareActivationItem1 = {
      id: 10,
      asset_code: 'AST-SOFT-001',
      brand: 'JetBrains',
      nama: 'IntelliJ IDEA Ultimate',
      category: 'Software',
      category_code: 'SOFT',
      subcategory: 'IDE',
      unit_details: {
        type: 'LT',
        classification: 'Aset',
        status: 'Belum Diverifikasi',
        condition: null,
        lot_code: 'LOT-SOFT-01',
        location: null,
        license_exp_date: '2027-06-30',
        renewal_reminder: 14,
      },
    };

    const softwareActivationItem2 = {
      id: 11,
      asset_code: 'AST-SOFT-002',
      brand: 'Microsoft',
      nama: 'Windows 11 Pro',
      category: 'Software',
      category_code: 'SOFT',
      subcategory: 'Operating System',
      unit_details: {
        type: 'LT',
        classification: 'Aset',
        status: 'Belum Diverifikasi',
        condition: null,
        lot_code: 'LOT-SOFT-02',
        location: null,
        license_exp_date: null,
        renewal_reminder: null,
      },
    };

    const softwareActivationItem3 = {
      id: 12,
      asset_code: 'AST-SOFT-003',
      brand: 'Oracle',
      nama: 'Java SE Subscription',
      category: 'Software',
      category_code: 'SOFT',
      subcategory: 'Runtime',
      unit_details: {
        type: 'LT',
        classification: 'Aset',
        status: 'Belum Diverifikasi',
        condition: null,
        lot_code: 'LOT-SOFT-03',
        location: null,
        license_exp_date: '2027-12-31',
        renewal_reminder: 0,
      },
    };

    it('renders license expiration date and renewal reminder for software asset in single activation approval', () => {
      setI18nLanguage('id');
      const wrapper = mountModal({
        itemCount: 1,
        itemName: 'Aktivasi Aset Baru',
        actionType: 'approved',
        itemData: [softwareActivationItem1],
      });

      const text = wrapper.text();
      expect(text).toContain('AST-SOFT-001');
      expect(text).toContain('Tanggal Kedaluwarsa Lisensi');
      expect(text).toContain('30-06-2027');
      expect(text).toContain('Pengingat Perpanjangan');
      expect(text).toContain('14 Hari');
      expect(text).not.toContain('Kondisi');
      expect(text).not.toContain('Lokasi Penyimpanan');
    });

    it('renders Perpetual when license_exp_date is null for software asset in single activation approval', () => {
      setI18nLanguage('id');
      const wrapper = mountModal({
        itemCount: 1,
        itemName: 'Aktivasi Aset Baru',
        actionType: 'approved',
        itemData: [softwareActivationItem2],
      });

      const text = wrapper.text();
      expect(text).toContain('AST-SOFT-002');
      expect(text).toContain('Tanggal Kedaluwarsa Lisensi');
      expect(text).toContain('Perpetual');
      expect(text).not.toContain('Pengingat Perpanjangan');
    });

    it('renders "Tidak ada pengingat" when renewal_reminder is 0 for software asset', () => {
      setI18nLanguage('id');
      const wrapper = mountModal({
        itemCount: 1,
        itemName: 'Aktivasi Aset Baru',
        actionType: 'approved',
        itemData: [softwareActivationItem3],
      });

      const text = wrapper.text();
      expect(text).toContain('AST-SOFT-003');
      expect(text).toContain('Tanggal Kedaluwarsa Lisensi');
      expect(text).toContain('31-12-2027');
      expect(text).toContain('Pengingat Perpanjangan');
      expect(text).toContain('Tidak ada pengingat');
    });

    it('renders "No reminder" in English when renewal_reminder is 0 for software asset', () => {
      setI18nLanguage('en');
      const wrapper = mountModal({
        itemCount: 1,
        itemName: 'New Asset Activation',
        actionType: 'approved',
        itemData: [softwareActivationItem3],
      });

      const text = wrapper.text();
      expect(text).toContain('AST-SOFT-003');
      expect(text).toContain('License Expiration Date');
      expect(text).toContain('31-12-2027');
      expect(text).toContain('Renewal Reminder');
      expect(text).toContain('No reminder');
    });

    it('renders license expiration date and renewal reminder in bulk activation approval for software assets', () => {
      setI18nLanguage('id');
      const wrapper = mountModal({
        itemCount: 2,
        itemName: 'Aktivasi Aset Baru',
        actionType: 'approved',
        itemData: [softwareActivationItem1, softwareActivationItem2],
      });

      const text = wrapper.text();
      expect(text).toContain('AST-SOFT-001');
      expect(text).toContain('AST-SOFT-002');
      expect(text).toContain('Tanggal Kedaluwarsa Lisensi');
      expect(text).toContain('30-06-2027');
      expect(text).toContain('14 Hari');
      expect(text).toContain('Perpetual');
      expect(text).not.toContain('Kondisi');
    });

    it('renders software license details in English in activation approval', () => {
      setI18nLanguage('en');
      const wrapper = mountModal({
        itemCount: 1,
        itemName: 'New Asset Activation',
        actionType: 'approved',
        itemData: [softwareActivationItem1],
      });

      const text = wrapper.text();
      expect(text).toContain('AST-SOFT-001');
      expect(text).toContain('License Expiration Date');
      expect(text).toContain('30-06-2027');
      expect(text).toContain('Renewal Reminder');
      expect(text).toContain('14 Day(s)');
      expect(text).not.toContain('Condition');
      expect(text).not.toContain('Storage Location');
    });
  });

  describe('Events and interactions', () => {
    it('emits confirm when confirm button is clicked', async () => {
      const wrapper = mountModal({
        itemCount: 1,
        itemName: 'Aktivasi Aset Baru',
        actionType: 'approved',
      });

      const confirmBtn = wrapper.findAll('button').find(b => b.text().includes('Konfirmasi Approval'));
      expect(confirmBtn?.exists()).toBe(true);
      await confirmBtn?.trigger('click');

      expect(wrapper.emitted('confirm')).toBeTruthy();
    });

    it('emits close when cancel button or header close icon is clicked', async () => {
      const wrapper = mountModal({
        itemCount: 1,
        itemName: 'Aktivasi Aset Baru',
        actionType: 'approved',
      });

      const cancelBtn = wrapper.findAll('button').find(b => b.text().includes('Batal'));
      expect(cancelBtn?.exists()).toBe(true);
      await cancelBtn?.trigger('click');
      expect(wrapper.emitted('close')).toBeTruthy();

      const closeHeaderBtn = wrapper.find('button[class*="rounded-full"]');
      expect(closeHeaderBtn.exists()).toBe(true);
      await closeHeaderBtn.trigger('click');
      expect(wrapper.emitted('close')!.length).toBe(2);
    });
  });
});
