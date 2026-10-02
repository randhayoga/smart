import { describe, it, expect, beforeEach, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import ApprovalAktivasiModal from '../ApprovalAktivasiModal.vue';
import { i18n, setI18nLanguage } from '@/locales';

describe('ApprovalAktivasiModal.vue', () => {
  beforeEach(() => {
    setI18nLanguage('id');
  });

  const sampleApproval = {
    id: 1,
    unit_id: 10,
    asset_code: 'AST-2026-001',
    category: 'Elektronik',
    subcategory: 'Laptop',
    brand: 'Lenovo',
    nama: 'ThinkPad X1 Carbon',
    specification: 'Intel Core Ultra 7, 32GB RAM, 1TB SSD',
    decision: 'pending',
    note: null,
    requested_by: 'Admin User',
    requested_at: '01-10-2026 14:30',
    memo_url: 'memos/memo_aktivasi.pdf',
    unit_details: {
      id: 10,
      barang_code: 'BRG-001',
      barang_unit: 'Unit',
      lot_code: 'LOT-2026-01',
      organizer: 'PT Mitra Inti',
      date_of_receipt: '2026-09-01',
      age: 0,
      vendor: 'Vendor Utama',
      po_number: 'PO-2026-0901',
      price: 25000000,
      status: 'Belum Diverifikasi',
      condition: 'Bagus',
      image_url: null,
      vehicle_registration: null,
      location: 'Gd. Menara',
      floor: 'Lt. 3',
      room: 'IT Room',
      lifecycles: [
        {
          waktu: '01-10-2026 14:30',
          status: 'Belum Diverifikasi',
          action_type: 'Registrasi',
          aktor: 'Admin: John Doe',
          durasi: '1 hari',
          catatan: 'Registrasi unit aset baru',
        },
      ],
    },
  };

  const mountModal = (props: any) => {
    return mount(ApprovalAktivasiModal, {
      props: {
        open: true,
        approval: sampleApproval,
        mode: 'pending',
        ...props,
      },
      global: {
        plugins: [i18n],
        stubs: {
          Teleport: true,
          Transition: false,
        },
      },
    });
  };

  describe('Pending Mode', () => {
    it('renders asset specifications and LOT information', () => {
      const wrapper = mountModal({ mode: 'pending' });

      expect(wrapper.text()).toContain('BRG-001');
      expect(wrapper.text()).toContain('Lenovo');
      expect(wrapper.text()).toContain('ThinkPad X1 Carbon');
      expect(wrapper.text()).toContain('LOT-2026-01');
      expect(wrapper.text()).toContain('PO-2026-0901');
      expect(wrapper.text()).toContain('Rp25.000.000');
    });

    it('renders status and condition badges in pending mode', () => {
      const wrapper = mountModal({ mode: 'pending' });

      expect(wrapper.text()).toContain('Status & Kondisi');
      expect(wrapper.text()).toContain('Belum Diverifikasi');
      expect(wrapper.text()).toContain('Bagus');
    });

    it('renders approve, reject, back, and memo buttons in pending mode', () => {
      const wrapper = mountModal({ mode: 'pending' });

      const buttons = wrapper.findAll('button');
      const approveBtn = buttons.find(b => b.text().includes('Approve'));
      const rejectBtn = buttons.find(b => b.text().includes('Tolak'));
      const memoBtn = buttons.find(b => b.text().includes('Buka Berita Acara / Memo'));
      const backBtn = buttons.find(b => b.text().includes('Kembali'));

      expect(approveBtn?.exists()).toBe(true);
      expect(rejectBtn?.exists()).toBe(true);
      expect(memoBtn?.exists()).toBe(true);
      expect(backBtn?.exists()).toBe(true);
    });

    it('emits approve when Approve button is clicked', async () => {
      const wrapper = mountModal({ mode: 'pending' });

      const approveBtn = wrapper.findAll('button').find(b => b.text().includes('Approve'));
      await approveBtn?.trigger('click');

      expect(wrapper.emitted('approve')).toBeTruthy();
    });

    it('emits reject when Tolak button is clicked', async () => {
      const wrapper = mountModal({ mode: 'pending' });

      const rejectBtn = wrapper.findAll('button').find(b => b.text().includes('Tolak'));
      await rejectBtn?.trigger('click');

      expect(wrapper.emitted('reject')).toBeTruthy();
    });

    it('opens memo in a new window when memo button is clicked', async () => {
      const windowOpenSpy = vi.spyOn(window, 'open').mockImplementation(() => null);
      const wrapper = mountModal({ mode: 'pending' });

      const memoBtn = wrapper.findAll('button').find(b => b.text().includes('Buka Berita Acara / Memo'));
      await memoBtn?.trigger('click');

      expect(windowOpenSpy).toHaveBeenCalledWith('/media/memos/memo_aktivasi.pdf', '_blank');
      windowOpenSpy.mockRestore();
    });
  });

  describe('Decided Mode', () => {
    it('renders decision label and manager notes, but not approve/reject buttons', () => {
      const decidedApproval = {
        ...sampleApproval,
        decision: 'approved',
        note: 'Approved for production use',
        unit_details: {
          ...sampleApproval.unit_details,
          status: 'Tersedia',
          condition: 'Bagus',
        },
      };

      const wrapper = mountModal({
        mode: 'decided',
        approval: decidedApproval,
      });

      expect(wrapper.text()).toContain('Disetujui');
      expect(wrapper.text()).toContain('Status & Kondisi');
      expect(wrapper.text()).toContain('Tersedia');
      expect(wrapper.text()).toContain('Bagus');
      expect(wrapper.text()).toContain('Catatan Manager');
      expect(wrapper.text()).toContain('Approved for production use');

      const buttons = wrapper.findAll('button');
      const approveBtn = buttons.find(b => b.text().includes('Approve'));
      const rejectBtn = buttons.find(b => b.text().includes('Tolak'));
      expect(approveBtn).toBeUndefined();
      expect(rejectBtn).toBeUndefined();
    });
  });

  describe('Vehicle TNKB Display', () => {
    it('renders vehicle registration plate when category is vehicle', () => {
      const vehicleApproval = {
        ...sampleApproval,
        category: 'Kendaraan',
        subcategory: 'Mobil Operasional',
        unit_details: {
          ...sampleApproval.unit_details,
          vehicle_registration: 'B 1234 XYZ',
        },
      };

      const wrapper = mountModal({
        approval: vehicleApproval,
        mode: 'pending',
      });

      expect(wrapper.text()).toContain('Nopol:');
      expect(wrapper.text()).toContain('B 1234 XYZ');
    });
  });
});
