import { describe, it, expect, beforeEach } from 'vitest';
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
});
