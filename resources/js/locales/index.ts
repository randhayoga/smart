import { createI18n } from 'vue-i18n';
import id from './id';
import en from './en';

export type MessageSchema = typeof id;

export const i18n = createI18n<[MessageSchema], 'id' | 'en'>({
  legacy: false,
  locale: 'id',
  fallbackLocale: 'id',
  messages: {
    id,
    en,
  },
});

/**
 * Updates the reactive active locale on the i18n instance.
 */
export function setI18nLanguage(locale: 'id' | 'en'): void {
  if (i18n.mode === 'legacy') {
    (i18n.global.locale as any) = locale;
  } else {
    (i18n.global.locale as any).value = locale;
  }
  document.querySelector('html')?.setAttribute('lang', locale);
}

export default i18n;
