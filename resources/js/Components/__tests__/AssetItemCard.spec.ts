import { describe, it, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import AssetItemCard from '../AssetItemCard.vue';

describe('AssetItemCard.vue', () => {
  it('displays brand, model name, and spec when all are provided', () => {
    const wrapper = mount(AssetItemCard, {
      props: {
        brand: 'Lenovo',
        name: 'ThinkPad T14 Gen 3',
        spec: 'i7 16GB 512GB SSD',
        category: 'Elektronik',
        subcategory: 'Laptop',
        quantity: 1,
      },
      global: {
        mocks: {
          $t: (key: string) => key,
        },
      },
    });

    expect(wrapper.text()).toContain('Lenovo ThinkPad T14 Gen 3 i7 16GB 512GB SSD');
  });

  it('avoids duplicating tokens if brand already contains the spec or name', () => {
    const wrapper = mount(AssetItemCard, {
      props: {
        brand: 'Lenovo ThinkPad T14 Gen 3',
        name: 'ThinkPad T14 Gen 3',
        spec: '512GB SSD',
        quantity: 1,
      },
      global: {
        mocks: {
          $t: (key: string) => key,
        },
      },
    });

    expect(wrapper.text()).toContain('Lenovo ThinkPad T14 Gen 3 512GB SSD');
  });

  it('defaults quantity label to localized itemQuantity when not specified', () => {
    const wrapper = mount(AssetItemCard, {
      props: {
        brand: 'Dell',
        quantity: 5,
        uom: 'unit',
      },
      global: {
        mocks: {
          $t: (key: string) => (key === 'requests.itemQuantity' ? 'Item quantity' : key),
        },
      },
    });

    expect(wrapper.text()).toContain('Item quantity: 5 unit');
  });

  it('respects custom quantityLabel prop when specified', () => {
    const wrapper = mount(AssetItemCard, {
      props: {
        brand: 'Dell',
        quantity: 3,
        uom: 'pcs',
        quantityLabel: 'Kuantitas kustom',
      },
      global: {
        mocks: {
          $t: (key: string) => key,
        },
      },
    });

    expect(wrapper.text()).toContain('Kuantitas kustom: 3 pcs');
  });

  it('emits viewAsset when an allocated asset number is clicked in active assets list', async () => {
    const wrapper = mount(AssetItemCard, {
      props: {
        brand: 'Dell',
        quantity: 1,
        assets: ['AST-001', 'AST-002'],
        defaultOpen: true,
      },
      global: {
        mocks: {
          $t: (key: string) => key,
        },
      },
    });

    const assetButtons = wrapper.findAll('button').filter(b => b.text() === 'AST-001');
    expect(assetButtons.length).toBeGreaterThan(0);
    await assetButtons[0].trigger('click');

    expect(wrapper.emitted('viewAsset')).toBeTruthy();
    expect(wrapper.emitted('viewAsset')![0]).toEqual(['AST-001']);
  });

  it('emits viewAsset when an allocated slot asset number is clicked', async () => {
    const wrapper = mount(AssetItemCard, {
      props: {
        brand: 'Dell',
        quantity: 1,
        allocationSlots: [
          { slot_number: 1, asset_number: 'AST-SLOT-001', color: 'purple' },
        ],
        defaultOpen: true,
      },
      global: {
        mocks: {
          $t: (key: string) => key,
        },
      },
    });

    const slotButtons = wrapper.findAll('button').filter(b => b.text() === 'AST-SLOT-001');
    expect(slotButtons.length).toBeGreaterThan(0);
    await slotButtons[0].trigger('click');

    expect(wrapper.emitted('viewAsset')).toBeTruthy();
    expect(wrapper.emitted('viewAsset')![0]).toEqual(['AST-SLOT-001']);
  });
});
