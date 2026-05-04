<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { Loader2, ArrowRight } from 'lucide-vue-next';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';

defineProps<{
  canResetPassword?: boolean;
  status?: string;
}>();

const form = useForm({
  username: '',
  password: '',
  remember: false,
});

const submit = () => {
  form.post(route('login'), {
    onFinish: () => {
      form.reset('password');
    },
  });
};
</script>

<template>
  <Head title="Log in" />
  
  <div class="min-h-screen flex items-center justify-center bg-background p-4 relative overflow-hidden">
    <!-- Background decorative elements -->
    <div class="fixed inset-0 overflow-hidden pointer-events-none">
      <div class="blob-primary w-[600px] h-[600px] -top-64 -left-64 opacity-30"></div>
      <div class="blob-secondary w-[500px] h-[500px] top-1/2 -right-48 opacity-25"></div>
      <div class="blob-primary w-[400px] h-[400px] -bottom-32 left-1/3 opacity-20"></div>
    </div>
    
    <!-- Login Card (HTML Version) -->
    <div class="w-full max-w-md relative z-10 bg-card rounded-xl border border-border/50 shadow-[0_25px_50px_-12px_rgba(79,70,229,0.25)] overflow-hidden">
      <div class="p-6 text-center pb-2 pt-8">
        <!-- Logo -->
        <div class="flex justify-center mb-4">
          <div class="h-16 w-16 rounded-xl flex items-center justify-center overflow-hidden">
            <ApplicationLogo class="h-full w-full object-contain" />
          </div>
        </div>
        
        <h3 class="text-2xl font-bold tracking-tight">
          Selamat Datang di <span class="text-gradient-primary">SMART</span>
        </h3>
        <p class="text-sm text-muted-foreground mt-2">
          Stock Management And Request Tracking
        </p>
      </div>
      
      <div class="p-6 pt-4">
        <!-- Status message -->
        <div v-if="status" class="mb-4 p-3 rounded-lg bg-accent/10 text-accent text-sm text-center font-medium">
          {{ status }}
        </div>
        
        <form @submit.prevent="submit" class="space-y-5">
          <!-- Username / NPK -->
          <div class="space-y-2">
            <label for="username" class="text-sm font-medium leading-none peer-disabled:cursor-not-allowed peer-disabled:opacity-70">
                NPK
            </label>
            <input
              id="username"
              type="text"
              v-model="form.username"
              placeholder="Ketik NPK Anda"
              required
              autofocus
              autocomplete="username"
              class="flex h-11 w-full rounded-lg border border-input bg-background px-3 py-2 text-sm ring-offset-background file:border-0 file:bg-transparent file:text-sm file:font-medium placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary/20 focus-visible:border-primary disabled:cursor-not-allowed disabled:opacity-50 transition-all"
              :class="{ 'border-destructive focus-visible:ring-destructive/20': form.errors.username }"
            />
            <p v-if="form.errors.username" class="text-sm text-destructive font-medium">
              {{ form.errors.username }}
            </p>
          </div>
          
          <!-- Password -->
          <div class="space-y-2">
            <div class="flex items-center justify-between">
              <label for="password" class="text-sm font-medium leading-none peer-disabled:cursor-not-allowed peer-disabled:opacity-70">Kata Sandi</label>
              <!--
              <Link
                v-if="canResetPassword"
                :href="route('password.request')"
                class="text-sm text-primary hover:text-primary/80 font-medium transition-colors"
              >
                Forgot password?
              </Link>
              -->
            </div>
            <input
              id="password"
              type="password"
              v-model="form.password"
              placeholder="••••••••"
              required
              autocomplete="current-password"
              class="flex h-11 w-full rounded-lg border border-input bg-background px-3 py-2 text-sm ring-offset-background file:border-0 file:bg-transparent file:text-sm file:font-medium placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary/20 focus-visible:border-primary disabled:cursor-not-allowed disabled:opacity-50 transition-all"
              :class="{ 'border-destructive focus-visible:ring-destructive/20': form.errors.password }"
            />
            <p v-if="form.errors.password" class="text-sm text-destructive font-medium">
              {{ form.errors.password }}
            </p>
          </div>
          
          <!-- Remember me removed - not allowed in this project -->
          
          <!-- Submit button -->
          <button
            type="submit"
            class="inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-lg text-sm font-medium transition-colors focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring disabled:pointer-events-none disabled:opacity-50 w-full h-11 bg-gradient-primary text-primary-foreground hover:opacity-90 shadow-button"
            :disabled="form.processing"
          >
            <Loader2 v-if="form.processing" class="mr-2 h-5 w-5 animate-spin" />
            <template v-else>
              <span>Masuk</span>
              <ArrowRight class="h-5 w-5 shrink-0" />
            </template>
          </button>
        </form>
        
        <!-- Register link hidden since controller removed -->
      </div>
    </div>
    
    <!-- Footer -->
    <div class="absolute bottom-4 left-0 right-0 text-center text-sm text-muted-foreground">
      © {{ new Date().getFullYear() }} Integrated Facilites Services, IT Dev Team
    </div>
  </div>
</template>
