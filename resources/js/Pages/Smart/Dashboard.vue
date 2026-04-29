<script setup lang="ts">
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/Components/ui/card';
import { Button } from '@/Components/ui/button';
import { Badge } from '@/Components/ui/badge';
import { 
  Package, 
  FileText, 
  TrendingUp, 
  Users, 
  ArrowUpRight, 
  ArrowDownRight,
  Plus,
  Clock,
  CheckCircle2,
  AlertCircle
} from 'lucide-vue-next';

interface Props {
  user: {
    name: string;
    email: string;
  };
}

const props = defineProps<Props>();

// Sample stats data
const stats = [
  {
    title: 'Total Items',
    value: '1,234',
    change: '+12%',
    trend: 'up',
    icon: Package,
    color: 'primary',
  },
  {
    title: 'Pending Requests',
    value: '23',
    change: '-5%',
    trend: 'down',
    icon: FileText,
    color: 'secondary',
  },
  {
    title: 'Completed Today',
    value: '45',
    change: '+18%',
    trend: 'up',
    icon: TrendingUp,
    color: 'success',
  },
  {
    title: 'Active Users',
    value: '89',
    change: '+3%',
    trend: 'up',
    icon: Users,
    color: 'primary',
  },
];

// Sample recent activity
const recentActivity = [
  {
    id: 1,
    title: 'New stock request submitted',
    description: 'Request #1234 for Office Supplies',
    time: '5 mins ago',
    status: 'pending',
    icon: Clock,
  },
  {
    id: 2,
    title: 'Request approved',
    description: 'Request #1230 has been approved',
    time: '1 hour ago',
    status: 'success',
    icon: CheckCircle2,
  },
  {
    id: 3,
    title: 'Low stock alert',
    description: 'Printer Paper running low (15 remaining)',
    time: '2 hours ago',
    status: 'warning',
    icon: AlertCircle,
  },
  {
    id: 4,
    title: 'Request completed',
    description: 'Request #1225 has been fulfilled',
    time: '3 hours ago',
    status: 'success',
    icon: CheckCircle2,
  },
];

const getStatusColor = (status: string) => {
  switch (status) {
    case 'success':
      return 'text-accent';
    case 'warning':
      return 'text-amber-500';
    case 'pending':
      return 'text-primary';
    default:
      return 'text-muted-foreground';
  }
};
</script>

<template>
  <AppLayout title="Dashboard">
    <template #header>
      <div class="flex flex-col gap-3 sm:gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h1 class="text-lg sm:text-2xl lg:text-3xl font-bold leading-tight">
            Welcome, <span class="text-gradient-primary">{{ user?.name || 'User' }}</span>
          </h1>
          <p class="text-sm sm:text-base text-muted-foreground mt-1">
            Here's what's happening with your inventory today.
          </p>
        </div>
        <div class="flex gap-2 sm:gap-3">
          <Button class="bg-gradient-primary hover:opacity-90 shadow-button text-sm sm:text-base px-3 sm:px-4">
            <Plus class="mr-1 sm:mr-2 h-4 w-4" />
            <span class="hidden min-[360px]:inline">New Request</span>
            <span class="min-[360px]:hidden">New</span>
          </Button>
        </div>
      </div>
    </template>
    
    <!-- Stats Grid -->
    <div class="grid gap-3 sm:gap-4 lg:gap-6 grid-cols-2 lg:grid-cols-4 mb-6 sm:mb-8">
      <Card 
        v-for="(stat, index) in stats" 
        :key="index"
        class="card-elevated group"
      >
        <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-1 sm:pb-2 p-3 sm:p-6">
          <CardTitle class="text-sm font-medium text-muted-foreground">
            {{ stat.title }}
          </CardTitle>
          <div 
            class="h-8 w-8 sm:h-10 sm:w-10 rounded-lg flex items-center justify-center transition-transform group-hover:scale-110"
            :class="[
              stat.color === 'primary' ? 'bg-primary/10 text-primary' :
              stat.color === 'secondary' ? 'bg-secondary/10 text-secondary' :
              'bg-accent/10 text-accent'
            ]"
          >
            <component :is="stat.icon" class="h-4 w-4 sm:h-5 sm:w-5" />
          </div>
        </CardHeader>
        <CardContent class="p-3 pt-0 sm:p-6 sm:pt-0">
          <div class="text-2xl sm:text-3xl font-bold">{{ stat.value }}</div>
          <div class="flex items-center gap-1 mt-1 text-xs sm:text-sm">
            <span 
              :class="stat.trend === 'up' ? 'text-accent' : 'text-destructive'"
              class="flex items-center font-medium"
            >
              <ArrowUpRight v-if="stat.trend === 'up'" class="h-3 w-3 sm:h-4 sm:w-4" />
              <ArrowDownRight v-else class="h-3 w-3 sm:h-4 sm:w-4" />
              {{ stat.change }}
            </span>
            <span class="text-muted-foreground">from last month</span>
          </div>
        </CardContent>
      </Card>
    </div>
    
    <!-- Main content grid -->
    <div class="grid gap-4 sm:gap-6 grid-cols-1 lg:grid-cols-3">
      <!-- Recent Activity -->
      <Card class="card-elevated lg:col-span-2">
        <CardHeader>
          <div class="flex items-center justify-between">
            <div>
              <CardTitle class="text-base sm:text-lg">Recent Activity</CardTitle>
              <CardDescription class="text-xs sm:text-sm">Your latest stock management updates</CardDescription>
            </div>
            <Button variant="ghost" size="sm" class="text-primary text-xs sm:text-sm px-2 sm:px-3">
              View all
              <ArrowUpRight class="ml-1 h-3 w-3 sm:h-4 sm:w-4" />
            </Button>
          </div>
        </CardHeader>
        <CardContent>
          <div class="space-y-4">
            <div 
              v-for="activity in recentActivity" 
              :key="activity.id"
              class="flex items-start gap-3 sm:gap-4 p-3 rounded-lg hover:bg-muted/50 transition-colors"
            >
              <div 
                class="h-10 w-10 rounded-full flex items-center justify-center flex-shrink-0"
                :class="[
                  activity.status === 'success' ? 'bg-accent/10' :
                  activity.status === 'warning' ? 'bg-amber-500/10' :
                  'bg-primary/10'
                ]"
              >
                <component 
                  :is="activity.icon" 
                  class="h-5 w-5"
                  :class="getStatusColor(activity.status)"
                />
              </div>
              <div class="flex-1 min-w-0">
                <p class="font-medium text-sm">{{ activity.title }}</p>
                <p class="text-sm text-muted-foreground truncate">{{ activity.description }}</p>
              </div>
              <span class="text-xs text-muted-foreground whitespace-nowrap">{{ activity.time }}</span>
            </div>
          </div>
        </CardContent>
      </Card>
      
      <!-- Quick Actions -->
      <Card class="card-elevated">
        <CardHeader>
          <CardTitle>Quick Actions</CardTitle>
          <CardDescription>Frequently used operations</CardDescription>
        </CardHeader>
        <CardContent class="space-y-3">
          <Button variant="outline" class="w-full justify-start gap-3 h-auto py-3 whitespace-normal text-left">
            <div class="h-8 w-8 rounded-lg bg-primary/10 flex items-center justify-center flex-shrink-0">
              <Plus class="h-4 w-4 text-primary" />
            </div>
            <span>Create New Request</span>
          </Button>
          <Button variant="outline" class="w-full justify-start gap-3 h-auto py-3 whitespace-normal text-left">
            <div class="h-8 w-8 rounded-lg bg-secondary/10 flex items-center justify-center flex-shrink-0">
              <Package class="h-4 w-4 text-secondary" />
            </div>
            <span>Add Stock Item</span>
          </Button>
          <Button variant="outline" class="w-full justify-start gap-3 h-auto py-3 whitespace-normal text-left">
            <div class="h-8 w-8 rounded-lg bg-accent/10 flex items-center justify-center flex-shrink-0">
              <FileText class="h-4 w-4 text-accent" />
            </div>
            <span>Generate Report</span>
          </Button>
          <Button variant="outline" class="w-full justify-start gap-3 h-auto py-3 whitespace-normal text-left">
            <div class="h-8 w-8 rounded-lg bg-amber-500/10 flex items-center justify-center flex-shrink-0">
              <Users class="h-4 w-4 text-amber-500" />
            </div>
            <span>Manage Users</span>
          </Button>
        </CardContent>
      </Card>
    </div>
  </AppLayout>
</template>
