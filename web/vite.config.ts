import { fileURLToPath, URL } from 'node:url'
import vue from '@vitejs/plugin-vue'
import { defineConfig } from 'vite'

export default defineConfig({
  base: '/admin/',
  plugins: [vue()],
  resolve: {
    alias: {
      '@': fileURLToPath(new URL('./src', import.meta.url)),
    },
  },
  build: {
    // 打包到 PHP 的 public/admin，由后端直接提供页面
    outDir: '../public/admin',
    emptyOutDir: true,
    // Element Plus 全量引入，单个 chunk 约 1MB，后台系统可以接受
    chunkSizeWarningLimit: 1500,
  },
  server: {
    port: 5178,
    strictPort: true,
    proxy: {
      // docker-compose.yml 把容器的 9501 映射到宿主机 9505
      '/admin-api': {
        target: 'http://127.0.0.1:9505',
        changeOrigin: true,
      },
    },
  },
})
