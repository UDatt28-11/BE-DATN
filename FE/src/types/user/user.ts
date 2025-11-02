export type UserRole = "admin" | "host" | "guest";
export type UserStatus = "active" | "inactive" | "blocked" | "locked";

export interface User {
  key?: string;
  id: number;
  full_name: string;
  email: string;
  phone_number: string | null;
  avatar_url: string | null;
  date_of_birth: string | null;
  gender: "male" | "female" | "other" | null;
  address: string | null;
  status: "active" | "locked";
  preferred_language: string;
  email_verified_at: string | null;
  created_at: string | null;
  updated_at: string | null;
  // Legacy fields for compatibility (optional)
  name?: string;
  phone?: string;
  avatar?: string;
  role?: UserRole;
  totalBookings?: number;
  totalSpent?: number;
  joinDate?: string;
  lastLogin?: string;
}
