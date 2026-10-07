import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import type { Me } from '@/api'

const TOKEN_KEY = 'ops-admin:token'

/** 登录令牌存 localStorage；当前账号信息进入后台时加载一次 */
export const useAuthStore = defineStore('ops-auth', () => {
  const token = ref<string | null>(localStorage.getItem(TOKEN_KEY))
  const me = ref<Me | null>(null)
  const isLoggedIn = computed(() => token.value !== null)
  const isAdmin = computed(() => me.value?.is_admin ?? false)

  function setToken(newToken: string) {
    token.value = newToken
    localStorage.setItem(TOKEN_KEY, newToken)
  }

  function setMe(value: Me) {
    me.value = value
  }

  function clear() {
    token.value = null
    me.value = null
    localStorage.removeItem(TOKEN_KEY)
  }

  return { token, me, isLoggedIn, isAdmin, setToken, setMe, clear }
})
