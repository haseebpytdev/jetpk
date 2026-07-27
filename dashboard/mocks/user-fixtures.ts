import { buildEffectiveAccessSummary } from "@/lib/access-control/effective-access";
import { validateUser } from "@/lib/access-control/access-validation";
import { USER_TYPE_LABELS } from "@/types/access-control";
import type {
  InvitationState,
  MfaState,
  SecurityState,
  User,
  UserRoleAssignment,
  UserStatus,
  UserType,
  UserVerificationState,
  ValidationState,
} from "@/types/access-control";

export const USER_REFERENCE_DATE = "2026-07-01T00:00:00.000Z";

type UserSeed = {
  id: string;
  fullName: string;
  displayName: string;
  email: string;
  phone: string | null;
  department: string;
  jobTitle: string;
  userType: UserType;
  roleIds: string[];
  status: UserStatus;
  verificationState: UserVerificationState;
  mfaState: MfaState;
  mfaRequired: boolean;
  invitationState: InvitationState;
  securityState: SecurityState;
  failedSignInCount: number;
  activeSessionCount: number;
  lastSignInAt: string | null;
  createdAt: string;
  updatedAt: string;
  createdBy: string;
  updatedBy: string;
  notes: string | null;
  validationState: ValidationState;
};

const DEPARTMENTS = [
  "Operations",
  "Finance",
  "Customer Experience",
  "Content",
  "Technology",
  "Compliance",
  "Analytics",
  "Executive",
] as const;

const USER_SEEDS: UserSeed[] = [
  { id: "JP-USR-0001", fullName: "Ayesha Khan", displayName: "Ayesha K.", email: "ayesha.khan@staff-preview.jetpakistan.example", phone: "+92 300 1000001", department: "Executive", jobTitle: "Platform Director", userType: "superAdministrator", roleIds: ["JP-ROL-0001"], status: "active", verificationState: "verified", mfaState: "enabled", mfaRequired: true, invitationState: "accepted", securityState: "normal", failedSignInCount: 0, activeSessionCount: 2, lastSignInAt: "2026-06-30T14:22:00.000Z", createdAt: "2025-01-10T08:00:00.000Z", updatedAt: USER_REFERENCE_DATE, createdBy: "system", updatedBy: "JP-USR-0001", notes: "Primary super administrator fixture.", validationState: "valid" },
  { id: "JP-USR-0002", fullName: "Bilal Ahmed", displayName: "Bilal A.", email: "bilal.ahmed@staff-preview.jetpakistan.example", phone: "+92 300 1000002", department: "Operations", jobTitle: "Operations Manager", userType: "operationsManager", roleIds: ["JP-ROL-0002"], status: "active", verificationState: "verified", mfaState: "enabled", mfaRequired: true, invitationState: "accepted", securityState: "normal", failedSignInCount: 0, activeSessionCount: 1, lastSignInAt: "2026-06-29T09:15:00.000Z", createdAt: "2025-01-15T08:00:00.000Z", updatedAt: USER_REFERENCE_DATE, createdBy: "JP-USR-0001", updatedBy: "JP-USR-0001", notes: null, validationState: "valid" },
  { id: "JP-USR-0003", fullName: "Sana Malik", displayName: "Sana M.", email: "sana.malik@staff-preview.jetpakistan.example", phone: "+92 300 1000003", department: "Operations", jobTitle: "Senior Booking Agent", userType: "bookingAgent", roleIds: ["JP-ROL-0003"], status: "active", verificationState: "verified", mfaState: "enabled", mfaRequired: false, invitationState: "accepted", securityState: "normal", failedSignInCount: 0, activeSessionCount: 1, lastSignInAt: "2026-06-30T11:00:00.000Z", createdAt: "2025-02-01T08:00:00.000Z", updatedAt: USER_REFERENCE_DATE, createdBy: "JP-USR-0002", updatedBy: "JP-USR-0002", notes: null, validationState: "valid" },
  { id: "JP-USR-0004", fullName: "Hassan Raza", displayName: "Hassan R.", email: "hassan.raza@staff-preview.jetpakistan.example", phone: "+92 300 1000004", department: "Operations", jobTitle: "Ticketing Specialist", userType: "ticketingAgent", roleIds: ["JP-ROL-0004", "JP-ROL-0014"], status: "active", verificationState: "verified", mfaState: "enabled", mfaRequired: true, invitationState: "accepted", securityState: "normal", failedSignInCount: 0, activeSessionCount: 2, lastSignInAt: "2026-06-30T08:45:00.000Z", createdAt: "2025-02-10T08:00:00.000Z", updatedAt: USER_REFERENCE_DATE, createdBy: "JP-USR-0002", updatedBy: "JP-USR-0002", notes: "Multi-role GDS and NDC ticketing.", validationState: "valid" },
  { id: "JP-USR-0005", fullName: "Fatima Noor", displayName: "Fatima N.", email: "fatima.noor@staff-preview.jetpakistan.example", phone: "+92 300 1000005", department: "Finance", jobTitle: "Finance Officer", userType: "financeOfficer", roleIds: ["JP-ROL-0005"], status: "active", verificationState: "verified", mfaState: "enabled", mfaRequired: true, invitationState: "accepted", securityState: "normal", failedSignInCount: 0, activeSessionCount: 1, lastSignInAt: "2026-06-28T16:30:00.000Z", createdAt: "2025-02-15T08:00:00.000Z", updatedAt: USER_REFERENCE_DATE, createdBy: "JP-USR-0001", updatedBy: "JP-USR-0001", notes: null, validationState: "valid" },
  { id: "JP-USR-0006", fullName: "Omar Siddiqui", displayName: "Omar S.", email: "omar.siddiqui@staff-preview.jetpakistan.example", phone: "+92 300 1000006", department: "Customer Experience", jobTitle: "Support Lead", userType: "customerSupport", roleIds: ["JP-ROL-0006"], status: "active", verificationState: "verified", mfaState: "disabled", mfaRequired: false, invitationState: "accepted", securityState: "warning", failedSignInCount: 1, activeSessionCount: 1, lastSignInAt: "2026-06-27T10:00:00.000Z", createdAt: "2025-03-01T08:00:00.000Z", updatedAt: USER_REFERENCE_DATE, createdBy: "JP-USR-0002", updatedBy: "JP-USR-0002", notes: null, validationState: "warning" },
  { id: "JP-USR-0007", fullName: "Zainab Ali", displayName: "Zainab A.", email: "zainab.ali@staff-preview.jetpakistan.example", phone: "+92 300 1000007", department: "Content", jobTitle: "Content Manager", userType: "contentManager", roleIds: ["JP-ROL-0007"], status: "active", verificationState: "verified", mfaState: "enabled", mfaRequired: false, invitationState: "accepted", securityState: "normal", failedSignInCount: 0, activeSessionCount: 1, lastSignInAt: "2026-06-30T13:00:00.000Z", createdAt: "2025-03-15T08:00:00.000Z", updatedAt: USER_REFERENCE_DATE, createdBy: "JP-USR-0001", updatedBy: "JP-USR-0007", notes: null, validationState: "valid" },
  { id: "JP-USR-0008", fullName: "Usman Tariq", displayName: "Usman T.", email: "usman.tariq@staff-preview.jetpakistan.example", phone: "+92 300 1000008", department: "Analytics", jobTitle: "Business Analyst", userType: "analyst", roleIds: ["JP-ROL-0008"], status: "active", verificationState: "verified", mfaState: "enabled", mfaRequired: false, invitationState: "accepted", securityState: "normal", failedSignInCount: 0, activeSessionCount: 1, lastSignInAt: "2026-06-29T15:00:00.000Z", createdAt: "2025-04-01T08:00:00.000Z", updatedAt: USER_REFERENCE_DATE, createdBy: "JP-USR-0001", updatedBy: "JP-USR-0001", notes: null, validationState: "valid" },
  { id: "JP-USR-0009", fullName: "Nadia Hussain", displayName: "Nadia H.", email: "nadia.hussain@staff-preview.jetpakistan.example", phone: "+92 300 1000009", department: "Compliance", jobTitle: "Internal Auditor", userType: "readOnlyAuditor", roleIds: ["JP-ROL-0009"], status: "active", verificationState: "verified", mfaState: "enabled", mfaRequired: true, invitationState: "accepted", securityState: "normal", failedSignInCount: 0, activeSessionCount: 1, lastSignInAt: "2026-06-30T07:00:00.000Z", createdAt: "2025-04-01T08:00:00.000Z", updatedAt: USER_REFERENCE_DATE, createdBy: "JP-USR-0001", updatedBy: "JP-USR-0001", notes: null, validationState: "valid" },
  { id: "JP-USR-0010", fullName: "Kamran Iqbal", displayName: "Kamran I.", email: "kamran.iqbal@staff-preview.jetpakistan.example", phone: "+92 300 1000010", department: "Technology", jobTitle: "System Administrator", userType: "administrator", roleIds: ["JP-ROL-0013"], status: "active", verificationState: "verified", mfaState: "enabled", mfaRequired: true, invitationState: "accepted", securityState: "normal", failedSignInCount: 0, activeSessionCount: 2, lastSignInAt: "2026-06-30T12:00:00.000Z", createdAt: "2025-01-20T08:00:00.000Z", updatedAt: USER_REFERENCE_DATE, createdBy: "JP-USR-0001", updatedBy: "JP-USR-0010", notes: null, validationState: "valid" },
  { id: "JP-USR-0011", fullName: "Rabia Shah", displayName: "Rabia S.", email: "rabia.shah@staff-preview.jetpakistan.example", phone: "+92 300 1000011", department: "Operations", jobTitle: "Booking Agent", userType: "bookingAgent", roleIds: ["JP-ROL-0003"], status: "invited", verificationState: "unverified", mfaState: "disabled", mfaRequired: false, invitationState: "pending", securityState: "staleInvitation", failedSignInCount: 0, activeSessionCount: 0, lastSignInAt: null, createdAt: "2025-06-01T08:00:00.000Z", updatedAt: USER_REFERENCE_DATE, createdBy: "JP-USR-0002", updatedBy: "JP-USR-0002", notes: "Pending invitation.", validationState: "review" },
  { id: "JP-USR-0012", fullName: "Imran Qureshi", displayName: "Imran Q.", email: "imran.qureshi@staff-preview.jetpakistan.example", phone: "+92 300 1000012", department: "Operations", jobTitle: "Booking Agent", userType: "bookingAgent", roleIds: [], status: "active", verificationState: "verified", mfaState: "disabled", mfaRequired: false, invitationState: "accepted", securityState: "reviewRequired", failedSignInCount: 0, activeSessionCount: 1, lastSignInAt: "2026-06-25T09:00:00.000Z", createdAt: "2025-05-01T08:00:00.000Z", updatedAt: USER_REFERENCE_DATE, createdBy: "JP-USR-0002", updatedBy: "JP-USR-0002", notes: "No role assigned.", validationState: "warning" },
  { id: "JP-USR-0013", fullName: "Mehwish Anwar", displayName: "Mehwish A.", email: "mehwish.anwar@staff-preview.jetpakistan.example", phone: "+92 300 1000013", department: "Operations", jobTitle: "PNR Reviewer", userType: "operationsManager", roleIds: ["JP-ROL-0010"], status: "active", verificationState: "verified", mfaState: "enabled", mfaRequired: false, invitationState: "accepted", securityState: "normal", failedSignInCount: 0, activeSessionCount: 1, lastSignInAt: "2026-06-30T10:30:00.000Z", createdAt: "2025-05-15T08:00:00.000Z", updatedAt: USER_REFERENCE_DATE, createdBy: "JP-USR-0002", updatedBy: "JP-USR-0002", notes: null, validationState: "valid" },
  { id: "JP-USR-0014", fullName: "Tariq Mahmood", displayName: "Tariq M.", email: "tariq.mahmood@staff-preview.jetpakistan.example", phone: "+92 300 1000014", department: "Finance", jobTitle: "Senior Finance Officer", userType: "financeOfficer", roleIds: ["JP-ROL-0005", "JP-ROL-0008"], status: "active", verificationState: "verified", mfaState: "enabled", mfaRequired: true, invitationState: "accepted", securityState: "normal", failedSignInCount: 0, activeSessionCount: 1, lastSignInAt: "2026-06-29T11:00:00.000Z", createdAt: "2025-03-01T08:00:00.000Z", updatedAt: USER_REFERENCE_DATE, createdBy: "JP-USR-0001", updatedBy: "JP-USR-0005", notes: "Multi-role finance and analytics.", validationState: "valid" },
  { id: "JP-USR-0015", fullName: "Sadia Farooq", displayName: "Sadia F.", email: "sadia.farooq@staff-preview.jetpakistan.example", phone: "+92 300 1000015", department: "Customer Experience", jobTitle: "Support Agent", userType: "customerSupport", roleIds: ["JP-ROL-0006"], status: "suspended", verificationState: "verified", mfaState: "enabled", mfaRequired: false, invitationState: "accepted", securityState: "suspended", failedSignInCount: 0, activeSessionCount: 2, lastSignInAt: "2026-06-20T08:00:00.000Z", createdAt: "2025-04-01T08:00:00.000Z", updatedAt: USER_REFERENCE_DATE, createdBy: "JP-USR-0002", updatedBy: "JP-USR-0010", notes: "Suspended with active sessions (validation test).", validationState: "blocked" },
  { id: "JP-USR-0016", fullName: "Waqas Butt", displayName: "Waqas B.", email: "waqas.butt@staff-preview.jetpakistan.example", phone: "+92 300 1000016", department: "Operations", jobTitle: "Booking Agent", userType: "bookingAgent", roleIds: ["JP-ROL-0003"], status: "locked", verificationState: "verified", mfaState: "enabled", mfaRequired: false, invitationState: "accepted", securityState: "locked", failedSignInCount: 7, activeSessionCount: 0, lastSignInAt: "2026-06-30T06:00:00.000Z", createdAt: "2025-04-15T08:00:00.000Z", updatedAt: USER_REFERENCE_DATE, createdBy: "JP-USR-0002", updatedBy: "system", notes: "Locked after failed sign-ins.", validationState: "blocked" },
  { id: "JP-USR-0017", fullName: "Hina Akram", displayName: "Hina A.", email: "hina.akram@staff-preview.jetpakistan.example", phone: "+92 300 1000017", department: "Content", jobTitle: "Content Editor", userType: "contentManager", roleIds: ["JP-ROL-0007"], status: "pendingVerification", verificationState: "pending", mfaState: "pendingSetup", mfaRequired: true, invitationState: "accepted", securityState: "warning", failedSignInCount: 0, activeSessionCount: 0, lastSignInAt: null, createdAt: "2026-06-25T08:00:00.000Z", updatedAt: USER_REFERENCE_DATE, createdBy: "JP-USR-0007", updatedBy: "JP-USR-0007", notes: "Awaiting email verification.", validationState: "review" },
  { id: "JP-USR-0018", fullName: "Asad Javed", displayName: "Asad J.", email: "asad.javed@staff-preview.jetpakistan.example", phone: "+92 300 1000018", department: "Technology", jobTitle: "DevOps Engineer", userType: "administrator", roleIds: ["JP-ROL-0011"], status: "active", verificationState: "verified", mfaState: "enabled", mfaRequired: true, invitationState: "accepted", securityState: "normal", failedSignInCount: 0, activeSessionCount: 1, lastSignInAt: "2026-06-28T14:00:00.000Z", createdAt: "2025-06-01T08:00:00.000Z", updatedAt: USER_REFERENCE_DATE, createdBy: "JP-USR-0010", updatedBy: "JP-USR-0010", notes: "Incomplete role assignment.", validationState: "warning" },
  { id: "JP-USR-0019", fullName: "Maria Joseph", displayName: "Maria J.", email: "maria.joseph@staff-preview.jetpakistan.example", phone: "+92 300 1000019", department: "Compliance", jobTitle: "Compliance Analyst", userType: "readOnlyAuditor", roleIds: ["JP-ROL-0012"], status: "active", verificationState: "verified", mfaState: "enabled", mfaRequired: true, invitationState: "accepted", securityState: "reviewRequired", failedSignInCount: 0, activeSessionCount: 1, lastSignInAt: "2026-06-29T08:00:00.000Z", createdAt: "2025-06-15T08:00:00.000Z", updatedAt: USER_REFERENCE_DATE, createdBy: "JP-USR-0001", updatedBy: "JP-USR-0001", notes: "Overly broad role for validation demo.", validationState: "warning" },
  { id: "JP-USR-0020", fullName: "Faisal Hamid", displayName: "Faisal H.", email: "faisal.hamid@staff-preview.jetpakistan.example", phone: "+92 300 1000020", department: "Operations", jobTitle: "Night Shift Agent", userType: "bookingAgent", roleIds: ["JP-ROL-0003"], status: "disabled", verificationState: "verified", mfaState: "disabled", mfaRequired: false, invitationState: "revoked", securityState: "normal", failedSignInCount: 0, activeSessionCount: 0, lastSignInAt: "2026-05-01T08:00:00.000Z", createdAt: "2025-03-01T08:00:00.000Z", updatedAt: USER_REFERENCE_DATE, createdBy: "JP-USR-0002", updatedBy: "JP-USR-0010", notes: "Disabled account.", validationState: "valid" },
  { id: "JP-USR-0021", fullName: "Amna Sheikh", displayName: "Amna S.", email: "amna.sheikh@staff-preview.jetpakistan.example", phone: "+92 300 1000021", department: "Operations", jobTitle: "Booking Agent", userType: "bookingAgent", roleIds: ["JP-ROL-0003"], status: "archived", verificationState: "expired", mfaState: "disabled", mfaRequired: false, invitationState: "accepted", securityState: "normal", failedSignInCount: 0, activeSessionCount: 0, lastSignInAt: "2025-12-01T08:00:00.000Z", createdAt: "2025-01-01T08:00:00.000Z", updatedAt: USER_REFERENCE_DATE, createdBy: "JP-USR-0002", updatedBy: "JP-USR-0010", notes: "Archived former employee.", validationState: "valid" },
  { id: "JP-USR-0022", fullName: "Danish Ali", displayName: "Danish A.", email: "danish.ali@staff-preview.jetpakistan.example", phone: "+92 300 1000022", department: "Operations", jobTitle: "Ticketing Agent", userType: "ticketingAgent", roleIds: ["JP-ROL-0004"], status: "active", verificationState: "verified", mfaState: "enabled", mfaRequired: true, invitationState: "accepted", securityState: "normal", failedSignInCount: 0, activeSessionCount: 3, lastSignInAt: "2026-06-30T15:00:00.000Z", createdAt: "2025-04-01T08:00:00.000Z", updatedAt: USER_REFERENCE_DATE, createdBy: "JP-USR-0002", updatedBy: "JP-USR-0002", notes: "Multiple active sessions.", validationState: "valid" },
  { id: "JP-USR-0023", fullName: "Laiba Khan", displayName: "Laiba K.", email: "laiba.khan@staff-preview.jetpakistan.example", phone: "+92 300 1000023", department: "Customer Experience", jobTitle: "Support Agent", userType: "customerSupport", roleIds: ["JP-ROL-0006"], status: "active", verificationState: "verified", mfaState: "disabled", mfaRequired: true, invitationState: "accepted", securityState: "warning", failedSignInCount: 0, activeSessionCount: 1, lastSignInAt: "2026-06-30T09:00:00.000Z", createdAt: "2025-05-01T08:00:00.000Z", updatedAt: USER_REFERENCE_DATE, createdBy: "JP-USR-0006", updatedBy: "JP-USR-0006", notes: "MFA required but disabled.", validationState: "blocked" },
  { id: "JP-USR-0024", fullName: "Shahid Mehmood", displayName: "Shahid M.", email: "shahid.mehmood@staff-preview.jetpakistan.example", phone: "+92 300 1000024", department: "Finance", jobTitle: "Accounts Officer", userType: "financeOfficer", roleIds: ["JP-ROL-0005"], status: "active", verificationState: "verified", mfaState: "enabled", mfaRequired: true, invitationState: "accepted", securityState: "normal", failedSignInCount: 0, activeSessionCount: 1, lastSignInAt: "2026-06-27T12:00:00.000Z", createdAt: "2025-04-01T08:00:00.000Z", updatedAt: USER_REFERENCE_DATE, createdBy: "JP-USR-0005", updatedBy: "JP-USR-0005", notes: null, validationState: "valid" },
  { id: "JP-USR-0025", fullName: "Noreen Bibi", displayName: "Noreen B.", email: "noreen.bibi@staff-preview.jetpakistan.example", phone: "+92 300 1000025", department: "Analytics", jobTitle: "Reporting Analyst", userType: "analyst", roleIds: ["JP-ROL-0008"], status: "active", verificationState: "verified", mfaState: "enabled", mfaRequired: false, invitationState: "accepted", securityState: "normal", failedSignInCount: 0, activeSessionCount: 1, lastSignInAt: "2026-06-29T14:00:00.000Z", createdAt: "2025-05-01T08:00:00.000Z", updatedAt: USER_REFERENCE_DATE, createdBy: "JP-USR-0008", updatedBy: "JP-USR-0008", notes: null, validationState: "valid" },
  { id: "JP-USR-0026", fullName: "Arsalan Haider", displayName: "Arsalan H.", email: "arsalan.haider@staff-preview.jetpakistan.example", phone: "+92 300 1000026", department: "Operations", jobTitle: "Booking Agent", userType: "bookingAgent", roleIds: ["JP-ROL-0003"], status: "invited", verificationState: "unverified", mfaState: "disabled", mfaRequired: false, invitationState: "pending", securityState: "normal", failedSignInCount: 0, activeSessionCount: 0, lastSignInAt: null, createdAt: "2026-06-28T08:00:00.000Z", updatedAt: USER_REFERENCE_DATE, createdBy: "JP-USR-0002", updatedBy: "JP-USR-0002", notes: "Recent invitation.", validationState: "review" },
  { id: "JP-USR-0027", fullName: "Saima Akhtar", displayName: "Saima A.", email: "saima.akhtar@staff-preview.jetpakistan.example", phone: "+92 300 1000027", department: "Content", jobTitle: "CMS Reviewer", userType: "contentManager", roleIds: ["JP-ROL-0007"], status: "active", verificationState: "verified", mfaState: "enabled", mfaRequired: false, invitationState: "accepted", securityState: "normal", failedSignInCount: 0, activeSessionCount: 1, lastSignInAt: "2026-06-30T16:00:00.000Z", createdAt: "2025-06-01T08:00:00.000Z", updatedAt: USER_REFERENCE_DATE, createdBy: "JP-USR-0007", updatedBy: "JP-USR-0007", notes: null, validationState: "valid" },
  { id: "JP-USR-0028", fullName: "Junaid Malik", displayName: "Junaid M.", email: "junaid.malik@staff-preview.jetpakistan.example", phone: "+92 300 1000028", department: "Technology", jobTitle: "Security Engineer", userType: "administrator", roleIds: ["JP-ROL-0013", "JP-ROL-0009"], status: "active", verificationState: "verified", mfaState: "enabled", mfaRequired: true, invitationState: "accepted", securityState: "normal", failedSignInCount: 0, activeSessionCount: 2, lastSignInAt: "2026-06-30T17:00:00.000Z", createdAt: "2025-02-01T08:00:00.000Z", updatedAt: USER_REFERENCE_DATE, createdBy: "JP-USR-0001", updatedBy: "JP-USR-0010", notes: "Admin plus audit read access.", validationState: "valid" },
  { id: "JP-USR-0029", fullName: "Huma Rizvi", displayName: "Huma R.", email: "huma.rizvi@staff-preview.jetpakistan.example", phone: "+92 300 1000029", department: "Executive", jobTitle: "Deputy Director", userType: "superAdministrator", roleIds: ["JP-ROL-0001"], status: "active", verificationState: "verified", mfaState: "enabled", mfaRequired: true, invitationState: "accepted", securityState: "normal", failedSignInCount: 0, activeSessionCount: 1, lastSignInAt: "2026-06-30T18:00:00.000Z", createdAt: "2025-01-10T08:00:00.000Z", updatedAt: USER_REFERENCE_DATE, createdBy: "system", updatedBy: "JP-USR-0001", notes: "Secondary super admin.", validationState: "valid" },
  { id: "JP-USR-0030", fullName: "Yasir Abbas", displayName: "Yasir A.", email: "yasir.abbas@staff-preview.jetpakistan.example", phone: "+92 300 1000030", department: "Operations", jobTitle: "Shift Supervisor", userType: "operationsManager", roleIds: ["JP-ROL-0002", "JP-ROL-0003"], status: "active", verificationState: "verified", mfaState: "enabled", mfaRequired: true, invitationState: "accepted", securityState: "normal", failedSignInCount: 0, activeSessionCount: 1, lastSignInAt: "2026-06-29T20:00:00.000Z", createdAt: "2025-03-01T08:00:00.000Z", updatedAt: USER_REFERENCE_DATE, createdBy: "JP-USR-0002", updatedBy: "JP-USR-0002", notes: null, validationState: "valid" },
  { id: "JP-USR-0031", fullName: "Amina Farooq", displayName: "Amina F.", email: "amina.farooq@staff-preview.jetpakistan.example", phone: "+92 300 1000031", department: "", jobTitle: "Contractor", userType: "bookingAgent", roleIds: ["JP-ROL-0003"], status: "active", verificationState: "verified", mfaState: "disabled", mfaRequired: false, invitationState: "accepted", securityState: "warning", failedSignInCount: 0, activeSessionCount: 1, lastSignInAt: "2026-06-28T08:00:00.000Z", createdAt: "2025-06-01T08:00:00.000Z", updatedAt: USER_REFERENCE_DATE, createdBy: "JP-USR-0002", updatedBy: "JP-USR-0002", notes: "Missing department validation.", validationState: "warning" },
  { id: "JP-USR-0032", fullName: "Rehan Saeed", displayName: "Rehan S.", email: "rehan.saeed@staff-preview.jetpakistan.example", phone: "+92 300 1000032", department: "Operations", jobTitle: "Inactive Agent", userType: "bookingAgent", roleIds: ["JP-ROL-0003"], status: "active", verificationState: "verified", mfaState: "enabled", mfaRequired: false, invitationState: "accepted", securityState: "normal", failedSignInCount: 0, activeSessionCount: 0, lastSignInAt: "2026-03-01T08:00:00.000Z", createdAt: "2025-02-01T08:00:00.000Z", updatedAt: USER_REFERENCE_DATE, createdBy: "JP-USR-0002", updatedBy: "JP-USR-0002", notes: "Inactive user — no recent sign-in.", validationState: "valid" },
  { id: "JP-USR-0033", fullName: "Kiran Aslam", displayName: "Kiran A.", email: "kiran.aslam@staff-preview.jetpakistan.example", phone: "+92 300 1000033", department: "Customer Experience", jobTitle: "Support Agent", userType: "customerSupport", roleIds: ["JP-ROL-0006"], status: "active", verificationState: "verified", mfaState: "enabled", mfaRequired: false, invitationState: "accepted", securityState: "normal", failedSignInCount: 3, activeSessionCount: 1, lastSignInAt: "2026-06-30T19:00:00.000Z", createdAt: "2025-04-01T08:00:00.000Z", updatedAt: USER_REFERENCE_DATE, createdBy: "JP-USR-0006", updatedBy: "JP-USR-0006", notes: null, validationState: "valid" },
  { id: "JP-USR-0034", fullName: "Babar Azam", displayName: "Babar A.", email: "babar.azam@staff-preview.jetpakistan.example", phone: "+92 300 1000034", department: "Operations", jobTitle: "GDS Specialist", userType: "ticketingAgent", roleIds: ["JP-ROL-0004"], status: "active", verificationState: "verified", mfaState: "enabled", mfaRequired: true, invitationState: "accepted", securityState: "normal", failedSignInCount: 0, activeSessionCount: 1, lastSignInAt: "2026-06-30T20:00:00.000Z", createdAt: "2025-05-01T08:00:00.000Z", updatedAt: USER_REFERENCE_DATE, createdBy: "JP-USR-0002", updatedBy: "JP-USR-0004", notes: null, validationState: "valid" },
  { id: "JP-USR-0035", fullName: "Samina Parveen", displayName: "Samina P.", email: "samina.parveen@staff-preview.jetpakistan.example", phone: "+92 300 1000035", department: "Finance", jobTitle: "Reconciliation Clerk", userType: "financeOfficer", roleIds: ["JP-ROL-0005"], status: "active", verificationState: "verified", mfaState: "enabled", mfaRequired: true, invitationState: "accepted", securityState: "normal", failedSignInCount: 0, activeSessionCount: 1, lastSignInAt: "2026-06-29T21:00:00.000Z", createdAt: "2025-05-15T08:00:00.000Z", updatedAt: USER_REFERENCE_DATE, createdBy: "JP-USR-0005", updatedBy: "JP-USR-0005", notes: null, validationState: "valid" },
  { id: "JP-USR-0036", fullName: "Adnan Khawaja", displayName: "Adnan K.", email: "adnan.khawaja@staff-preview.jetpakistan.example", phone: "+92 300 1000036", department: "Compliance", jobTitle: "Audit Associate", userType: "readOnlyAuditor", roleIds: ["JP-ROL-0009"], status: "active", verificationState: "verified", mfaState: "enabled", mfaRequired: true, invitationState: "accepted", securityState: "normal", failedSignInCount: 0, activeSessionCount: 1, lastSignInAt: "2026-06-30T21:30:00.000Z", createdAt: "2025-06-01T08:00:00.000Z", updatedAt: USER_REFERENCE_DATE, createdBy: "JP-USR-0009", updatedBy: "JP-USR-0009", notes: null, validationState: "valid" },
  { id: "JP-USR-0037", fullName: "Rubina Iqbal", displayName: "Rubina I.", email: "rubina.iqbal@staff-preview.jetpakistan.example", phone: "+92 300 1000037", department: "Operations", jobTitle: "Booking Agent", userType: "bookingAgent", roleIds: ["JP-ROL-0003", "JP-ROL-0003"], status: "active", verificationState: "verified", mfaState: "enabled", mfaRequired: false, invitationState: "accepted", securityState: "reviewRequired", failedSignInCount: 0, activeSessionCount: 1, lastSignInAt: "2026-06-30T22:00:00.000Z", createdAt: "2025-06-10T08:00:00.000Z", updatedAt: USER_REFERENCE_DATE, createdBy: "JP-USR-0002", updatedBy: "JP-USR-0002", notes: "Duplicate role for validation.", validationState: "blocked" },
  { id: "JP-USR-0038", fullName: "Mansoor Elahi", displayName: "Mansoor E.", email: "mansoor.elahi@staff-preview.jetpakistan.example", phone: "+92 300 1000038", department: "Technology", jobTitle: "Integration Specialist", userType: "administrator", roleIds: ["JP-ROL-0013"], status: "active", verificationState: "unverified", mfaState: "enabled", mfaRequired: true, invitationState: "accepted", securityState: "warning", failedSignInCount: 0, activeSessionCount: 1, lastSignInAt: "2026-06-30T22:30:00.000Z", createdAt: "2025-06-15T08:00:00.000Z", updatedAt: USER_REFERENCE_DATE, createdBy: "JP-USR-0010", updatedBy: "JP-USR-0010", notes: "Active but unverified.", validationState: "warning" },
  { id: "JP-USR-0039", fullName: "Naila Waseem", displayName: "Naila W.", email: "naila.waseem@staff-preview.jetpakistan.example", phone: "+92 300 1000039", department: "Analytics", jobTitle: "Data Analyst", userType: "analyst", roleIds: ["JP-ROL-0008"], status: "active", verificationState: "verified", mfaState: "enabled", mfaRequired: false, invitationState: "accepted", securityState: "normal", failedSignInCount: 0, activeSessionCount: 1, lastSignInAt: "2026-06-30T23:00:00.000Z", createdAt: "2025-06-20T08:00:00.000Z", updatedAt: USER_REFERENCE_DATE, createdBy: "JP-USR-0008", updatedBy: "JP-USR-0008", notes: null, validationState: "valid" },
  { id: "JP-USR-0040", fullName: "Khalid Mahmood", displayName: "Khalid M.", email: "khalid.mahmood@staff-preview.jetpakistan.example", phone: "+92 300 1000040", department: "Operations", jobTitle: "NDC Specialist", userType: "ticketingAgent", roleIds: ["JP-ROL-0014"], status: "active", verificationState: "verified", mfaState: "enabled", mfaRequired: true, invitationState: "accepted", securityState: "normal", failedSignInCount: 0, activeSessionCount: 1, lastSignInAt: "2026-06-30T23:30:00.000Z", createdAt: "2025-07-01T08:00:00.000Z", updatedAt: USER_REFERENCE_DATE, createdBy: "JP-USR-0004", updatedBy: "JP-USR-0004", notes: null, validationState: "valid" },
];

function buildRoleAssignments(roleIds: string[], createdBy: string): UserRoleAssignment[] {
  return roleIds.map((roleId) => ({
    roleId,
    assignedAt: USER_REFERENCE_DATE,
    assignedBy: createdBy,
    source: "system" as const,
  }));
}

function buildUser(seed: UserSeed): User {
  const assignedRoles = buildRoleAssignments(seed.roleIds, seed.createdBy);
  const effectiveAccess = buildEffectiveAccessSummary(seed.roleIds);
  const user: User = {
    id: seed.id,
    profile: {
      fullName: seed.fullName,
      displayName: seed.displayName,
      department: seed.department,
      jobTitle: seed.jobTitle,
      userType: seed.userType,
    },
    contact: {
      email: seed.email,
      phone: seed.phone,
      phoneExtension: null,
    },
    assignedRoles,
    effectiveAccess,
    security: {
      status: seed.status,
      verificationState: seed.verificationState,
      mfaState: seed.mfaState,
      invitationState: seed.invitationState,
      securityState: seed.securityState,
      failedSignInCount: seed.failedSignInCount,
      activeSessionCount: seed.activeSessionCount,
      lastSignInAt: seed.lastSignInAt,
      mfaRequired: seed.mfaRequired,
    },
    activity: {
      recentActions: [
        `Viewed ${seed.jobTitle} workspace`,
        seed.lastSignInAt ? "Signed in to dashboard preview" : "Invitation pending",
      ],
      lastViewedModule: seed.department === "Content" ? "CMS" : "Bookings",
      signInCount30d: seed.lastSignInAt ? 12 : 0,
      recordViews30d: 45,
    },
    session: {
      activeSessionCount: seed.activeSessionCount,
      lastSignInAt: seed.lastSignInAt,
      lastSignInMaskedLocation: seed.lastSignInAt ? "192.0.2.10 (documentation range)" : null,
    },
    validationState: seed.validationState,
    validationIssues: [],
    createdAt: seed.createdAt,
    updatedAt: seed.updatedAt,
    createdBy: seed.createdBy,
    updatedBy: seed.updatedBy,
    notes: seed.notes,
  };

  const validation = validateUser(user);
  return {
    ...user,
    validationIssues: validation.issues,
    validationState: validation.valid
      ? seed.validationState
      : validation.issues.some((i) => i.blocking)
        ? "blocked"
        : "warning",
  };
}

export const mockUsers: User[] = USER_SEEDS.map(buildUser);

export const USER_FIXTURE_COUNTS = {
  users: mockUsers.length,
  departments: DEPARTMENTS.length,
};

export function getUserById(id: string): User | undefined {
  return mockUsers.find((u) => u.id === id);
}

export function getUserTypeLabel(userType: UserType): string {
  return USER_TYPE_LABELS[userType];
}
