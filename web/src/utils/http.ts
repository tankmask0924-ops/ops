import axios, { type AxiosError } from 'axios'
import { ElMessage } from 'element-plus'
import type { App } from 'vue'

/**
 * 接口错误已经由响应拦截器弹过提示，页面里的 try/finally 不再重复 catch；
 * 这里吞掉这类错误，避免控制台里一堆 Uncaught (in promise)，其他错误照常打印。
 */
export function ignoreHandledHttpErrors(app: App): void {
  app.config.errorHandler = (err) => {
    if (!axios.isAxiosError(err)) {
      console.error(err)
    }
  }
}

export interface HttpOptions {
  baseURL: string
  getToken: () => string | null
  /** 接口返回 401 时调用，一般是清除登录态并跳转登录页 */
  onUnauthorized: () => void
  timeout?: number
}

/** 直接返回响应体，调用方通过泛型声明返回类型 */
export interface Http {
  get<T>(url: string, params?: object): Promise<T>
  post<T>(url: string, data?: unknown, timeout?: number): Promise<T>
  put<T>(url: string, data?: unknown): Promise<T>
  delete<T>(url: string, params?: object): Promise<T>
}

export function extractMessage(data: unknown, fallback = '请求失败'): string {
  if (typeof data === 'string' && data !== '') {
    return data
  }
  if (data && typeof data === 'object' && 'message' in data && typeof data.message === 'string') {
    return data.message
  }
  return fallback
}

export function createHttp(options: HttpOptions): Http {
  const instance = axios.create({
    baseURL: options.baseURL,
    timeout: options.timeout ?? 35000,
  })

  instance.interceptors.request.use((config) => {
    const token = options.getToken()
    if (token) {
      config.headers.Authorization = `Bearer ${token}`
    }
    return config
  })

  instance.interceptors.response.use(undefined, (error: AxiosError) => {
    if (axios.isCancel(error)) {
      return Promise.reject(error)
    }
    ElMessage.error(extractMessage(error.response?.data, error.message || '请求失败'))
    // 只有带着 token 的请求返回 401 才是登录态失效；登录接口本身返回 401 不用跳转
    if (error.response?.status === 401 && error.config?.headers?.Authorization) {
      options.onUnauthorized()
    }
    return Promise.reject(error)
  })

  return {
    get: (url, params) => instance.get(url, { params }).then((res) => res.data),
    post: (url, data, timeout) => instance.post(url, data, timeout ? { timeout } : undefined).then((res) => res.data),
    put: (url, data) => instance.put(url, data).then((res) => res.data),
    delete: (url, params) => instance.delete(url, { params }).then((res) => res.data),
  }
}
