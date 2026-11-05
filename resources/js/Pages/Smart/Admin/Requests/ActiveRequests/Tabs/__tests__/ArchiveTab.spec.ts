import { describe, it, expect, beforeEach, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import ArchiveTab from '../ArchiveTab.vue';
import { i18n, setI18nLanguage } from '@/locales';

describe('ArchiveTab.vue', () => {
  beforeEach(() => {
    setI18nLanguage('id');
    vi.clearAllMocks();
  });

  const sampleArchive = [
    {
      id: 50,
      number: '112026-0001',
      type: 'consumable',
      status: 'Sukses',
      requester: 'Budi Santoso',
      startTime: '01-11-2026 09:00',
      endTime: '01-11-2026 10:00',
    },
    {
      id: 51,
      number: '112026-0002',
      type: 'st',
      status: 'Ditolak',
      requester: 'Siti Rahma',
      startTime: '02-11-2026 14:00',
      endTime: '-',
    },
  ];

  it('renders archive table records and columns', () => {
    const wrapper = mount(ArchiveTab, {
      props: {
        archiveList: sampleArchive,
      },
      global: {
        plugins: [i18n],
      },
    });

    const text = wrapper.text();
    expect(text).toContain('112026-0001');
    expect(text).toContain('Budi Santoso');
    expect(text).toContain('112026-0002');
    expect(text).toContain('Siti Rahma');
  });

  it('filters by status correctly', async () => {
    const wrapper = mount(ArchiveTab, {
      props: {
        archiveList: sampleArchive,
      },
      global: {
        plugins: [i18n],
      },
    });

    expect(wrapper.text()).toContain('112026-0001');
    expect(wrapper.text()).toContain('112026-0002');

    // Filter by Ditolak
    (wrapper.vm as any).statusFilter = 'Ditolak';
    await wrapper.vm.$nextTick();

    expect(wrapper.text()).not.toContain('112026-0001');
    expect(wrapper.text()).toContain('112026-0002');
  });
});
