<script setup lang="ts">
import { ElMessage, type FormInstance, type FormRules } from 'element-plus'
import { computed, nextTick, reactive, ref, watch } from 'vue'
import { createPlatform, fetchPlatformPassword, updatePlatform, type Platform, type PlatformInput, type User } from '@/api'

/** 管理员新增 / 修改平台，同时选择哪些普通账号能看到 */
const props = defineProps<{
  platform: Platform | null
  /** 已有的分类，下拉选择；也可以直接输入新分类 */
  categories: string[]
  /** 可分配的普通账号（管理员本来就能看到全部平台） */
  users: User[]
}>()

const emit = defineEmits<{ saved: [] }>()

const visible = defineModel<boolean>({ required: true })
const formRef = ref<FormInstance>()
const saving = ref(false)
const loadingPassword = ref(false)
const form = reactive<Required<PlatformInput>>({
  title: '',
  category: '',
  url: '',
  account: '',
  password: '',
  clear_password: false,
  description: '',
  user_ids: [],
})
const isEdit = computed(() => props.platform !== null)
const rules: FormRules = {
  title: [{ required: true, message: '请输入标题', trigger: 'blur' }],
}

watch(visible, async (open) => {
  if (!open) return
  const p = props.platform
  Object.assign(form, {
    title: p?.title ?? '',
    category: p?.category ?? '',
    url: p?.url ?? '',
    account: p?.account ?? '',
    password: '',
    clear_password: false,
    description: p?.description ?? '',
    user_ids: [...(p?.user_ids ?? [])],
  })
  await nextTick()
  formRef.value?.clearValidate()
})

async function loadCurrentPassword() {
  if (!props.platform) return
  loadingPassword.value = true
  try {
    form.password = await fetchPlatformPassword(props.platform.id)
  } finally {
    loadingPassword.value = false
  }
}

async function onSubmit() {
  if (!(await formRef.value?.validate().catch(() => false))) return
  saving.value = true
  try {
    if (props.platform) await updatePlatform(props.platform.id, form)
    else await createPlatform(form)
  } finally {
    saving.value = false
  }
  ElMessage.success('已保存')
  visible.value = false
  emit('saved')
}
</script>

<template>
  <el-dialog v-model="visible" :title="isEdit ? '编辑平台' : '新增平台'" width="560px">
    <el-form ref="formRef" :model="form" :rules="rules" label-width="80px" @submit.prevent="onSubmit">
      <el-form-item label="标题" prop="title">
        <el-input v-model="form.title" maxlength="100" placeholder="例如：阿里云控制台" />
      </el-form-item>
      <el-form-item label="分类" prop="category">
        <el-select
          v-model="form.category"
          filterable
          allow-create
          default-first-option
          clearable
          placeholder="选择已有分类，或直接输入新分类"
          style="width: 100%"
        >
          <el-option v-for="c in categories" :key="c" :label="c" :value="c" />
        </el-select>
      </el-form-item>
      <el-form-item label="网址" prop="url">
        <el-input v-model="form.url" maxlength="500" placeholder="https://..." />
      </el-form-item>
      <el-form-item label="账号" prop="account">
        <el-input v-model="form.account" maxlength="191" autocomplete="off" />
      </el-form-item>
      <el-form-item label="密码" prop="password">
        <el-input
          v-model="form.password"
          type="password"
          show-password
          maxlength="500"
          autocomplete="new-password"
          :placeholder="isEdit && platform?.has_password ? '不修改请留空' : ''"
          :disabled="form.clear_password"
        />
        <div v-if="isEdit && platform?.has_password" class="pwd-tools">
          <el-button link type="primary" :loading="loadingPassword" :disabled="form.clear_password" @click="loadCurrentPassword">
            载入当前密码
          </el-button>
          <el-checkbox v-model="form.clear_password" @change="form.password = ''">清空密码</el-checkbox>
        </div>
      </el-form-item>
      <el-form-item label="网站描述" prop="description">
        <el-input v-model="form.description" type="textarea" :rows="3" maxlength="1000" show-word-limit />
      </el-form-item>
      <el-form-item label="可见人员">
        <el-select v-model="form.user_ids" multiple filterable clearable placeholder="选择能看到这个平台的人（管理员默认都能看到）" style="width: 100%">
          <el-option
            v-for="u in users"
            :key="u.id"
            :label="u.name ? `${u.name}（${u.username}）` : u.username"
            :value="u.id"
            :disabled="u.status !== 1 && !form.user_ids.includes(u.id)"
          />
        </el-select>
      </el-form-item>
    </el-form>
    <template #footer>
      <el-button @click="visible = false">取消</el-button>
      <el-button type="primary" :loading="saving" @click="onSubmit">保存</el-button>
    </template>
  </el-dialog>
</template>

<style scoped>
.pwd-tools {
  display: flex;
  align-items: center;
  gap: 16px;
  margin-top: 4px;
}
</style>
