<script setup lang="ts">
/**
 * External Signed Approval Page component for zero-login manager decision workflow.
 */
import { ref, computed } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import { Button } from '@/Components/ui/button';
import { 
  CheckCircle2, 
  XCircle, 
  Clock, 
  Check, 
  X, 
  Loader2, 
  ShieldCheck,
  AlertTriangle
} from 'lucide-vue-next';

interface RequestItem {
  id: number;
  name: string;
  spec?: string;
  category: string;
  quantity: number;
  uom: string;
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
  loanPeriod?: string | null;
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

const isActionModalOpen = ref(false);
const pendingAction = ref<'approve' | 'reject'>('approve');
const actionNote = ref('');

const form = useForm({
  action: 'approve',
  note: '',
});

const isPending = computed(() => props.request.rawStatus === 'wait');

const openConfirmModal = (action: 'approve' | 'reject') => {
  pendingAction.value = action;
  isActionModalOpen.value = true;
};

const closeConfirmModal = () => {
  isActionModalOpen.value = false;
};

const submitDecision = () => {
  form.action = pendingAction.value;
  form.note = actionNote.value;

  form.post(window.location.href, {
    preserveScroll: true,
    onSuccess: () => {
      closeConfirmModal();
    },
  });
};

const statusBadge = computed(() => {
  switch (props.request.rawStatus) {
    case 'wait':
      return {
        label: 'Menunggu Approval',
        class: 'bg-amber-50 text-amber-700 border-amber-200',
        icon: Clock,
      };
    case 'approve':
      return {
        label: 'Disetujui',
        class: 'bg-emerald-50 text-emerald-700 border-emerald-200',
        icon: CheckCircle2,
      };
    case 'reject':
      return {
        label: 'Ditolak',
        class: 'bg-rose-50 text-rose-700 border-rose-200',
        icon: XCircle,
      };
    default:
      return {
        label: props.request.status || 'Diproses',
        class: 'bg-slate-50 border-slate-200',
        icon: Clock,
      };
  }
});
</script>

<template>
  <Head :title="`Persetujuan ${request.type} #${request.number} - SMART`" />

  <div class="min-h-screen bg-slate-50 flex flex-col items-center py-8 px-4 sm:px-6 lg:px-8">
    <div class="w-full max-w-2xl bg-white rounded-2xl shadow-sm border border-slate-200/80 overflow-hidden">
      <!-- Gradient Accent Header -->
      <div class="h-1.5 w-full bg-gradient-to-r from-primary to-indigo-600"></div>

      <!-- App Header Bar -->
      <div class="px-6 py-3 border-b border-slate-100 flex items-center justify-between flex-wrap gap-4">
        <div class="flex items-center gap-3">
          <ApplicationLogo class="h-9 w-9 shrink-0 object-contain" />
          <div>
            <h2 class="text-base font-bold text-primary leading-tight">
              SMART
            </h2>
            <p class="text-xs text-muted-foreground">
              Sistem Manajemen Aset & Request Tracking
            </p>
          </div>
        </div>

        <div :class="['inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold border', statusBadge.class]">
          <component :is="statusBadge.icon" class="h-3.5 w-3.5 shrink-0" />
          <span>{{ statusBadge.label }}</span>
        </div>
      </div>

      <!-- Content Area -->
      <div class="px-8 py-5 space-y-6">
        <!-- Title & Subtitle -->
        <div>
          <h1 class="text-xl font-bold">
            Persetujuan {{ request.type }}
          </h1>
          <p class="text-sm text-muted-foreground mt-1">
            Tinjau rincian permohonan di bawah ini sebelum memberikan persetujuan atau penolakan.
          </p>
        </div>

        <!-- Resolved Decision Banner (when not in 'wait' status) -->
        <div 
          v-if="!isPending"
          :class="[
            'p-4 rounded-xl border flex items-start gap-3.5',
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

        <!-- Request Details Grid -->
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 bg-slate-50 p-4 sm:p-5 rounded-xl border border-slate-200/70 text-sm">
          <div>
            <span class="block text-xs font-semibold uppercase tracking-wider text-muted-foreground mb-0.5">Nomor Pengajuan</span>
            <span class="font-mono font-bold text-primary">{{ request.number }}</span>
          </div>

          <div>
            <span class="block text-xs font-semibold uppercase tracking-wider text-muted-foreground mb-0.5">Pemohon</span>
            <span class="font-semibold">{{ request.requester }}</span>
          </div>

          <div>
            <span class="block text-xs font-semibold uppercase tracking-wider text-muted-foreground mb-0.5">Pemanfaatan</span>
            <span class="font-semibold">{{ request.destination }}</span>
          </div>

          <div v-if="request.loanPeriod">
            <span class="block text-xs font-semibold uppercase tracking-wider text-muted-foreground mb-0.5">Periode Pinjam</span>
            <span class="font-semibold">{{ request.loanPeriod }}</span>
          </div>

          <div class="sm:col-span-2">
            <span class="block text-xs font-semibold uppercase tracking-wider text-muted-foreground mb-0.5">Alasan Pengajuan</span>
            <span class="italic">"{{ request.reasoning }}"</span>
          </div>
        </div>

        <!-- Items Table -->
        <div>
          <h3 class="text-sm font-bold mb-2.5">
            Daftar Barang yang Diajukan
          </h3>
          <div class="border border-slate-200 rounded-xl overflow-hidden shadow-xs">
            <table class="w-full text-left text-sm">
              <thead class="bg-slate-50 text-muted-foreground text-xs font-semibold border-b border-slate-200">
                <tr>
                  <th class="py-2.5 px-3.5 text-center w-12">No</th>
                  <th class="py-2.5 px-3.5">Nama Barang</th>
                  <th class="py-2.5 px-3.5 hidden sm:table-cell">Kategori</th>
                  <th class="py-2.5 px-3.5 text-center w-24">Jumlah</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100">
                <tr v-for="(item, idx) in request.items" :key="item.id" class="hover:bg-slate-50/50">
                  <td class="py-3 px-3.5 text-center text-muted-foreground text-xs">{{ idx + 1 }}</td>
                  <td class="py-3 px-3.5">
                    <div class="font-semibold">{{ item.name }}</div>
                    <div v-if="item.spec" class="text-xs text-muted-foreground mt-0.5">
                      {{ item.spec }}
                    </div>
                  </td>
                  <td class="py-3 px-3.5 hidden sm:table-cell">
                    {{ item.category }}
                  </td>
                  <td class="py-3 px-3.5 text-center font-bold text-primary whitespace-nowrap">
                    {{ item.quantity }} {{ item.uom }}
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Action Panel (Visible only when status === 'wait') -->
        <div v-if="isPending" class="space-y-4 pt-2 border-t border-slate-100">
          <div>
            <h3 class="text-sm font-bold mb-2.5">
              Catatan Keputusan <span class="font-normal text-muted-foreground text-xs">(Opsional untuk persetujuan, disarankan jika menolak)</span>
            </h3>
            <textarea
              id="note"
              v-model="actionNote"
              rows="3"
              placeholder="Tuliskan catatan atau alasan di sini..."
              class="w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2.5 text-sm placeholder:text-muted-foreground focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all resize-none"
            ></textarea>
          </div>

          <div class="flex flex-col-reverse sm:flex-row items-center justify-end gap-3 pt-2">
            <Button
              type="button"
              variant="destructive"
              size="lg"
              class="w-full sm:w-auto"
              :disabled="form.processing"
              @click="openConfirmModal('reject')"
            >
              <X class="h-4 w-4 mr-2" />
              Tolak Permohonan
            </Button>

            <Button
              type="button"
              variant="primary"
              size="lg"
              class="w-full sm:w-auto"
              :disabled="form.processing"
              @click="openConfirmModal('approve')"
            >
              <Loader2 v-if="form.processing" class="h-4 w-4 animate-spin mr-2" />
              <Check v-else class="h-4 w-4 mr-2" />
              Setujui Permohonan
            </Button>
          </div>
        </div>

        <div v-else class="pt-4 text-center">
          <p class="text-xs text-muted-foreground">
            Tindakan untuk permohonan ini telah selesai. Anda dapat menutup halaman ini.
          </p>
        </div>
      </div>

      <!-- Footer Security Note -->
      <div class="py-4 px-6 bg-slate-50 border-t border-slate-100 text-center text-xs text-muted-foreground flex items-center justify-center gap-1.5">
        <ShieldCheck class="h-4 w-4 text-primary shrink-0" />
        <span>Akses terverifikasi dengan Kode Autentikasi &bull; Sesi tanpa Log in</span>
      </div>
    </div>

    <!-- Confirmation Modal -->
    <div 
      v-if="isActionModalOpen" 
      class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-xs transition-opacity"
    >
      <div class="bg-white rounded-2xl max-w-md w-full p-6 shadow-xl border border-slate-200 space-y-4 animate-in fade-in zoom-in-95 duration-150">
        <div class="flex items-center gap-3">
          <div 
            :class="[
              'h-10 w-10 rounded-xl flex items-center justify-center shrink-0',
              pendingAction === 'approve' ? 'bg-emerald-100 text-emerald-600' : 'bg-amber-100 text-amber-600'
            ]"
          >
            <AlertTriangle class="h-5 w-5" />
          </div>
          <div>
            <h3 class="text-base font-bold">
              Konfirmasi {{ pendingAction === 'approve' ? 'Persetujuan' : 'Penolakan' }}
            </h3>
            <p class="text-xs text-muted-foreground">
              Permohonan #{{ request.number }}
            </p>
          </div>
        </div>

        <p class="text-sm">
          Apakah Anda yakin ingin <strong :class="pendingAction === 'approve' ? 'text-emerald-600' : 'text-amber-600'">{{ pendingAction === 'approve' ? 'menyetujui' : 'menolak' }}</strong> permohonan ini? Tindakan ini akan dicatat ke riwayat status.
        </p>

        <div v-if="actionNote" class="p-3 bg-slate-50 rounded-lg text-xs italic">
          Catatan: "{{ actionNote }}"
        </div>

        <div class="flex items-center justify-end gap-3 pt-2">
          <Button
            type="button"
            variant="outline"
            size="sm"
            :disabled="form.processing"
            @click="closeConfirmModal"
          >
            Batal
          </Button>

          <Button
            type="button"
            :variant="pendingAction === 'approve' ? 'primary' : 'warning'"
            size="sm"
            :disabled="form.processing"
            @click="submitDecision"
          >
            <Loader2 v-if="form.processing" class="h-4 w-4 animate-spin mr-1.5" />
            <span>Ya, {{ pendingAction === 'approve' ? 'Setujui' : 'Tolak' }}</span>
          </Button>
        </div>
      </div>
    </div>
  </div>
</template>
