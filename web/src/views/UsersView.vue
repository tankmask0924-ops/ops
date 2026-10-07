<script setup lang="ts">
import { Plus } from '@element-plus/icons-vue'
import { ElMessage, ElMessageBox, type FormInstance, type FormRules } from 'element-plus'
import { computed, nextTick, onMounted, reactive, ref } from 'vue'
import { createUser, deleteUser, listPlatforms, listUsers, updateUser, type Platform, type User, type UserInput } from '@/api'
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()
const users = ref<User[]>([])
const platforms = ref<Platform[]>([])
const loading = ref(false)

const platformTitles = computed(() => Object.fromEntries(platforms.value.map((p) => [p.id, p.title])))

async function load() {
  loading.value = true
  try {
    ;[users.value, platforms.value] = await Promise.all([listUsers(), listPlatforms()])
  } finally {
    loading.value = false
  }
}

const dialog = ref(false)
const saving = ref(false)
const editing = ref<User | null>(null)
const formRef = ref<FormInstance>()
const form = reactive<Required<UserInput>>({ username: '', name: '', is_admin: false, status: 1, password: '', platform_ids: [] })
const isSelf = computed(() => editing.value?.id === auth.me?.id)
const rules = computed<FormRules>(() => ({
  username: [
    { required: true, message: '请输入账号', trigger: 'blur' },
    { pattern: /^[A-Za-z0-9_.@-]{3,50}$/, message: '字母、数字和 _.@-，3～50 位', trigger: 'blur' },
  ],
  password: editing.value
    ? [{ min: 8, message: '至少 8 位', trigger: 'blur' }]
    : [
        { required: true, message: '请输入密码', trigger: 'blur' },
        { min: 8, message: '至少 8 位', trigger: 'blur' },
      ],
}))

async function open(user: User | null) {
  editing.value = user
  Object.assign(form, {
    username: user?.username ?? '',
    name: user?.name ?? '',
    is_admin: user?.is_admin ?? false,
    status: user?.status ?? 1,
    password: '',
    platform_ids: [...(user?.platform_ids ?? [])],
  })
  dialog.value = true
  await nextTick()
  formRef.value?.clearValidate()
}

async function save() {
  if (!(await formRef.value?.validate().catch(() => false))) return
  saving.value = true
  try {
    if (editing.value) {
      const { username: _ignored, ...data } = form
      await updateUser(editing.value.id, data)
    } else {
      await createUser(form)
    }
  } finally {
    saving.value = false
  }
  ElMessage.success('已保存')
  dialog.value = false
  load()
}

async function remove(user: User) {
  await ElMessageBox.confirm(`确定删除账号「${user.username}」？`, '删除账号', { type: 'warning' })
  await deleteUser(user.id)
  ElMessage.success('已删除')
  load()
}

onMounted(load)
</script>

<template>
  <el-card shadow="never" class="page-card">
    <div class="toolbar">
      <span class="muted">管理员能看到并管理全部平台；普通账号只能看到分配给自己的平台。</span>
      <span class="spacer" />
      <el-button type="primary" :icon="Plus" @click="open(null)">新增账号</el-button>
    </div>

    <el-table v-loading="loading" :data="users" border>
      <el-table-column prop="username" label="账号" min-width="120" />
      <el-table-column prop="name" label="姓名" min-width="100" />
      <el-table-column label="身份" width="90">
        <template #default="{ row }">
          <el-tag v-if="row.is_admin" type="danger" effect="plain">管理员</el-tag>
          <el-tag v-else effect="plain">普通</el-tag>
        </template>
      </el-table-column>
      <el-table-column label="可见平台" min-width="260">
        <template #default="{ row }">
          <span v-if="row.is_admin" class="muted">全部</span>
          <span v-else-if="row.platform_ids.length === 0" class="muted">未分配</span>
          <template v-else>
            <el-tag v-for="id in row.platform_ids" :key="id" size="small" class="tag">{{ platformTitles[id] ?? id }}</el-tag>
          </template>
        </template>
      </el-table-column>
      <el-table-column label="状态" width="80">
        <template #default="{ row }">
          <el-tag :type="row.status === 1 ? 'success' : 'info'">{{ row.status === 1 ? '启用' : '禁用' }}</el-tag>
        </template>
      </el-table-column>
      <el-table-column prop="last_login_at" label="最近登录" width="170" />
      <el-table-column label="操作" width="130" fixed="right">
        <template #default="{ row }">
          <el-button link type="primary" @click="open(row as User)">编辑</el-button>
          <el-button link type="danger" :disabled="row.id === auth.me?.id" @click="remove(row as User)">删除</el-button>
        </template>
      </el-table-column>
    </el-table>

    <el-dialog v-model="dialog" :title="editing ? '编辑账号' : '新增账号'" width="560px">
      <el-form ref="formRef" :model="form" :rules="rules" label-width="80px" @submit.prevent="save">
        <el-form-item label="账号" prop="username">
          <el-input v-model="form.username" :disabled="!!editing" autocomplete="off" />
        </el-form-item>
        <el-form-item label="姓名" prop="name">
          <el-input v-model="form.name" maxlength="50" />
        </el-form-item>
        <el-form-item :label="editing ? '重置密码' : '密码'" prop="password">
          <el-input
            v-model="form.password"
            type="password"
            show-password
            autocomplete="new-password"
            :placeholder="editing ? '不修改请留空' : '至少 8 位'"
          />
        </el-form-item>
        <el-form-item label="管理员">
          <el-switch v-model="form.is_admin" :disabled="isSelf" />
          <span class="muted hint">管理员能看到全部平台，能管理平台和账号</span>
        </el-form-item>
        <el-form-item v-if="!form.is_admin" label="可见平台">
          <el-select v-model="form.platform_ids" multiple filterable clearable placeholder="选择这个人能看到的平台" style="width: 100%">
            <el-option v-for="p in platforms" :key="p.id" :label="p.title" :value="p.id" />
          </el-select>
        </el-form-item>
        <el-form-item label="状态">
          <el-radio-group v-model="form.status" :disabled="isSelf">
            <el-radio :value="1">启用</el-radio>
            <el-radio :value="0">禁用</el-radio>
          </el-radio-group>
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="dialog = false">取消</el-button>
        <el-button type="primary" :loading="saving" @click="save">保存</el-button>
      </template>
    </el-dialog>
  </el-card>
</template>

<style scoped>
.tag {
  margin: 2px 4px 2px 0;
}

.hint {
  margin-left: 12px;
}
</style>
