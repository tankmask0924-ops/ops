<script setup lang="ts">
import { CopyDocument, Delete, Edit, Hide, Link, MoreFilled, Plus, Search, View } from '@element-plus/icons-vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { computed, onMounted, reactive, ref } from 'vue'
import { deletePlatform, fetchPlatformPassword, listPlatforms, listUsers, type Platform, type User } from '@/api'
import PlatformDialog from '@/components/PlatformDialog.vue'
import { useAuthStore } from '@/stores/auth'
import { copyText } from '@/utils/clipboard'

const auth = useAuthStore()
const platforms = ref<Platform[]>([])
const users = ref<User[]>([])
const keyword = ref('')
const loading = ref(false)
/** 已经点开查看的密码明文，按平台 id 存；再点一次眼睛就收起 */
const revealed = reactive<Record<number, string>>({})

const normalUsers = computed(() => users.value.filter((u) => !u.is_admin))

async function load() {
  loading.value = true
  try {
    platforms.value = await listPlatforms(keyword.value.trim())
  } finally {
    loading.value = false
  }
}

function hostOf(url: string): string {
  try {
    return new URL(url).host
  } catch {
    return url
  }
}

const COLORS = ['#409eff', '#67c23a', '#e6a23c', '#f56c6c', '#909399', '#9b59b6', '#16a085', '#d35400']
function colorOf(title: string): string {
  let sum = 0
  for (const ch of title) sum += ch.codePointAt(0) ?? 0
  return COLORS[sum % COLORS.length]!
}

function open(p: Platform) {
  if (!p.url) {
    ElMessage.warning('这个平台没有填写网址')
    return
  }
  window.open(p.url, '_blank', 'noopener,noreferrer')
}

async function togglePassword(p: Platform) {
  if (p.id in revealed) {
    delete revealed[p.id]
    return
  }
  revealed[p.id] = await fetchPlatformPassword(p.id)
}

async function copyPassword(p: Platform) {
  const password = revealed[p.id] ?? (await fetchPlatformPassword(p.id))
  await copyText(password, '密码')
}

// ---------- 管理员：新增 / 修改 / 删除 ----------

const dialogVisible = ref(false)
const editing = ref<Platform | null>(null)

function openDialog(p: Platform | null) {
  editing.value = p
  dialogVisible.value = true
}

function onSaved() {
  if (editing.value) delete revealed[editing.value.id]
  load()
}

async function remove(p: Platform) {
  await ElMessageBox.confirm(`确定删除平台「${p.title}」？删除后所有人都看不到了。`, '删除平台', { type: 'warning' })
  await deletePlatform(p.id)
  ElMessage.success('已删除')
  load()
}

function onCommand(command: 'edit' | 'delete', p: Platform) {
  if (command === 'edit') openDialog(p)
  else remove(p)
}

onMounted(async () => {
  await load()
  if (auth.isAdmin) users.value = await listUsers()
})
</script>

<template>
  <div v-loading="loading">
    <div class="toolbar">
      <el-input
        v-model="keyword"
        placeholder="搜索标题、网址、账号、描述"
        clearable
        :prefix-icon="Search"
        style="width: 280px"
        @keyup.enter="load"
        @clear="load"
      />
      <el-button @click="load">搜索</el-button>
      <span class="muted">共 {{ platforms.length }} 个平台</span>
      <span class="spacer" />
      <el-button v-if="auth.isAdmin" type="primary" :icon="Plus" @click="openDialog(null)">新增平台</el-button>
    </div>

    <el-empty
      v-if="!loading && platforms.length === 0"
      :description="keyword ? '没有找到匹配的平台' : auth.isAdmin ? '还没有平台，点右上角「新增平台」' : '还没有给你分配平台，请联系管理员'"
    />

    <div class="grid">
      <div
        v-for="p in platforms"
        :key="p.id"
        class="card"
        :class="{ clickable: !!p.url }"
        :title="p.url ? `打开 ${p.url}` : ''"
        @click="open(p)"
      >
        <div class="card-head">
          <div class="avatar" :style="{ background: colorOf(p.title) }">{{ p.title.slice(0, 1) }}</div>
          <div class="head-text">
            <div class="title">{{ p.title }}</div>
            <div class="host">
              <template v-if="p.url"><el-icon><Link /></el-icon>{{ hostOf(p.url) }}</template>
              <template v-else>未填写网址</template>
            </div>
          </div>
          <div v-if="auth.isAdmin" @click.stop>
            <el-dropdown trigger="click" @command="(c: 'edit' | 'delete') => onCommand(c, p)">
              <el-button text circle :icon="MoreFilled" />
              <template #dropdown>
                <el-dropdown-menu>
                  <el-dropdown-item command="edit" :icon="Edit">编辑</el-dropdown-item>
                  <el-dropdown-item command="delete" :icon="Delete">删除</el-dropdown-item>
                </el-dropdown-menu>
              </template>
            </el-dropdown>
          </div>
        </div>

        <div class="desc" :class="{ empty: !p.description }">{{ p.description || '暂无描述' }}</div>

        <div class="cred" @click.stop>
          <div class="row">
            <span class="label">账号</span>
            <span class="value mono">{{ p.account || '-' }}</span>
            <el-button v-if="p.account" text size="small" :icon="CopyDocument" title="复制账号" @click="copyText(p.account, '账号')" />
          </div>
          <div class="row">
            <span class="label">密码</span>
            <span class="value mono">{{ !p.has_password ? '-' : p.id in revealed ? revealed[p.id] : '••••••••' }}</span>
            <template v-if="p.has_password">
              <el-button
                text
                size="small"
                :icon="p.id in revealed ? Hide : View"
                :title="p.id in revealed ? '隐藏' : '查看密码'"
                @click="togglePassword(p)"
              />
              <el-button text size="small" :icon="CopyDocument" title="复制密码" @click="copyPassword(p)" />
            </template>
          </div>
        </div>
      </div>
    </div>

    <PlatformDialog v-if="auth.isAdmin" v-model="dialogVisible" :platform="editing" :users="normalUsers" @saved="onSaved" />
  </div>
</template>

<style scoped>
.grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
  gap: 16px;
}

.card {
  display: flex;
  flex-direction: column;
  gap: 12px;
  padding: 16px;
  background: #fff;
  border: 1px solid #e4e7ed;
  border-radius: 8px;
  transition:
    box-shadow 0.2s,
    border-color 0.2s,
    transform 0.2s;
}

.card.clickable {
  cursor: pointer;
}

.card.clickable:hover {
  border-color: #409eff;
  box-shadow: 0 6px 16px rgba(0, 0, 0, 0.08);
  transform: translateY(-2px);
}

.card-head {
  display: flex;
  align-items: center;
  gap: 12px;
}

.avatar {
  flex: none;
  width: 40px;
  height: 40px;
  border-radius: 8px;
  color: #fff;
  font-size: 18px;
  font-weight: 600;
  line-height: 40px;
  text-align: center;
}

.head-text {
  flex: 1;
  min-width: 0;
}

.title {
  font-size: 16px;
  font-weight: 600;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.host {
  display: flex;
  align-items: center;
  gap: 4px;
  margin-top: 2px;
  color: #909399;
  font-size: 12px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.desc {
  min-height: 40px;
  color: #606266;
  font-size: 13px;
  line-height: 20px;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
  word-break: break-all;
}

.desc.empty {
  color: #c0c4cc;
}

.cred {
  padding: 8px 10px;
  background: #f5f7fa;
  border-radius: 6px;
  cursor: default;
}

.row {
  display: flex;
  align-items: center;
  gap: 4px;
  min-height: 28px;
  font-size: 13px;
}

.row .label {
  flex: none;
  width: 36px;
  color: #909399;
}

.row .value {
  flex: 1;
  min-width: 0;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.row .el-button + .el-button {
  margin-left: 0;
}
</style>
