import { describe, it, expect, beforeEach } from 'vitest';
import { i18n, setI18nLanguage } from '../index';
import id from '../id';
import en from '../en';

describe('i18n Configuration and Dictionaries', () => {
  beforeEach(() => {
    setI18nLanguage('id');
  });

  it('should initialize with default locale id', () => {
    expect((i18n.global.locale as any).value).toBe('id');
  });

  it('should update active locale when setI18nLanguage is called', () => {
    setI18nLanguage('en');
    expect((i18n.global.locale as any).value).toBe('en');
    expect(document.querySelector('html')?.getAttribute('lang')).toBe('en');
  });

  it('should translate common keys correctly in both languages', () => {
    setI18nLanguage('id');
    expect(i18n.global.t('common.save')).toBe('Simpan');
    expect(i18n.global.t('common.cancel')).toBe('Batal');

    setI18nLanguage('en');
    expect(i18n.global.t('common.save')).toBe('Save');
    expect(i18n.global.t('common.cancel')).toBe('Cancel');
  });

  it('should support parameter interpolation', () => {
    setI18nLanguage('id');
    expect(
      i18n.global.t('common.notifications.newBadge', { count: 5 })
    ).toBe('5 baru');

    setI18nLanguage('en');
    expect(
      i18n.global.t('common.notifications.newBadge', { count: 5 })
    ).toBe('5 new');
  });

  it('should have parity between Indonesian and English dictionaries', () => {
    function getKeys(obj: Record<string, any>, prefix = ''): string[] {
      let keys: string[] = [];
      for (const [k, v] of Object.entries(obj)) {
        const fullKey = prefix ? `${prefix}.${k}` : k;
        if (typeof v === 'object' && v !== null) {
          keys = keys.concat(getKeys(v, fullKey));
        } else {
          keys.push(fullKey);
        }
      }
      return keys;
    }

    const idKeys = getKeys(id).sort();
    const enKeys = getKeys(en).sort();

    expect(idKeys).toEqual(enKeys);
  });
});
