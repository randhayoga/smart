<script setup lang="ts">
/**
 * External Signed Approval Page
 * Allows designated managers to approve or reject requests via authenticated/signed URL tokens without logging in.
 */
import { ref, computed } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import { Button } from '@/Components/ui/button';
import { ScrollArea } from "@/Components/ui/scroll-area";
import AssetItemCard from '@/Components/AssetItemCard.vue';
import { 
  CheckCircle2, 
  XCircle, 
  Loader2, 
  ShieldCheck
} from 'lucide-vue-next';

// --- Data Types & Props ---
interface RequestItem {
  id: number;
  barang_id?: number;
  subcategory: string;
  brand: string;
  name?: string;
  spec: string;
  quantity: number;
  stockQuantity?: number;
  imageUrl?: string;
  category: string;
  assets?: string[];
  is_consumable?: boolean;
  uom?: string;
  status?: string;
}

interface ApprovalDetail {
  approver_name?: string;
  decision?: 'approve' | 'reject';
  note?: string;
  decided_at?: string;
}

interface ExternalRequest {
  id: number;
  number: string;
  type: string;
  requester: string;
  utilization: 'corporate' | 'project';
  destination: string;
  borrowPeriod?: string | null;
  reasoning: string;
  status: 'wait' | 'approve' | 'reject' | 'confirm' | 'handover' | 'success' | string;
  rawStatus: string;
  createdAt: string;
  items: RequestItem[];
  approval?: ApprovalDetail | null;
}

const props = defineProps<{
  request: ExternalRequest;
}>();

// --- Form & Decision State ---
const actionNote = ref('');

const form = useForm({
  action: 'approve',
  note: '',
});

/** Whether the request is currently awaiting manager decision */
const isPending = computed(() => props.request.rawStatus === 'wait');

/** Submit manager approval or rejection decision */
const submitDecision = (action: 'approve' | 'reject') => {
  form.action = action;
  form.note = actionNote.value;

  form.post(window.location.href, {
    preserveScroll: true,
  });
};

/** Formats request detail fields for display */
const getRequestFields = (req: ExternalRequest) => {
  const fields: { label: string; value: string }[] = [
    { label: 'Nomor', value: req.number },
    { label: 'Pemohon', value: req.requester || '-' },
    { label: 'Pemanfaatan', value: req.destination || '-' },
  ];

  if (req.type?.toLowerCase() === 'peminjaman' && req.borrowPeriod) {
    fields.push({ label: 'Durasi', value: req.borrowPeriod });
  }

  if (req.reasoning) {
    fields.push({ label: 'Alasan', value: req.reasoning });
  }

  return fields;
};
</script>

<template>
  <Head :title="`Persetujuan ${request.type} #${request.number} - SMART`" />

  <div class="min-h-screen bg-slate-50 flex flex-col items-center py-8 px-4 sm:px-6 lg:px-8">
    <div class="bg-card w-full max-w-2xl rounded-[14px] shadow-2xl overflow-hidden flex flex-col border border-border">
      <!-- Gradient Accent Header -->
      <div class="h-1.5 w-full bg-gradient-to-r from-primary to-indigo-600"></div>

      <!-- App Header Bar -->
      <div class="flex items-center p-1 justify-between border-b border-border">
        <div class="flex items-center gap-3 py-2 px-5">
          <ApplicationLogo class="h-9 w-9 rounded-lg shrink-0 object-contain" />
          <div>
            <h2 class="text-base font-bold text-primary leading-tight">
              SMART
            </h2>
            <p class="text-xs text-muted-foreground">
              Sistem Manajemen Aset & Request Tracking
            </p>
          </div>
        </div>
      </div>

      <!-- Modal Body (same structure as line 160-246 of ApprovalModal) -->
      <div class="p-6 flex flex-col items-center text-center space-y-4 flex-grow overflow-y-auto">
        <!-- Requests Container -->
        <div class="w-full space-y-6">
          <!-- Single Request Selection Layout -->
          <div class="space-y-4 w-full">
            <!-- Single Item Info Details (matching DeleteConfirmationModal / ApprovalModal) -->
            <div class="p-3 rounded-[14px] bg-muted/40 border border-border text-left space-y-2.5 w-full">
              <div 
                v-for="field in getRequestFields(request)" 
                :key="field.label" 
                class="grid grid-cols-12 gap-2 text-sm border-b border-border/50 last:border-0 pb-2 last:pb-0"
              >
                <span class="col-span-4 text-muted-foreground font-medium">{{ field.label }}</span>
                <span class="col-span-8 text-foreground font-semibold text-right break-words">
                  {{ field.value }}
                </span>
              </div>
            </div>

            <!-- Card Daftar Barang -->
            <div class="text-left w-full space-y-2">
              <p class="text-xs text-muted-foreground font-medium">Daftar Barang:</p>
              
              <ScrollArea class="max-h-[14rem] sm:max-h-[16rem] h-fit border border-border rounded-[0.875rem] bg-card [&>div]:max-h-[14rem] sm:[&>div]:max-h-[16rem]">
                <div class="p-3 sm:p-4 space-y-3">
                  <AssetItemCard 
                    v-for="item in request.items" 
                    :key="item.id" 
                    :brand="item.brand && item.brand !== '-' ? (item.name && item.name !== 'Tidak Spesifik' ? `${item.brand} ${item.name} ${item.spec || ''}` : `${item.brand} ${item.spec || ''}`) : (item.subcategory || item.name || 'Barang')"
                    :category="item.category"
                    :subcategory="item.subcategory"
                    :quantity="item.quantity"
                    :uom="item.uom || 'satuan'"
                    :assets="item.assets || []"
                    :imageUrl="item.imageUrl"
                    :status="item.status"
                    :is-consumable="item.is_consumable"
                  />
                </div>
              </ScrollArea>
            </div>
          </div>
        </div>

        <!-- Input Catatan/Alasan (when pending) -->
        <div v-if="isPending" class="space-y-1.5 text-left w-full pt-1">
          <label class="text-xs text-muted-foreground font-medium block">Catatan / Alasan (Opsional)</label>
          <textarea
            v-model="actionNote"
            placeholder="Masukkan catatan persetujuan atau alasan penolakan..."
            class="w-full h-16 text-sm border border-input rounded-[14px] bg-background text-foreground p-3 focus:outline-none focus:ring-1 focus:ring-primary focus:border-primary shadow-sm resize-none"
          ></textarea>
        </div>

        <!-- Resolved Decision Banner (when not pending) -->
        <div 
          v-else
          :class="[
            'p-4 rounded-xl border flex items-start gap-3.5 w-full text-left',
            request.rawStatus === 'approve' 
              ? 'bg-emerald-50/80 border-emerald-200 text-emerald-900' 
              : 'bg-rose-50/80 border-rose-200 text-rose-900'
          ]"
        >
          <CheckCircle2 v-if="request.rawStatus === 'approve'" class="h-5 w-5 text-emerald-600 shrink-0 mt-0.5" />
          <XCircle v-else class="h-5 w-5 text-rose-600 shrink-0 mt-0.5" />
          <div class="text-sm space-y-1">
            <div class="font-bold">
              Permohonan ini telah {{ request.rawStatus === 'approve' ? 'disetujui' : 'ditolak' }}.
            </div>
            <div class="text-xs opacity-90" v-if="request.approval?.approver_name">
              Diputuskan oleh: <span class="font-semibold">{{ request.approval.approver_name }}</span>
              <span v-if="request.approval.decided_at"> pada {{ request.approval.decided_at }}</span>
            </div>
            <div class="text-xs italic opacity-90" v-if="request.approval?.note">
              Catatan: "{{ request.approval.note }}"
            </div>
          </div>
        </div>
      </div>

      <!-- Modal Footer (direct decision confirmation buttons) -->
      <div v-if="isPending" class="py-3 px-4 bg-muted/30 border-t border-border shrink-0">
        <div class="flex items-center justify-end gap-3">
          <Button 
            @click="submitDecision('reject')"
            variant="destructive"
            :disabled="form.processing"
            class="px-5 active:scale-[0.98] relative"
          >
            <Loader2 v-if="form.processing && form.action === 'reject'" class="h-4 w-4 animate-spin mr-1.5" />
            Tolak
          </Button>
          <Button 
            @click="submitDecision('approve')"
            variant="success"
            :disabled="form.processing"
            class="px-5 active:scale-[0.98] relative"
          >
            <Loader2 v-if="form.processing && form.action === 'approve'" class="h-4 w-4 animate-spin mr-1.5" />
            Setujui
          </Button>
        </div>
      </div>

      <div v-else class="py-3 px-4 bg-muted/30 border-t border-border text-center">
        <p class="text-xs text-muted-foreground">
          Tindakan untuk permohonan ini telah selesai. Anda dapat menutup halaman ini.
        </p>
      </div>
    </div>

    <!-- Security Footer (placed outside of and below the card) -->
    <div class="mt-4 text-center text-xs text-muted-foreground flex items-center justify-center gap-1.5">
      <ShieldCheck class="h-4 w-4 text-primary shrink-0" />
      <span>Akses terverifikasi dengan Kode Autentikasi &bull; Sesi tanpa Log in</span>
    </div>
  </div>
</template>
