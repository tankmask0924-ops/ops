import router from '@/router'
import { useAuthStore } from '@/stores/auth'
import { createHttp } from '@/utils/http'

export const API_BASE = '/admin-api'

export const http = createHttp({
  baseURL: API_BASE,
  getToken: () => useAuthStore().token,
  onUnauthorized: () => {
    useAuthStore().clear()
    router.push({ name: 'login', query: { redirect: router.currentRoute.value.fullPath } })
  },
})

// ---------- 登录 ----------

export interface Me {
  id: number
  username: string
  name: string
  is_admin: boolean
}

export const login = (username: string, password: string) =>
  http.post<{ token: string; expires_in: number }>('/auth/login', { username, password })
export const fetchMe = () => http.get<Me>('/auth/me')
export const changePassword = (oldPassword: string, newPassword: string) =>
  http.post<{ token: string }>('/auth/password', { old_password: oldPassword, new_password: newPassword })

// ---------- 平台 ----------

export interface Platform {
  id: number
  title: string
  /** 空字符串表示未分类 */
  category: string
  url: string
  account: string
  has_password: boolean
  description: string
  updated_at: string
  /** 能看到这个平台的普通账号，只有管理员拿得到 */
  user_ids?: number[]
}

export interface PlatformInput {
  title: string
  category: string
  url: string
  account: string
  /** 修改时留空表示不改密码 */
  password: string
  clear_password?: boolean
  description: string
  user_ids: number[]
}

export const listPlatforms = (keyword = '') => http.get<Platform[]>('/platforms', keyword ? { keyword } : undefined)
/** 取密码明文，服务端会记一条查看记录 */
export const fetchPlatformPassword = (id: number) =>
  http.get<{ password: string }>(`/platforms/${id}/password`).then((r) => r.password)
export const createPlatform = (data: PlatformInput) => http.post<{ id: number }>('/platforms', data)
export const updatePlatform = (id: number, data: PlatformInput) => http.put<{ id: number }>(`/platforms/${id}`, data)
export const deletePlatform = (id: number) => http.delete<unknown>(`/platforms/${id}`)

// ---------- 账号管理 ----------

export interface User {
  id: number
  username: string
  name: string
  is_admin: boolean
  status: number
  platform_ids: number[]
  last_login_at: string | null
  created_at: string
}

export interface UserInput {
  username?: string
  name: string
  is_admin: boolean
  status: number
  password?: string
  platform_ids: number[]
}

export const listUsers = () => http.get<User[]>('/users')
export const createUser = (data: UserInput) => http.post<{ id: number }>('/users', data)
export const updateUser = (id: number, data: UserInput) => http.put<{ id: number }>(`/users/${id}`, data)
export const deleteUser = (id: number) => http.delete<unknown>(`/users/${id}`)

// ---------- 查看记录 ----------

export interface PasswordViewLog {
  id: number
  user_id: number
  platform_id: number
  ip: string
  created_at: string
  username: string | null
  user_name: string | null
  platform_title: string | null
}

export const listPasswordViewLogs = (query: { user_id?: number; platform_id?: number; page: number; page_size: number }) =>
  http.get<{ items: PasswordViewLog[]; total: number }>('/password-view-logs', query)
