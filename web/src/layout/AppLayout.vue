<script setup lang="ts">
import { ArrowDown, Expand, Fold, Grid, Tickets, User } from '@element-plus/icons-vue'
import { computed, ref, type Component } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { changePassword } from '@/api'
import ChangePasswordDialog from '@/components/ChangePasswordDialog.vue'
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()
const route = useRoute()
const router = useRouter()
const title = import.meta.env.VITE_APP_TITLE
const collapsed = ref(false)
const passwordDialog = ref(false)

const menus: { path: string; title: string; icon: Component; admin?: boolean }[] = [
  { path: '/platforms', title: '平台', icon: Grid },
  { path: '/users', title: '账号管理', icon: User, admin: true },
  { path: '/password-logs', title: '密码查看记录', icon: Tickets, admin: true },
]
const visibleMenus = computed(() => menus.filter((m) => !m.admin || auth.isAdmin))
const displayName = computed(() => auth.me?.name || auth.me?.username || '')

function logout() {
  auth.clear()
  router.push({ name: 'login' })
}
</script>

<template>
  <el-container class="layout">
    <el-aside :width="collapsed ? '64px' : '200px'" class="aside">
      <div class="logo">{{ collapsed ? title.slice(0, 1) : title }}</div>
      <el-menu
        :default-active="route.path"
        :collapse="collapsed"
        :collapse-transition="false"
        router
        background-color="#001529"
        text-color="#bfcbd9"
        active-text-color="#ffffff"
        class="menu"
      >
        <el-menu-item v-for="item in visibleMenus" :key="item.path" :index="item.path">
          <el-icon><component :is="item.icon" /></el-icon>
          <template #title>{{ item.title }}</template>
        </el-menu-item>
      </el-menu>
    </el-aside>

    <el-container>
      <el-header class="header">
        <el-icon class="toggle" @click="collapsed = !collapsed">
          <Expand v-if="collapsed" />
          <Fold v-else />
        </el-icon>
        <span class="page-title">{{ route.meta.title }}</span>
        <el-dropdown trigger="click">
          <span class="user">
            {{ displayName }}
            <el-tag v-if="auth.isAdmin" size="small" effect="plain">管理员</el-tag>
            <el-icon><ArrowDown /></el-icon>
          </span>
          <template #dropdown>
            <el-dropdown-menu>
              <el-dropdown-item @click="passwordDialog = true">修改密码</el-dropdown-item>
              <el-dropdown-item divided @click="logout">退出登录</el-dropdown-item>
            </el-dropdown-menu>
          </template>
        </el-dropdown>
      </el-header>

      <el-main class="main">
        <router-view :key="route.path" />
      </el-main>
    </el-container>
  </el-container>

  <ChangePasswordDialog v-model="passwordDialog" :submit="changePassword" @changed="auth.setToken" />
</template>

<style scoped>
.layout {
  height: 100%;
}

.aside {
  background: #001529;
  overflow-x: hidden;
  transition: width 0.2s;
}

.logo {
  height: 60px;
  line-height: 60px;
  text-align: center;
  color: #fff;
  font-size: 17px;
  font-weight: 600;
  white-space: nowrap;
  overflow: hidden;
}

.menu {
  border-right: none;
}

.header {
  display: flex;
  align-items: center;
  gap: 16px;
  background: #fff;
  border-bottom: 1px solid #e4e7ed;
}

.toggle {
  font-size: 20px;
  cursor: pointer;
}

.page-title {
  flex: 1;
  font-size: 16px;
}

.user {
  display: flex;
  align-items: center;
  gap: 6px;
  cursor: pointer;
}

.main {
  padding: 20px;
}
</style>
