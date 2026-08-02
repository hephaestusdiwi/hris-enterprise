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
import AttendanceApprovalListView from '@/views/AttendanceApprovalListView.vue'
import WorkingScheduleAssignmentListView from '@/views/WorkingScheduleAssignmentListView.vue'
import SchedulerView from '@/views/SchedulerView.vue'
import AttendanceReportView from '@/views/AttendanceReportView.vue'
import LeaveTypeListView from '@/views/LeaveTypeListView.vue'
import LeaveBalanceListView from '@/views/LeaveBalanceListView.vue'
import MyLeaveRequestView from '@/views/MyLeaveRequestView.vue'
import LeaveApprovalListView from '@/views/LeaveApprovalListView.vue'
import LeaveCalendarView from '@/views/LeaveCalendarView.vue'
import SalaryComponentListView from '@/views/SalaryComponentListView.vue'
import SalaryStructureListView from '@/views/SalaryStructureListView.vue'
import { useAuthStore } from '@/stores/auth'
import EmployeeSalaryListView from '@/views/EmployeeSalaryListView.vue'
import EmployeeAllowanceListView from '@/views/EmployeeAllowanceListView.vue'
import FaceRecognitionTestView from '@/views/FaceRecognitionTestView.vue'
import FaceCheckInView from '@/views/FaceCheckInView.vue'

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
      path: '/attendance-approvals',
      name: 'attendance-approvals.list',
      component: AttendanceApprovalListView,
      meta: { requiresAuth: true },
    },
    {
      path: '/attendance-settings/face-recognition-test',
      name: 'attendance-settings.face-recognition-test',
      component: FaceRecognitionTestView,
      meta: { requiresAuth: true },
    },
    {
      path: '/working-schedule-assignments',
      name: 'working-schedule-assignments.list',
      component: WorkingScheduleAssignmentListView,
      meta: { requiresAuth: true },
    },
    {
      path: '/scheduler',
      name: 'scheduler.list',
      component: SchedulerView,
      meta: { requiresAuth: true },
    },
    {
      path: '/attendance-report',
      name: 'attendance-report.list',
      component: AttendanceReportView,
      meta: { requiresAuth: true },
    },
    {
      path: '/leave-types',
      name: 'leave-types.list',
      component: LeaveTypeListView,
      meta: { requiresAuth: true },
    },
    {
      path: '/leave-balances',
      name: 'leave-balances.list',
      component: LeaveBalanceListView,
      meta: { requiresAuth:true },
    },
    {
      path: '/my-leave-requests',
      name: 'my-leave-requests.list',
      component: MyLeaveRequestView,
      meta: { requiresAuth:true },
    },
    {
      path: '/leave-approvals',
      name: 'leave-approvals.list',
      component: LeaveApprovalListView,
      meta: { requiresAuth:true },
    },
    {
      path: '/leave-calendar',
      name: 'leave-calendar.list',
      component: LeaveCalendarView,
      meta: { requiresAuth:true },
    },
    {
      path: '/salary-components',
      name: 'salary-components.list',
      component: SalaryComponentListView,
      meta: { requiresAuth:true },
    },
    {
      path: '/salary-structures',
      name: 'salary-structures.list',
      component: SalaryStructureListView,
      meta: { requiresAuth:true },
    },
    {
      path: '/employee-salaries',
      name: 'employee-salaries.list',
      component: EmployeeSalaryListView,
      meta: { requiresAuth:true },
    },
    {
      path: '/employee-allowances',
      name: 'employee-allowances.list',
      component: EmployeeAllowanceListView,
      meta: { requiresAuth:true },
    },
    {
     path: '/attendance/face-checkin',
     name: 'attendance.face-checkin',
     component: FaceCheckInView,
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