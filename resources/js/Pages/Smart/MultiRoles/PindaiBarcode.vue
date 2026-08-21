<script setup lang="ts">
import { ref, onMounted, onUnmounted, nextTick } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import { Html5Qrcode } from 'html5-qrcode';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Breadcrumb, BreadcrumbList, BreadcrumbItem, BreadcrumbLink } from '@/Components/ui/breadcrumb';
import { Button } from '@/Components/ui/button';
import { Badge } from '@/Components/ui/badge';
import {
  Camera,
  CameraOff,
  Upload,
  QrCode,
  AlertCircle,
  AlertTriangle,
  CheckCircle2,
  RefreshCw,
  Zap,
  ZapOff,
  ShieldCheck,
  Image as ImageIcon
} from 'lucide-vue-next';

// State variables
const isScanning = ref(false);
const isInitializing = ref(true);
const scanError = ref<string | null>(null);
const scanSuccessMsg = ref<string | null>(null);
const fileScanning = ref(false);

// Camera management
const cameras = ref<{ id: string; label: string }[]>([]);
const selectedCameraId = ref<string>('');
const isTorchOn = ref(false);
const hasTorch = ref(false);

let html5QrCodeScanner: Html5Qrcode | null = null;
const fileInputRef = ref<HTMLInputElement | null>(null);

// Page Visibility Handler to pause/resume camera when tab active state changes
const handleVisibilityChange = async () => {
  if (document.hidden) {
    await stopScanner();
  } else if (!isScanning.value && !scanSuccessMsg.value && !fileScanning.value) {
    await startScanner();
  }
};

// Lifecycle Hooks
onMounted(async () => {
  document.addEventListener('visibilitychange', handleVisibilityChange);
  await nextTick();
  await initCameraList();
  await startScanner();
});

onUnmounted(async () => {
  document.removeEventListener('visibilitychange', handleVisibilityChange);
  await stopScanner();
});

// Initialize Camera Devices List
const initCameraList = async () => {
  try {
    const devices = await Html5Qrcode.getCameras();
    if (devices && devices.length > 0) {
      cameras.value = devices.map(d => ({
        id: d.id,
        label: d.label || `Kamera ${d.id.substring(0, 5)}...`
      }));
      // Default to last camera (usually back/environment camera on mobile)
      selectedCameraId.value = devices[devices.length - 1].id;
    }
  } catch (err: any) {
    console.warn('Gagal membaca daftar kamera:', err);
  }
};

// Start Live Camera QR Scanner
const startScanner = async () => {
  scanError.value = null;
  scanSuccessMsg.value = null;
  isInitializing.value = true;

  try {
    if (html5QrCodeScanner) {
      try {
        if (html5QrCodeScanner.isScanning) {
          await html5QrCodeScanner.stop();
        }
        html5QrCodeScanner.clear();
      } catch (cleanErr) {
        console.warn('Cleanup scanner warning:', cleanErr);
      }
      html5QrCodeScanner = null;
    }

    html5QrCodeScanner = new Html5Qrcode('qr-reader-container');

    const cameraConfig = selectedCameraId.value 
      ? selectedCameraId.value 
      : { facingMode: 'environment' };

    await html5QrCodeScanner.start(
      cameraConfig,
      {
        fps: 15,
        qrbox: (viewfinderWidth, viewfinderHeight) => {
          const minEdge = Math.min(viewfinderWidth, viewfinderHeight);
          const qrboxSize = Math.floor(minEdge * 0.75);
          return { width: Math.max(qrboxSize, 200), height: Math.max(qrboxSize, 200) };
        },
        aspectRatio: 1.0,
      },
      onScanSuccess,
      onScanError
    );

    isScanning.value = true;
    isInitializing.value = false;

    // Check torch / flashlight support
    try {
      const capabilities = html5QrCodeScanner.getRunningTrackCapabilities();
      hasTorch.value = !!(capabilities as any).torch;
    } catch {
      hasTorch.value = false;
    }
  } catch (err: any) {
    isScanning.value = false;
    isInitializing.value = false;
    scanError.value = 'Izin kamera ditolak atau kamera tidak ditemukan. Silakan izinkan akses kamera pada browser atau gunakan opsi Pindai dari Galeri di bawah.';
    console.error('Camera Scanner error:', err);
  }
};

// Stop Camera Stream
const stopScanner = async () => {
  if (html5QrCodeScanner) {
    try {
      if (html5QrCodeScanner.isScanning) {
        await html5QrCodeScanner.stop();
      }
      html5QrCodeScanner.clear();
    } catch (err) {
      console.warn('Stop scanner warning:', err);
    }
    html5QrCodeScanner = null;
  }
  isScanning.value = false;
  isTorchOn.value = false;
};

// Toggle Torch
const toggleTorch = async () => {
  if (!html5QrCodeScanner || !isScanning.value || !hasTorch.value) return;
  try {
    isTorchOn.value = !isTorchOn.value;
    await html5QrCodeScanner.applyVideoConstraints({
      advanced: [{ torch: isTorchOn.value } as any]
    });
  } catch (err) {
    console.warn('Torch failed:', err);
    isTorchOn.value = false;
  }
};

// Switch Selected Camera
const onCameraChange = async (event: Event) => {
  const target = event.target as HTMLSelectElement;
  selectedCameraId.value = target.value;
  await startScanner();
};

// QR Code Detection Success Handler
const onScanSuccess = async (decodedText: string) => {
  if (!decodedText) return;

  // Immediately stop camera for privacy and resource release
  await stopScanner();

  scanSuccessMsg.value = 'QR Code Berhasil Terdeteksi! Membuka halaman detail aset...';

  // Process scanned payload
  processScannedData(decodedText);
};

// Ignore frame decoding errors (fires continuously when frame has no QR)
const onScanError = (_errorMessage: string) => {
  // Silent frame miss
};

// Process Scanned Text & Route User safely
const processScannedData = (rawText: string) => {
  const cleanText = rawText.trim();
  if (!cleanText) return;

  // 1. If scanned text is a full URL containing /scan/... or /smart/scan/...
  const urlMatch = cleanText.match(/(?:\/smart)?\/scan\/([^\/?#]+)/);
  if (urlMatch && urlMatch[1]) {
    const code = decodeURIComponent(urlMatch[1]);
    router.visit(`/smart/scan/${encodeURIComponent(code)}`);
    return;
  }

  // 2. Direct unit code payload (e.g. 00001-ELK-IT-26) - scanner appends to /smart/scan/
  if (!cleanText.startsWith('http://') && !cleanText.startsWith('https://')) {
    router.visit(`/smart/scan/${encodeURIComponent(cleanText)}`);
    return;
  }

  // Fallback: If text doesn't match expected SMART QR format
  scanError.value = `Format QR Code tidak dikenali sebagai QR Aset SMART (${cleanText.substring(0, 35)}...)`;
};

// Handle File Selection (Scans QR directly in client memory without saving file)
const triggerFileInput = () => {
  if (fileInputRef.value) {
    fileInputRef.value.click();
  }
};

const handleFileUpload = async (event: Event) => {
  const target = event.target as HTMLInputElement;
  const files = target.files;
  if (!files || files.length === 0) return;

  const file = files[0];
  fileScanning.value = true;
  scanError.value = null;

  let fileScanner: Html5Qrcode | null = null;
  try {
    // Stop live camera if running
    await stopScanner();

    // Create temporary scanner instance for single file scan in-memory
    fileScanner = new Html5Qrcode('file-scanner-temp');
    const result = await fileScanner.scanFile(file, false);

    fileScanning.value = false;
    scanSuccessMsg.value = 'QR Code Gambar Terbaca!';
    processScannedData(result);
  } catch (err: any) {
    fileScanning.value = false;
    scanError.value = 'Tidak dapat menemukan QR Code pada gambar yang dipilih.';
    console.error('File scan error:', err);
  } finally {
    if (fileScanner) {
      try {
        fileScanner.clear();
      } catch (clearErr) {
        console.warn('File scanner clear warning:', clearErr);
      }
    }
    target.value = '';
  }
};
</script>

<template>
  <AppLayout title="Pindai Barcode">
    <div class="max-w-md mx-auto space-y-4">
      <Breadcrumb>
        <BreadcrumbList>
          <BreadcrumbItem>
            <BreadcrumbLink href="/smart/scan">Pindai Barcode</BreadcrumbLink>
          </BreadcrumbItem>
        </BreadcrumbList>
      </Breadcrumb>

      <div class="space-y-4">
        <!-- Main Card Container -->
        <div class="px-4 py-4 bg-card rounded-xl border border-border shadow-sm overflow-hidden space-y-3">
          
          <!-- Header Title Section -->
          <div class="flex flex-col gap-2">
            <div>
              <h2 class="text-lg font-bold text-foreground flex items-center">
                Pindai Barcode Aset
              </h2>
              <p class="text-xs text-muted-foreground mt-0.5">
                Arahkan kamera ke stiker QR Code aset untuk melihat informasi detail dan riwayat barang.
              </p>
            </div>

            <div class="flex flex-wrap items-center gap-2">
              <!-- Warning Badge for Desktop/Laptop screens -->
              <Badge variant="outline" class="hidden lg:flex w-fit bg-destructive/10 text-destructive border-destructive/20 px-3 py-1.5 items-center gap-1.5 font-medium text-xs">
                <AlertTriangle class="w-4 h-4 shrink-0" />
                Fitur ini didesain untuk digunakan pada Smartphone SobatRE
              </Badge>

              <!-- Privacy Badge -->
              <Badge variant="outline" class="w-fit bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border-emerald-500/20 px-3 py-1.5 flex items-center gap-1.5 font-medium text-xs">
                <ShieldCheck class="w-4 h-4 shrink-0" />
                Tenang, foto pemindai tidak kami simpan
              </Badge>
            </div>
          </div>

          <!-- Scanner Stack: Camera View + Gallery Upload -->
          <div class="grid grid-cols-1 gap-3 items-start">

            <!-- Main Live Camera Box -->
            <div class="flex flex-col items-center">
              <div class="w-full bg-muted/40 border border-border rounded-xl p-4 flex flex-col items-center">
                
                <!-- Camera Header Bar -->
                <div class="w-full flex items-center justify-between mb-3">
                  <span class="font-semibold text-foreground flex items-center gap-1.5">
                    <Camera class="w-5 h-5 text-primary" />
                    Kamera Pemindai
                  </span>

                  <div class="flex items-center gap-2">
                    <button
                      v-if="hasTorch && isScanning"
                      @click="toggleTorch"
                      :class="[
                        'px-2 py-1 rounded border text-xs font-medium transition-colors flex items-center gap-1',
                        isTorchOn 
                          ? 'bg-amber-500/20 text-amber-500 border-amber-500/40' 
                          : 'bg-background hover:bg-muted text-muted-foreground border-border'
                      ]"
                    >
                      <Zap v-if="!isTorchOn" class="w-3.5 h-3.5" />
                      <ZapOff v-else class="w-3.5 h-3.5" />
                      <span class="text-[11px]">Flash</span>
                    </button>

                    <select
                      v-if="cameras.length > 1"
                      :value="selectedCameraId"
                      @change="onCameraChange"
                      class="text-xs border border-border rounded bg-background px-2 py-1 text-foreground focus:outline-none focus:ring-1 focus:ring-primary"
                    >
                      <option v-for="cam in cameras" :key="cam.id" :value="cam.id">
                        {{ cam.label }}
                      </option>
                    </select>
                  </div>
                </div>

                <!-- Viewfinder Box -->
                <div class="relative w-full aspect-square bg-slate-950 rounded-lg overflow-hidden border border-slate-800 flex items-center justify-center shadow-inner">
                  
                  <div id="qr-reader-container" class="w-full h-full object-cover"></div>
                  <div id="file-scanner-temp" class="hidden"></div>

                  <!-- Corner Target Framing -->
                  <div v-if="isScanning" class="pointer-events-none absolute inset-0 p-6 flex flex-col justify-between">
                    <div class="flex justify-between">
                      <div class="w-7 h-7 border-t-4 border-l-4 border-primary rounded-tl"></div>
                      <div class="w-7 h-7 border-t-4 border-r-4 border-primary rounded-tr"></div>
                    </div>

                    <div class="w-full h-0.5 bg-gradient-to-r from-transparent via-primary to-transparent shadow-[0_0_12px_#3b82f6] animate-pulse"></div>

                    <div class="flex justify-between">
                      <div class="w-7 h-7 border-b-4 border-l-4 border-primary rounded-bl"></div>
                      <div class="w-7 h-7 border-b-4 border-r-4 border-primary rounded-br"></div>
                    </div>
                  </div>

                  <!-- Initializing Spinner -->
                  <div v-if="isInitializing && !scanError" class="absolute inset-0 bg-slate-950/90 flex flex-col items-center justify-center p-4 text-center z-10">
                    <RefreshCw class="w-8 h-8 text-primary animate-spin mb-2" />
                    <p class="text-sm font-medium text-slate-200">Memuat Kamera Pemindai...</p>
                    <p class="text-xs text-slate-400 mt-1">Izinkan akses kamera pada browser jika diminta.</p>
                  </div>

                  <!-- Inactive Camera Overlay -->
                  <div v-if="!isScanning && !isInitializing" class="absolute inset-0 bg-slate-950/95 flex flex-col items-center justify-center p-6 text-center z-10">
                    <CameraOff class="w-10 h-10 text-slate-500 mb-2" />
                    <p class="text-sm font-medium text-slate-300 mb-4">Kamera Nonaktif</p>
                    <Button @click="startScanner" variant="default" size="sm" class="gap-2">
                      <RefreshCw class="w-4 h-4" />
                      Coba Aktifkan Kamera Kembali
                    </Button>
                  </div>
                </div>

                <!-- Error Alert -->
                <div v-if="scanError" class="w-full mt-3 p-3 bg-destructive/10 border border-destructive/30 rounded-lg flex items-start gap-2 text-destructive text-xs">
                  <AlertCircle class="w-4 h-4 shrink-0 mt-0.5" />
                  <div class="flex-1">
                    <p class="font-semibold">Info Pemindaian</p>
                    <p class="mt-0.5 opacity-90">{{ scanError }}</p>
                  </div>
                </div>

                <!-- Success Alert -->
                <div v-if="scanSuccessMsg" class="w-full mt-3 p-3 bg-emerald-500/10 border border-emerald-500/30 rounded-lg flex items-center gap-2 text-emerald-600 dark:text-emerald-400 text-xs">
                  <CheckCircle2 class="w-4 h-4 shrink-0 animate-bounce" />
                  <p class="font-semibold">{{ scanSuccessMsg }}</p>
                </div>

              </div>
            </div>

            <!-- Upload Gallery Option -->
            <div class="space-y-4">
              <div class="bg-card rounded-xl border border-border p-4 shadow-sm space-y-3">
                <div>
                  <h3 class="text-base font-bold text-foreground flex items-center gap-2">
                    <ImageIcon class="w-5 h-5 text-primary" />
                    Pindai dari Galeri
                  </h3>
                  <p class="text-xs text-muted-foreground mt-1">
                    Pilih foto Barcode Aset yang tersimpan di perangkat Anda.
                  </p>
                </div>

                <input
                  ref="fileInputRef"
                  type="file"
                  accept="image/*"
                  class="hidden"
                  @change="handleFileUpload"
                />

                <Button
                  @click="triggerFileInput"
                  :disabled="fileScanning"
                  variant="primary"
                  class="w-full justify-center gap-2"
                >
                  <Upload v-if="!fileScanning" class="w-4 h-4" />
                  <RefreshCw v-else class="w-4 h-4 animate-spin" />
                  <span class="text-xs font-medium">
                    {{ fileScanning ? 'Membaca Gambar...' : 'Pilih Berkas Gambar' }}
                  </span>
                </Button>
              </div>
            </div>

          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
