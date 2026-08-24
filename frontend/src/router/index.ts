import { createRouter, createWebHistory } from 'vue-router'
import DashboardView from '../views/DashboardView.vue'
import UserListView from '../views/UserListView.vue'
import CompanyListView from '../views/CompanyListView.vue'
import BranchListView from '../views/BranchListView.vue'
import DepartmentListView from '../views/DepartmentListView.vue'
import PositionListView from '../views/PositionListView.vue'
import EmployeeListView from '@/views/EmployeeListView.vue'
import EmployeeDetailView from '@/views/EmployeeDetailView.vue'
import ContractProbationListView from '@/views/ContractProbationListView.vue'
import EmployeeMovementListView from '@/views/EmployeeMovementListView.vue'
import AnnouncementManagementView from '@/views/AnnouncementManagementView.vue'
import AnnouncementInboxView from '@/views/AnnouncementInboxView.vue'
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
import MyAttendanceRequestView from '@/views/MyAttendanceRequestView.vue'
import AttendanceHistoryView from '@/views/AttendanceHistoryView.vue'
import AttendanceAbsenceDeductionView from '@/views/AttendanceAbsenceDeductionView.vue'
import AttendanceRequestApprovalListView from '@/views/AttendanceRequestApprovalListView.vue'
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
import EmployeeDeductionListView from '@/views/EmployeeDeductionListView.vue'
import LoanListView from '@/views/LoanListView.vue'
import MyLoanView from '@/views/MyLoanView.vue'
import LoanApprovalListView from '@/views/LoanApprovalListView.vue'
import ReimbursementPolicyListView from '@/views/ReimbursementPolicyListView.vue'
import ReimbursementListView from '@/views/ReimbursementListView.vue'
import MyReimbursementView from '@/views/MyReimbursementView.vue'
import ReimbursementApprovalListView from '@/views/ReimbursementApprovalListView.vue'
import CashAdvancePolicyListView from '@/views/CashAdvancePolicyListView.vue'
import CashAdvanceListView from '@/views/CashAdvanceListView.vue'
import MyCashAdvanceView from '@/views/MyCashAdvanceView.vue'
import ExpensePolicyListView from '@/views/ExpensePolicyListView.vue'
import ExpensePolicyAssignmentListView from '@/views/ExpensePolicyAssignmentListView.vue'
import MyExpenseClaimView from '@/views/MyExpenseClaimView.vue'
import CashAdvanceApprovalListView from '@/views/CashAdvanceApprovalListView.vue'
import BpjsSettingView from '@/views/BpjsSettingView.vue'
import EmployeeBpjsListView from '@/views/EmployeeBpjsListView.vue'
import TaxSettingsView from '@/views/TaxSettingsView.vue'
import EmployeeTaxListView from '@/views/EmployeeTaxListView.vue'
import PayrollRunListView from '@/views/PayrollRunListView.vue'
import PayrollRunDetailView from '@/views/PayrollRunDetailView.vue'
import JobVacancyListView from '@/views/recruitment/JobVacancyListView.vue'
import JobVacancyDetailView from '@/views/recruitment/JobVacancyDetailView.vue'
import HiringRequisitionListView from '@/views/recruitment/HiringRequisitionListView.vue'
import HiringRequisitionDetailView from '@/views/recruitment/HiringRequisitionDetailView.vue'
import InternalJobVacancyListView from '@/views/recruitment/InternalJobVacancyListView.vue'
import InternalJobDetailView from '@/views/recruitment/InternalJobDetailView.vue'
import CandidateListView from '@/views/recruitment/CandidateListView.vue'
import CandidateDetailView from '@/views/recruitment/CandidateDetailView.vue'
import NewJoinerListView from '@/views/recruitment/NewJoinerListView.vue'
import TalentPoolListView from '@/views/recruitment/TalentPoolListView.vue'

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
      path: '/employees/:id',
      name: 'employee-detail',
      component: EmployeeDetailView,
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
      path: '/my-attendances',
      name: 'my-attendances.list',
      component: AttendanceHistoryView,
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
      path: '/my-attendance-requests',
      name: 'my-attendance-requests.list',
      component: MyAttendanceRequestView,
      meta: { requiresAuth: true },
    },
    {
      path: '/attendance-request-approvals',
      name: 'attendance-request-approvals.list',
      component: AttendanceRequestApprovalListView,
      meta: { requiresAuth: true },
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
      path: '/employee-deductions',
      name: 'employee-deductions.list',
      component: EmployeeDeductionListView,
      meta: { requiresAuth: true },
    },
    {
      path: '/loans',
      name: 'loans.list',
      component: LoanListView,
      meta: { requiresAuth: true },
    },
    {
      path: '/my-loans',
      name: 'my-loans.list',
      component: MyLoanView,
      meta: { requiresAuth: true },
    },
    {
      path: '/loan-approvals',
      name: 'loan-approvals.list',
      component: LoanApprovalListView,
      meta: { requiresAuth: true },
    },
    {
      path: '/reimbursement-policies',
      name: 'reimbursement-policies.list',
      component: ReimbursementPolicyListView,
      meta: { requiresAuth: true },
    },
    {
      path: '/reimbursements',
      name: 'reimbursements.list',
      component: ReimbursementListView,
      meta: { requiresAuth: true },
    },
    {
      path: '/my-reimbursements',
      name: 'my-reimbursements.list',
      component: MyReimbursementView,
      meta: { requiresAuth: true },
    },
    {
      path: '/reimbursement-approvals',
      name: 'reimbursement-approvals.list',
      component: ReimbursementApprovalListView,
      meta: { requiresAuth: true },
    },
    {
      path: '/cash-advance-policies',
      name: 'cash-advance-policies.list',
      component: CashAdvancePolicyListView,
      meta: { requiresAuth: true },
    },
    {
      path: '/cash-advances',
      name: 'cash-advances.list',
      component: CashAdvanceListView,
      meta: { requiresAuth: true },
    },
    {
      path: '/my-cash-advances',
      name: 'my-cash-advances.list',
      component: MyCashAdvanceView,
      meta: { requiresAuth: true },
    },
    {
      path: '/cash-advance-approvals',
      name: 'cash-advance-approvals.list',
      component: CashAdvanceApprovalListView,
      meta: { requiresAuth: true },
    },
        {
      path: '/expense-policies',
      name: 'expense-policies.list',
      component: ExpensePolicyListView,
      meta: { requiresAuth: true },
    },
    {
      path: '/expense-policy-assignments',
      name: 'expense-policy-assignments.list',
      component: ExpensePolicyAssignmentListView,
      meta: { requiresAuth: true },
    },
    {
      path: '/my-expense-claims',
      name: 'my-expense-claims.list',
      component: MyExpenseClaimView,
      meta: { requiresAuth: true },
    },
    {
      path: '/bpjs/settings',
      name: 'bpjs-settings',
      component: BpjsSettingView,
      meta: { requiresAuth: true },
    },
    {
      path: '/bpjs/employee-participations',
      name: 'bpjs-employee-participations',
      component: EmployeeBpjsListView,
      meta: { requiresAuth: true },
    },
    {
      path: '/tax-settings',
      name: 'tax-settings',
      component: TaxSettingsView,
      meta: { requiresAuth: true },
    },
    {
      path: '/employee-tax',
      name: 'employee-tax',
      component: EmployeeTaxListView,
      meta: { requiresAuth:true },
    },
    {
      path: '/payroll',
      name: 'payroll',
      component: PayrollRunListView,
      meta: { requiresAuth: true },
    },
    {
      path: '/payroll-runs/:id',
      name: 'payroll-run-detail',
      component: PayrollRunDetailView,
      meta: { requiresAuth: true },
    },
    {
      path: '/employees/contract-probation',
      name: 'contract-probation',
      component: ContractProbationListView,
      meta: { requiresAuth: true },
    },
    {
      path: '/employee-movements',
      name: 'employee-movements',
      component: EmployeeMovementListView,
      meta: { requiresAuth: true },
    },
    {
      path: '/announcements/manage',
      name: 'announcements.manage',
      component: AnnouncementManagementView,
      meta: { requiresAuth: true },
    },
    {
      path: '/announcements',
      name: 'announcements.inbox',
      component: AnnouncementInboxView,
      meta: { requiresAuth: true },
    },
    {
      path: '/job-vacancies',
      name: 'job-vacancies.index',
      component: JobVacancyListView,
      meta: { requiresAuth: true },
    },
    {
      path: '/job-vacancies/:id',
      name: 'job-vacancies.show',
      component: JobVacancyDetailView,
      meta: { requiresAuth: true },
    },
    {
      path: '/hiring-requisitions',
      name: 'hiring-requisitions.index',
      component: HiringRequisitionListView,
      meta: { requiresAuth: true },
    },
    {
      path: '/hiring-requisitions/:id',
      name: 'hiring-requisitions.show',
      component: HiringRequisitionDetailView,
      meta: { requiresAuth: true },
    },
    {
      path: '/internal-job-vacancies',
      name: 'internal-job-vacancies.index',
      component: InternalJobVacancyListView,
      meta: { requiresAuth: true },
    },
    {
      path: '/internal-job-vacancies/:slug',
      name: 'internal-job-vacancies.show',
      component: InternalJobDetailView,
      meta: { requiresAuth: true },
    },
    {
      path: '/candidates',
      name: 'candidates.index',
      component: CandidateListView,
      meta: { requiresAuth: true },
    },
    {
      path: '/candidates/:id',
      name: 'candidates.show',
      component: CandidateDetailView,
      meta: { requiresAuth: true },
    },
    {
      path: '/talent-pool',
      name: 'talent-pool.index',
      component: TalentPoolListView,
      meta: { requiresAuth: true },
    },
    {
      path: '/careers',
      name: 'careers.index',
      component: () => import('@/views/careers/CareerListingView.vue'),
      meta: { requiresAuth: false },
    },
    {
      path: '/careers/:slug',
      name: 'careers.show',
      component: () => import('@/views/careers/CareerDetailView.vue'),
      meta: { requiresAuth: false },
    },
    {
      path: '/careers/:slug/apply',
      name: 'careers.apply',
      component: () => import('@/views/careers/CareerApplyView.vue'),
      meta: { requiresAuth: false },
    },
    {
      path: '/new-joiners',
      name: 'new-joiners.index',
      component: NewJoinerListView,
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