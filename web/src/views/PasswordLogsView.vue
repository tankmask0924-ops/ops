<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue'
import { listPasswordViewLogs, listPlatforms, listUsers, type PasswordViewLog, type Platform, type User } from '@/api'

const items = ref<PasswordViewLog[]>([])
const total = ref(0)
const users = ref<User[]>([])
const platforms = ref<Platform[]>([])
const loading = ref(false)
const query = reactive<{ user_id?: number; platform_id?: number; page: number; page_size: number }>({ page: 1, page_size: 20 })

async function load() {
  loading.value = true
  try {
    ;({ items: items.value, total: total.value } = await listPasswordViewLogs(query))
  } finally {
    loading.value = false
  }
}

function search() {
  query.page = 1
  load()
}

onMounted(async () => {
  load()
  ;[users.value, platforms.value] = await Promise.all([listUsers(), listPlatforms()])
})
</script>

<template>
  <el-card shadow="never" class="page-card">
    <div class="toolbar">
      <el-select v-model="query.user_id" placeholder="全部账号" clearable filterable style="width: 200px" @change="search">
        <el-option v-for="u in users" :key="u.id" :label="u.name ? `${u.name}（${u.username}）` : u.username" :value="u.id" />
      </el-select>
      <el-select v-model="query.platform_id" placeholder="全部平台" clearable filterable style="width: 200px" @change="search">
        <el-option v-for="p in platforms" :key="p.id" :label="p.title" :value="p.id" />
      </el-select>
      <span class="muted">每次查看或复制平台密码都会记录一条</span>
    </div>

    <el-table v-loading="loading" :data="items" border>
      <el-table-column prop="created_at" label="时间" width="170" />
      <el-table-column label="账号" min-width="160">
        <template #default="{ row }">
          <template v-if="row.username">{{ row.user_name ? `${row.user_name}（${row.username}）` : row.username }}</template>
          <span v-else class="muted">已删除（ID {{ row.user_id }}）</span>
        </template>
      </el-table-column>
      <el-table-column label="平台" min-width="160">
        <template #default="{ row }">
          <template v-if="row.platform_title">{{ row.platform_title }}</template>
          <span v-else class="muted">已删除（ID {{ row.platform_id }}）</span>
        </template>
      </el-table-column>
      <el-table-column prop="ip" label="IP" width="150" />
    </el-table>

    <div class="pager">
      <el-pagination
        v-model:current-page="query.page"
        v-model:page-size="query.page_size"
        :total="total"
        :page-sizes="[20, 50, 100]"
        layout="total, sizes, prev, pager, next"
        @current-change="load"
        @size-change="search"
      />
    </div>
  </el-card>
</template>
