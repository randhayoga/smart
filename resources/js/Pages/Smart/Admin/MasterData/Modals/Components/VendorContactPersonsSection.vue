<script setup lang="ts">
/**
 * Vendor Contact Persons dynamic list section.
 * Supports adding, removing, and folding/unfolding individual contact person cards.
 */
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { ChevronDown, ChevronRight, Plus, Trash2 } from 'lucide-vue-next';
import { Button } from '@/Components/ui/button';
import { Field, FieldLabel, FieldContent, FieldError } from '@/Components/ui/field';

export interface VendorCpItem {
  id?: number;
  vendor_id?: number;
  contact_person: string;
  cp_email?: string | null;
  cp_phone?: string | null;
  is_collapsed?: boolean;
}

const props = defineProps<{
  modelValue: VendorCpItem[];
  errors?: Record<string, string>;
}>();

const emit = defineEmits<{
  (e: 'update:modelValue', value: VendorCpItem[]): void;
}>();

const { t } = useI18n();

const handlePhoneInput = (event: Event, index: number) => {
  const target = event.target as HTMLInputElement;
  const filtered = target.value.replace(/[^0-9+\-\s]/g, '');
  if (target.value !== filtered) {
    target.value = filtered;
  }
  const updated = [...props.modelValue];
  if (updated[index]) {
    updated[index] = {
      ...updated[index],
      cp_phone: filtered,
    };
    emit('update:modelValue', updated);
  }
};

const toggleCollapse = (index: number) => {
  const updated = [...props.modelValue];
  if (updated[index]) {
    updated[index] = {
      ...updated[index],
      is_collapsed: !updated[index].is_collapsed,
    };
    emit('update:modelValue', updated);
  }
};

const addContactPerson = () => {
  const updated = [
    ...props.modelValue,
    {
      contact_person: '',
      cp_email: '',
      cp_phone: '',
      is_collapsed: false,
    },
  ];
  emit('update:modelValue', updated);
};

const removeContactPerson = (index: number) => {
  const updated = props.modelValue.filter((_, i) => i !== index);
  emit('update:modelValue', updated);
};
</script>

<template>
  <div class="md:col-span-3 border-t pt-4 mt-2 space-y-3">
    <div class="flex items-center justify-between mb-2">
      <h4 class="text-sm font-semibold text-foreground">
        {{ t('masterData.fields.contactPersons') }}
      </h4>
      <Button
        type="button"
        variant="outline"
        size="sm"
        @click="addContactPerson"
        class="h-8 px-2.5 text-xs flex items-center gap-1.5"
      >
        <Plus class="h-3.5 w-3.5" />
        {{ t('masterData.fields.addContactPerson') }}
      </Button>
    </div>

    <!-- Contact Persons List -->
    <div v-if="modelValue.length === 0" class="text-center py-6 border border-dashed rounded-[14px] bg-muted/20">
      <p class="text-sm text-muted-foreground mb-2">
        {{ t('masterData.fields.contactPersons') }} belum ditambahkan.
      </p>
      <Button
        type="button"
        variant="outline"
        size="sm"
        @click="addContactPerson"
        class="text-xs"
      >
        <Plus class="h-3.5 w-3.5 mr-1" />
        {{ t('masterData.fields.addContactPerson') }}
      </Button>
    </div>

    <div
      v-for="(cp, index) in modelValue"
      :key="index"
      class="border rounded-[14px] bg-card/50 overflow-hidden transition-all duration-200"
      :class="[cp.is_collapsed ? 'border-input/60' : 'border-input shadow-xs']"
    >
      <!-- Section Header with Accordion Toggle & Remove -->
      <div
        data-testid="cp-accordion-header"
        class="flex items-center justify-between px-3.5 py-2.5 bg-muted/30 cursor-pointer select-none hover:bg-muted/50 transition-colors"
        @click="toggleCollapse(index)"
      >
        <div class="flex items-center gap-2">
          <button
            type="button"
            class="p-0.5 text-muted-foreground hover:text-foreground transition-transform"
            tabindex="-1"
          >
            <ChevronDown v-if="!cp.is_collapsed" class="h-4 w-4 text-foreground" />
            <ChevronRight v-else class="h-4 w-4 text-muted-foreground" />
          </button>
          <span class="text-sm font-medium text-foreground">
            {{ t('masterData.fields.contactPersonIndex', { index: index + 1 }) }}
          </span>
          <span
            v-if="cp.is_collapsed && cp.contact_person"
            class="text-xs text-muted-foreground truncate max-w-[180px] md:max-w-[280px]"
          >
            ({{ cp.contact_person }})
          </span>
        </div>

        <div class="flex items-center gap-1" @click.stop>
          <Button
            type="button"
            variant="ghost"
            size="sm"
            @click="removeContactPerson(index)"
            class="h-7 w-7 p-0 text-muted-foreground hover:text-destructive hover:bg-destructive/10 transition-colors"
            :title="t('masterData.fields.removeContactPerson')"
          >
            <Trash2 class="h-3.5 w-3.5" />
          </Button>
        </div>
      </div>

      <!-- Collapsible Body -->
      <div
        v-show="!cp.is_collapsed"
        class="p-4 grid grid-cols-1 md:grid-cols-3 gap-4 border-t border-input/40 bg-background"
      >
        <!-- Contact Person Name -->
        <Field>
          <FieldLabel>{{ t('masterData.fields.cpName') }}</FieldLabel>
          <FieldContent>
            <input
              type="text"
              v-model="cp.contact_person"
              maxlength="255"
              :placeholder="t('masterData.placeholders.cpName')"
              class="w-full px-3 py-2 text-sm border rounded-[14px] bg-background focus:outline-none focus:ring-2 transition-colors border-input focus:ring-primary/20 focus:border-primary"
            />
          </FieldContent>
        </Field>

        <!-- Contact Person Email -->
        <Field>
          <FieldLabel>{{ t('masterData.fields.cpEmail') }}</FieldLabel>
          <FieldContent>
            <input
              type="email"
              v-model="cp.cp_email"
              maxlength="255"
              :placeholder="t('masterData.placeholders.cpEmail')"
              class="w-full px-3 py-2 text-sm border rounded-[14px] bg-background focus:outline-none focus:ring-2 transition-colors border-input focus:ring-primary/20 focus:border-primary"
            />
          </FieldContent>
        </Field>

        <!-- Contact Person Phone -->
        <Field :data-invalid="!!(errors && (errors[`contact_persons.${index}.cp_phone`] || errors[`contact_persons_${index}_cp_phone`])) || undefined">
          <FieldLabel>{{ t('masterData.fields.cpPhone') }}</FieldLabel>
          <FieldContent>
            <input
              type="text"
              :value="cp.cp_phone"
              @input="handlePhoneInput($event, index)"
              maxlength="255"
              :placeholder="t('masterData.placeholders.cpPhone')"
              class="w-full px-3 py-2 text-sm border rounded-[14px] bg-background focus:outline-none focus:ring-2 transition-colors"
              :class="[(errors && (errors[`contact_persons.${index}.cp_phone`] || errors[`contact_persons_${index}_cp_phone`])) ? 'border-destructive focus:ring-destructive/20 focus:border-destructive' : 'border-input focus:ring-primary/20 focus:border-primary']"
            />
          </FieldContent>
          <FieldError v-if="errors && (errors[`contact_persons.${index}.cp_phone`] || errors[`contact_persons_${index}_cp_phone`])">
            {{ errors[`contact_persons.${index}.cp_phone`] || errors[`contact_persons_${index}_cp_phone`] }}
          </FieldError>
        </Field>
      </div>
    </div>
  </div>
</template>
