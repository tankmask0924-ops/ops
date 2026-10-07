import { createRouter, createWebHistory } from 'vue-router'
import { fetchMe } from '@/api'
import AppLayout from '@/layout/AppLayout.vue'
import { useAuthStore } from '@/stores/auth'

declare module 'vue-router' {
  interface RouteMeta {
    /** 显示在顶栏和浏览器标题 */
    title?: string
    /** 无需登录即可访问 */
    public?: boolean
    /** 只有管理员能进 */
    admin?: boolean
  }
}

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: [
    {
      path: '/login',
      name: 'login',
      component: () => import('@/views/LoginView.vue'),
      meta: { title: '登录', public: true },
    },
    {
      path: '/',
      component: AppLayout,
      children: [
        { path: '', redirect: '/platforms' },
        {
          path: 'platforms',
          component: () => import('@/views/PlatformsView.vue'),
          meta: { title: '平台' },
        },
        {
          path: 'users',
          component: () => import('@/views/UsersView.vue'),
          meta: { title: '账号管理', admin: true },
        },
        {
          path: 'password-logs',
          component: () => import('@/views/PasswordLogsView.vue'),
          meta: { title: '密码查看记录', admin: true },
        },
      ],
    },
    { path: '/:pathMatch(.*)*', redirect: '/' },
  ],
})

const appTitle = import.meta.env.VITE_APP_TITLE

router.beforeEach(async (to) => {
  const auth = useAuthStore()
  if (to.meta.public) {
    return to.name === 'login' && auth.isLoggedIn ? { path: '/' } : true
  }
  if (!auth.isLoggedIn) {
    return { name: 'login', query: { redirect: to.fullPath } }
  }
  if (!auth.me) {
    try {
      auth.setMe(await fetchMe())
    } catch {
      // 401 已由拦截器处理跳转登录页
      return false
    }
  }
  if (to.meta.admin && !auth.isAdmin) {
    return { path: '/platforms' }
  }
  return true
})

router.afterEach((to) => {
  document.title = to.meta.title ? `${to.meta.title} - ${appTitle}` : appTitle
})

export default router
