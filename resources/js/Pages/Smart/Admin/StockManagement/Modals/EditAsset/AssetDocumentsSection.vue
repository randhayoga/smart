<script setup lang="ts">
/**
 * Asset documents section for EditAssetModal handling condition approval documents,
 * lost reports, admin notes, and BoD/BoC decision uploads.
 */
import { useI18n } from 'vue-i18n';
import { Button } from '@/Components/ui/button';
import { Checkbox } from '@/Components/ui/checkbox';
import { Field, FieldLabel, FieldContent, FieldError } from '@/Components/ui/field';

interface Props {
  form: any;
  errors: Record<string, string>;
  isDocumentDisabled: boolean;
  isBodBocFieldVisible: boolean;
  bodBocDecisionType: 'approval' | 'disapproval' | null;
  arrNeedApproval: string[];
  triggerMemoFileInput: () => void;
  triggerLostDocFileInput: () => void;
  triggerBodBocFileInput: () => void;
  triggerBodBocDisapprovalFileInput: () => void;
  handleMemoUpload: (e: any) => void;
  handleLostDocUpload: (e: any) => void;
  handleBodBocFileUpload: (e: any) => void;
  handleBodBocDisapprovalFileUpload: (e: any) => void;
  handleToggleBodBocDecision: (type: 'approval' | 'disapproval', checked: boolean) => void;
  viewMemoInNewTab: () => void;
  viewLostDocInNewTab: () => void;
  viewBodBocInNewTab: () => void;
  viewBodBocDisapprovalInNewTab: () => void;
}

const props = defineProps<Props>();

const { t } = useI18n();
</script>

<template>
  <div v-if="arrNeedApproval.includes(form.condition) || isBodBocFieldVisible" class="space-y-6 pt-4 border-t border-border">
    <!-- Condition Approval Documents: Memo, Lost Doc, Admin Note -->
    <template v-if="arrNeedApproval.includes(form.condition)">
      <div class="grid grid-cols-1 md:grid-cols-2 gap-x-12 gap-y-6">
        <!-- Memo Document Upload -->
        <Field :data-invalid="!!errors.memo_file || undefined" :data-disabled="isDocumentDisabled || undefined">
          <FieldLabel>
            <span>{{ t('inventory.memoDocument') }}<span v-if="!isDocumentDisabled" class="text-rose-500">*</span></span>
          </FieldLabel>
          <FieldContent>
            <div class="space-y-1">
              <div class="flex items-center gap-2">
                <div
                  class="flex-1 px-3 py-2 text-sm border rounded-[10px] truncate h-10 flex items-center"
                  :class="[
                    form.memo_file_name ? 'cursor-pointer hover:bg-muted/60 hover:text-primary transition-colors text-foreground font-medium underline decoration-dotted' : 'text-muted-foreground cursor-default',
                    errors.memo_file ? 'border-destructive' : 'border-input',
                    isDocumentDisabled ? 'bg-muted/30 text-muted-foreground cursor-not-allowed' : 'bg-muted/40'
                  ]"
                  @click="form.memo_file_name && viewMemoInNewTab()"
                >
                  {{ form.memo_file_name || t('inventory.noFileSelected') }}
                </div>
                <input
                  type="file"
                  id="edit-asset-memo-upload"
                  class="hidden"
                  accept=".pdf,.jpg,.jpeg,.png,.webp"
                  @change="handleMemoUpload"
                  :disabled="isDocumentDisabled"
                />
                <Button
                  type="button"
                  @click="triggerMemoFileInput"
                  size="lg"
                  class="shrink-0"
                  :disabled="isDocumentDisabled"
                >
                  {{ t('inventory.chooseDoc') }}
                </Button>
              </div>
              <p class="text-[10px] text-muted-foreground ml-1">{{ t('inventory.maxFileSize2Mb') }}</p>
            </div>
          </FieldContent>
          <FieldError v-if="errors.memo_file">{{ errors.memo_file }}</FieldError>
        </Field>

        <!-- Lost Document Upload (Only if Condition is Hilang) -->
        <Field v-if="form.condition === 'Hilang'" :data-invalid="!!errors.lost_doc_file || undefined" :data-disabled="isDocumentDisabled || undefined">
          <FieldLabel>
            <span>{{ t('inventory.lostDocFile') }}<span v-if="!isDocumentDisabled" class="text-rose-500">*</span></span>
          </FieldLabel>
          <FieldContent>
            <div class="space-y-1">
              <div class="flex items-center gap-2">
                <div
                  class="flex-1 px-3 py-2 text-sm border rounded-[10px] truncate h-10 flex items-center"
                  :class="[
                    form.lost_doc_file_name ? 'cursor-pointer hover:bg-muted/60 hover:text-primary transition-colors text-foreground font-medium underline decoration-dotted' : 'text-muted-foreground cursor-default',
                    errors.lost_doc_file ? 'border-destructive' : 'border-input',
                    isDocumentDisabled ? 'bg-muted/30 text-muted-foreground cursor-not-allowed' : 'bg-muted/40'
                  ]"
                  @click="form.lost_doc_file_name && viewLostDocInNewTab()"
                >
                  {{ form.lost_doc_file_name || t('inventory.noFileSelected') }}
                </div>
                <input
                  type="file"
                  id="edit-asset-lost-doc-upload"
                  class="hidden"
                  accept=".pdf,.jpg,.jpeg,.png,.webp"
                  @change="handleLostDocUpload"
                  :disabled="isDocumentDisabled"
                />
                <Button
                  type="button"
                  @click="triggerLostDocFileInput"
                  size="lg"
                  class="shrink-0"
                  :disabled="isDocumentDisabled"
                >
                  {{ t('inventory.chooseDoc') }}
                </Button>
              </div>
              <p class="text-[10px] text-muted-foreground ml-1">{{ t('inventory.maxFileSize2Mb') }}</p>
            </div>
          </FieldContent>
          <FieldError v-if="errors.lost_doc_file">{{ errors.lost_doc_file }}</FieldError>
        </Field>
      </div>

      <!-- Admin Note -->
      <Field :data-invalid="!!errors.admin_note || undefined" :data-disabled="isDocumentDisabled || undefined">
        <FieldLabel for="edit-unit-admin-note">
          <span>{{ t('inventory.adminNote') }}</span>
        </FieldLabel>
        <FieldContent>
          <textarea
            id="edit-unit-admin-note"
            v-model="form.admin_note"
            rows="3"
            maxlength="1000"
            :disabled="isDocumentDisabled"
            :placeholder="t('inventory.adminNotePlaceholder')"
            class="w-full px-4 py-2 text-sm border border-input rounded-[14px] bg-background focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-colors disabled:bg-muted/30 disabled:text-muted-foreground disabled:cursor-not-allowed resize-none"
          />
        </FieldContent>
        <FieldError v-if="errors.admin_note">{{ errors.admin_note }}</FieldError>
      </Field>
    </template>

    <!-- BoD / BoC Decision Section -->
    <template v-if="isBodBocFieldVisible">
      <div class="space-y-4 p-4 rounded-[14px] bg-muted/20 border border-border">
        <div class="flex items-center justify-between">
          <span class="text-sm font-semibold text-foreground">{{ t('inventory.bodBocDecision') }}</span>
          <div class="flex items-center gap-6">
            <!-- BoD Approval Checkbox -->
            <div class="flex items-center space-x-2">
              <Checkbox
                id="bod-boc-approval-checkbox"
                :model-value="bodBocDecisionType === 'approval'"
                @update:model-value="(val) => handleToggleBodBocDecision('approval', val === true)"
              />
              <label for="bod-boc-approval-checkbox" class="text-sm text-foreground cursor-pointer select-none">
                {{ t('inventory.bodBocApproval') }}
              </label>
            </div>

            <!-- BoD Disapproval Checkbox -->
            <div class="flex items-center space-x-2">
              <Checkbox
                id="bod-boc-disapproval-checkbox"
                :model-value="bodBocDecisionType === 'disapproval'"
                @update:model-value="(val) => handleToggleBodBocDecision('disapproval', val === true)"
              />
              <label for="bod-boc-disapproval-checkbox" class="text-sm text-foreground cursor-pointer select-none">
                {{ t('inventory.bodBocDisapproval') }}
              </label>
            </div>
          </div>
        </div>

        <!-- BoD Approval Document Upload -->
        <Field v-if="bodBocDecisionType === 'approval'" :data-invalid="!!errors.bod_boc_approval_file || undefined">
          <FieldLabel>
            <span>{{ t('inventory.bodBocApprovalDoc') }}<span class="text-rose-500">*</span></span>
          </FieldLabel>
          <FieldContent>
            <div class="space-y-1">
              <div class="flex items-center gap-2">
                <div
                  class="flex-1 px-3 py-2 text-sm border rounded-[10px] truncate h-10 flex items-center"
                  :class="[
                    form.bod_boc_approval_file_name ? 'cursor-pointer hover:bg-muted/60 hover:text-primary transition-colors text-foreground font-medium underline decoration-dotted' : 'text-muted-foreground cursor-default',
                    errors.bod_boc_approval_file ? 'border-destructive' : 'border-input',
                    'bg-muted/40'
                  ]"
                  @click="form.bod_boc_approval_file_name && viewBodBocInNewTab()"
                >
                  {{ form.bod_boc_approval_file_name || t('inventory.noFileSelected') }}
                </div>
                <input
                  type="file"
                  id="edit-asset-bod-boc-doc-upload"
                  class="hidden"
                  accept=".pdf,.jpg,.jpeg,.png,.webp"
                  @change="handleBodBocFileUpload"
                />
                <Button
                  type="button"
                  @click="triggerBodBocFileInput"
                  size="lg"
                  class="shrink-0"
                >
                  {{ t('inventory.chooseDoc') }}
                </Button>
              </div>
              <p class="text-[10px] text-muted-foreground ml-1">{{ t('inventory.maxFileSize2Mb') }}</p>
            </div>
          </FieldContent>
          <FieldError v-if="errors.bod_boc_approval_file">{{ errors.bod_boc_approval_file }}</FieldError>
        </Field>

        <!-- BoD Disapproval Document Upload -->
        <Field v-if="bodBocDecisionType === 'disapproval'" :data-invalid="!!errors.bod_boc_disapproval_file || undefined">
          <FieldLabel>
            <span>{{ t('inventory.bodBocDisapprovalDoc') }}<span class="text-rose-500">*</span></span>
          </FieldLabel>
          <FieldContent>
            <div class="space-y-1">
              <div class="flex items-center gap-2">
                <div
                  class="flex-1 px-3 py-2 text-sm border rounded-[10px] truncate h-10 flex items-center"
                  :class="[
                    form.bod_boc_disapproval_file_name ? 'cursor-pointer hover:bg-muted/60 hover:text-primary transition-colors text-foreground font-medium underline decoration-dotted' : 'text-muted-foreground cursor-default',
                    errors.bod_boc_disapproval_file ? 'border-destructive' : 'border-input',
                    'bg-muted/40'
                  ]"
                  @click="form.bod_boc_disapproval_file_name && viewBodBocDisapprovalInNewTab()"
                >
                  {{ form.bod_boc_disapproval_file_name || t('inventory.noFileSelected') }}
                </div>
                <input
                  type="file"
                  id="edit-asset-bod-boc-disapproval-doc-upload"
                  class="hidden"
                  accept=".pdf,.jpg,.jpeg,.png,.webp"
                  @change="handleBodBocDisapprovalFileUpload"
                />
                <Button
                  type="button"
                  @click="triggerBodBocDisapprovalFileInput"
                  size="lg"
                  class="shrink-0"
                >
                  {{ t('inventory.chooseDoc') }}
                </Button>
              </div>
              <p class="text-[10px] text-muted-foreground ml-1">{{ t('inventory.maxFileSize2Mb') }}</p>
            </div>
          </FieldContent>
          <FieldError v-if="errors.bod_boc_disapproval_file">{{ errors.bod_boc_disapproval_file }}</FieldError>
        </Field>
      </div>
    </template>
  </div>
</template>
