import { describe, it, expect, beforeEach, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import DeletionApprovalModal from '../DeletionApprovalModal.vue';
import { i18n, setI18nLanguage } from '@/locales';

describe('DeletionApprovalModal.vue', () => {
  beforeEach(() => {
    setI18nLanguage('en');
  });

  const sampleApproval = {
    id: 1,
    unit_id: 10,
    asset_code: 'AST-2026-001',
    category: 'Elektronik',
    subcategory: 'Laptop',
    brand: 'Lenovo',
    nama: 'ThinkPad X1 Carbon',
    specification: 'Intel Core Ultra 7',
    decision: 'pending',
    proposed_condition: 'Rusak Total',
    previous_condition: 'Bagus',
    proposed_status: 'Rusak Total',
    previous_status: 'Tersedia',
    status_label: 'Rusak Total',
    note: null,
    admin_note: 'Screen broken beyond repair',
    requested_by: 'Admin User',
    requested_at: '01-10-2026 14:30',
    memo_url: 'memos/memo_penghapusan.pdf',
    lost_doc_url: null,
    asset_details: {
      id: 10,
      number: 'AST-2026-001',
      condition: 'Bagus',
      status: 'Tersedia',
      location: 'Gedung A',
      floor: 'Lt 2',
      room: 'R 201',
      lifecycles: [],
    },
  };

  const mountModal = (props: any) => {
    return mount(DeletionApprovalModal, {
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

  it('formats proposed condition as Total Damage in English and does not output raw key', () => {
    setI18nLanguage('en');
    const wrapper = mountModal({ mode: 'pending' });

    const text = wrapper.text();
    expect(text).toContain('Total Damage');
    expect(text).not.toContain('inventory.conditionHeavyDamage');
    expect(text).not.toContain('inventory.conditionTotalDamage');
  });

  it('formats proposed condition as Rusak Total in Indonesian', () => {
    setI18nLanguage('id');
    const wrapper = mountModal({ mode: 'pending' });

    const text = wrapper.text();
    expect(text).toContain('Rusak Total');
    expect(text).not.toContain('inventory.conditionHeavyDamage');
    expect(text).not.toContain('inventory.conditionTotalDamage');
  });

  it('formats Lost condition properly in English', () => {
    setI18nLanguage('en');
    const lostApproval = {
      ...sampleApproval,
      proposed_condition: 'Hilang',
      proposed_status: 'Hilang',
      status_label: 'Hilang',
      lost_doc_url: 'lost_docs/ba_kehilangan.pdf',
    };

    const wrapper = mountModal({ approval: lostApproval });

    const text = wrapper.text();
    expect(text).toContain('Lost');
    expect(text).not.toContain('inventory.conditionLost');
  });

  it('hides condition and storage location when asset is software category', () => {
    setI18nLanguage('en');
    const softwareApproval = {
      ...sampleApproval,
      category: 'Software',
      category_code: 'SOFT',
      asset_details: {
        ...sampleApproval.asset_details,
        model_category_code: 'SOFT',
        location: 'Server Room',
      },
    };

    const wrapper = mountModal({ approval: softwareApproval });
    const text = wrapper.text();

    expect(text).toContain('AST-2026-001');
    expect(text).toContain('Software');
    expect(text).toContain('Status:');
    expect(text).toContain('Available');
    expect(text).not.toContain('Proposed Condition:');
    expect(text).not.toContain('Previous Status & Condition:');
    expect(text).not.toContain('Storage Location:');
    expect(text).not.toContain('Location:');
    expect(text).not.toContain('Quality Control:');
  });

  it('renders Quality Control line and condition styling classes', () => {
    setI18nLanguage('id');
    const qcApproval = {
      ...sampleApproval,
      previous_condition: 'Bagus',
      qc_pass: true,
      last_qc: '2026-11-04 10:00:00',
      asset_details: {
        ...sampleApproval.asset_details,
        qc_pass: true,
        last_qc: '2026-11-04 10:00:00',
      },
    };

    const wrapper = mountModal({ approval: qcApproval, mode: 'pending' });
    const text = wrapper.text();

    expect(text).toContain('Quality Control:');
    expect(text).toContain('QC Pass (pada: 04-11-2026)');

    // Condition styling
    const emeraldCondition = wrapper.find('.text-emerald-600.font-semibold');
    expect(emeraldCondition.exists()).toBe(true);
    expect(emeraldCondition.text()).toBe('Bagus');

    const roseCondition = wrapper.find('.text-rose-600.font-semibold');
    expect(roseCondition.exists()).toBe(true);
    expect(roseCondition.text()).toBe('Rusak Total');
  });

  it('renders "Lokasi" instead of "Lokasi Penyimpanan" in Indonesian', () => {
    setI18nLanguage('id');
    const wrapper = mountModal({ mode: 'pending' });
    const text = wrapper.text();

    expect(text).toContain('Lokasi:');
    expect(text).not.toContain('Lokasi Penyimpanan:');
    expect(text).toContain('Gedung A, Lt 2, R 201');
  });

  it('only displays legacy number if it differs from asset code', () => {
    setI18nLanguage('id');
    // Same legacy number as asset_code
    const sameLegacyApproval = {
      ...sampleApproval,
      asset_details: {
        ...sampleApproval.asset_details,
        legacy_number: 'AST-2026-001',
      },
    };
    const wrapper1 = mountModal({ approval: sameLegacyApproval });
    expect(wrapper1.text()).not.toContain('Nomor Aset Lama:');

    // Different legacy number
    const diffLegacyApproval = {
      ...sampleApproval,
      asset_details: {
        ...sampleApproval.asset_details,
        legacy_number: 'LEGACY-999',
      },
    };
    const wrapper2 = mountModal({ approval: diffLegacyApproval });
    expect(wrapper2.text()).toContain('Nomor Aset Lama:');
    expect(wrapper2.text()).toContain('LEGACY-999');
  });

  it('uses asset comp_specification for COMP category code', () => {
    setI18nLanguage('id');
    const compApproval = {
      ...sampleApproval,
      category_code: 'COMP',
      specification: 'Model Default Spec',
      asset_details: {
        ...sampleApproval.asset_details,
        category_code: 'COMP',
        comp_specification: 'Intel i9 64GB 2TB Custom',
      },
    };
    const wrapper = mountModal({ approval: compApproval });
    expect(wrapper.text()).toContain('Intel i9 64GB 2TB Custom');
    expect(wrapper.text()).not.toContain('Model Default Spec');
  });

  it('formats project burden with project number and name', () => {
    setI18nLanguage('id');
    const projectApproval = {
      ...sampleApproval,
      asset_details: {
        ...sampleApproval.asset_details,
        burden: 'Project',
        project_no: 'PRJ-2026-001',
        project_name: 'Infrastructure Upgrade',
      },
    };
    const wrapper = mountModal({ approval: projectApproval });
    expect(wrapper.text()).toContain('Project [PRJ-2026-001] Infrastructure Upgrade');
  });

  it('emits approve, reject, and triggers memo window in pending mode', async () => {
    setI18nLanguage('id');
    const windowOpenSpy = vi.spyOn(window, 'open').mockImplementation(() => null);
    const wrapper = mountModal({ mode: 'pending' });

    const buttons = wrapper.findAll('button');
    const approveBtn = buttons.find(b => b.text().includes('Approve'));
    const rejectBtn = buttons.find(b => b.text().includes('Tolak'));
    const memoBtn = buttons.find(b => b.text().includes('Buka Berita Acara / Memo'));

    expect(approveBtn?.exists()).toBe(true);
    expect(rejectBtn?.exists()).toBe(true);
    expect(memoBtn?.exists()).toBe(true);

    await approveBtn?.trigger('click');
    expect(wrapper.emitted('approve')).toBeTruthy();

    await rejectBtn?.trigger('click');
    expect(wrapper.emitted('reject')).toBeTruthy();

    await memoBtn?.trigger('click');
    expect(windowOpenSpy).toHaveBeenCalledWith('/media/memos/memo_penghapusan.pdf', '_blank');
    windowOpenSpy.mockRestore();
  });
});
