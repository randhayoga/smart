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

  describe('Asset Status Change Approval Mode', () => {
    const statusApprovalItem = {
      id: 10,
      asset_code: 'AST-001',
      brand: 'Lenovo',
      nama: 'ThinkPad T14',
      status_label: 'Pending',
      proposed_condition: 'Rusak',
      previous_status: 'Tersedia',
      previous_condition: 'Bagus',
    };

    it('renders approval confirmation when approving status change', () => {
      const wrapper = mountModal({
        itemCount: 1,
        itemName: 'Perubahan Status Aset',
        actionType: 'approved',
        itemData: [statusApprovalItem],
      });

      expect(wrapper.text()).toContain('Konfirmasi Approval');
      expect(wrapper.text()).toContain('Apakah Anda yakin untuk meng-approve 1 perubahan aset yang Anda pilih?');
      expect(wrapper.text()).not.toContain('Tindakan ini tidak dapat dibatalkan');
      expect(wrapper.text()).toContain('AST-001');
      expect(wrapper.text()).toContain('Rusak');
    });

    it('renders rejection confirmation when rejecting status change', () => {
      const wrapper = mountModal({
        itemCount: 1,
        itemName: 'Perubahan Status Aset',
        actionType: 'rejected',
        itemData: [statusApprovalItem],
      });

      expect(wrapper.text()).toContain('Konfirmasi Penolakan');
      expect(wrapper.text()).toContain('Apakah Anda yakin untuk menolak 1 perubahan aset yang Anda pilih?');
      expect(wrapper.text()).not.toContain('Tindakan ini tidak dapat dibatalkan');
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
        status: 'Tidak Aktif',
        condition: 'Belum Diverifikasi',
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
        status: 'Tidak Aktif',
        condition: 'Belum Diverifikasi',
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
