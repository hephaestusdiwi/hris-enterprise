import { createRouter, createWebHistory } from 'vue-router'
import DashboardView from '../views/DashboardView.vue'
import UserListView from '../views/UserListView.vue'
import CompanyListView from '../views/CompanyListView.vue'
import BranchListView from '../views/BranchListView.vue'
import DepartmentListView from '../views/DepartmentListView.vue'
import PositionListView from '../views/PositionListView.vue'
import EmployeeListView from '@/views/EmployeeListView.vue'
import HolidayListView from '@/views/HolidayListView.vue'
import JobLevelListView from '@/views/JobLevelListView.vue'
import ShiftListView from '@/views/ShiftListView.vue'
import WorkingScheduleListView from '@/views/WorkingScheduleListView.vue'
import AttendanceSettingListView from '@/views/AttendanceSettingListView.vue'
import ApprovalFlowIndex from '@/views/ApprovalFlowIndex.vue'
import ApprovalFlowDetail from '@/views/ApprovalFlowDetail.vue'
import AttendanceIndex from '@/views/AttendanceIndex.vue'
import AttendanceDeviceListView from '@/views/AttendanceDeviceListView.vue'
import AttendanceDeviceOfficeQrDisplayView from '@/views/AttendanceDeviceOfficeQrDisplayView.vue'
import { useAuthStore } from '@/stores/auth'

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: [
    {
      path: '/',
      name: 'home',
      component: DashboardView,
      meta: { requiresAuth: true },
    },
    {
      path: '/users',
      name: 'users',
      component: UserListView,
      meta: { requiresAuth: true },
    },
    {
      path: '/about',
      name: 'about',
      component: () => import('../views/AboutView.vue'),
      meta: { requiresAuth: true },
    },
    {
      path: '/companies',
      name: 'companies',
      component: CompanyListView,
      meta: { requiresAuth: true },
    },
    {
      path: '/branches',
      name: 'branches',
      component: BranchListView,
      meta: { requiresAuth: true },
    },
    {
      path: '/departments',
      name: 'departments',
      component: DepartmentListView,
      meta: { requiresAuth: true },
    },
    {
      path: '/positions',
      name: 'positions',
      component: PositionListView,
      meta: { requiresAuth: true },
    },
    {
      path: '/employees',
      name: 'employees',
      component: EmployeeListView,
      meta: { requiresAuth: true },
    },
    {
      path: '/holidays',
      name: 'holidays',
      component: HolidayListView,
      meta: { requiresAuth: true },
    },
    {
      path: '/job-levels',
      name: 'job-levels',
      component: JobLevelListView,
      meta: { requiresAuth: true },
    },
    {
      path: '/shifts',
      name: 'shifts',
      component:ShiftListView,
      meta: { requiresAuth:true },
    },
    {
      path: '/working-schedules',
      name: 'working-schedules',
      component: WorkingScheduleListView,
      meta: { requiresAuth: true },
    },
    {
      path: '/attendance-settings',
      name: 'attendance-settings',
      component: AttendanceSettingListView,
      meta: { requiresAuth: true },
    },
    {
      path: '/approval-flows',
      name: 'approval-flows.index',
      component: ApprovalFlowIndex,
      meta: { requiresAuth: true },
    },
    {
      path: '/approval-flows/:id',
      name: 'approval-flows.show',
      component: ApprovalFlowDetail,
      meta: { requiresAuth: true },
      props: true,
    },
    {
      path: '/attendances',
      name: 'attendances.index',
      component: AttendanceIndex,
      meta: { requiresAuth: true },
    },
    {
      path: '/attendance-devices',
      name: 'attendance-devices.list',
      component: AttendanceDeviceListView,
      meta: { requiresAuth: true },
    },
    {
      path: '/attendance-devices/:id/office-qr',
      name: 'attendance-devices.office-qr',
      component: AttendanceDeviceOfficeQrDisplayView,
      meta: { requiresAuth: true },
    },
    {
      path: '/login',
      name: 'login',
      component: () => import('../views/LoginView.vue'),
      meta: { requiresAuth: false },
    },
  ],
})

router.beforeEach(async (to) => {
  const authStore = useAuthStore()

  if (authStore.user === null) {
    await authStore.fetchUser()
  }

  if (to.meta.requiresAuth && !authStore.isAuthenticated) {
    return { name: 'login' }
  }

  if (to.name === 'login' && authStore.isAuthenticated) {
    return { name: 'home' }
  }
})

export default router