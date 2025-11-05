import axios from 'axios';

const API_BASE_URL = 'http://localhost:8000/api';

export interface UserResponse {
  id: number;
  full_name: string;
  email: string;
  phone_number: string | null;
  avatar_url: string | null;
  date_of_birth: string | null;
  gender: 'male' | 'female' | 'other' | null;
  address: string | null;
  status: 'active' | 'locked';
  role: string;
  preferred_language: string;
  email_verified_at: string | null;
  created_at: string | null;
  updated_at: string | null;
}

export interface UserListResponse {
  data: UserResponse[];
  meta: {
    pagination: {
      current_page: number;
      per_page: number;
      total: number;
      last_page: number;
    };
  };
}

export interface CreateUserRequest {
  full_name: string;
  email: string;
  password: string;
  phone_number?: string;
  date_of_birth?: string;
  gender?: 'male' | 'female' | 'other';
  address?: string;
  status?: 'active' | 'locked';
}

export interface UpdateUserRequest {
  full_name?: string;
  email?: string;
  password?: string;
  phone_number?: string;
  date_of_birth?: string;
  gender?: 'male' | 'female' | 'other';
  address?: string;
  status?: 'active' | 'locked';
  avatar_url?: string;
}

export const userService = {
  // Lấy danh sách users
  getUsers: async (params?: {
    search?: string;
    status?: string;
    sort_by?: string;
    sort_order?: 'asc' | 'desc';
    per_page?: number;
    page?: number;
  }): Promise<UserListResponse> => {
    const response = await axios.get(`${API_BASE_URL}/admin/users`, { params });
    return response.data;
  },

  // Lấy chi tiết user
  getUserById: async (id: number): Promise<{ data: UserResponse }> => {
    const response = await axios.get(`${API_BASE_URL}/admin/users/${id}`);
    return response.data;
  },

  // Tạo user mới
  createUser: async (data: CreateUserRequest): Promise<{ data: UserResponse; message: string }> => {
    const response = await axios.post(`${API_BASE_URL}/admin/users`, data);
    return response.data;
  },

  // Cập nhật user
  updateUser: async (id: number, data: UpdateUserRequest): Promise<{ data: UserResponse; message: string }> => {
    const response = await axios.put(`${API_BASE_URL}/admin/users/${id}`, data);
    return response.data;
  },

  // Xóa user
  deleteUser: async (id: number): Promise<{ message: string }> => {
    const response = await axios.delete(`${API_BASE_URL}/admin/users/${id}`);
    return response.data;
  },
};

