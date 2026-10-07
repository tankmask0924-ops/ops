import { ElMessage } from 'element-plus'

/** 复制到剪贴板；http 访问时浏览器不给用 navigator.clipboard，退回到 execCommand */
export async function copyText(text: string, label = '内容'): Promise<void> {
  try {
    if (navigator.clipboard && window.isSecureContext) {
      await navigator.clipboard.writeText(text)
    } else {
      const el = document.createElement('textarea')
      el.value = text
      el.setAttribute('readonly', '')
      el.style.position = 'fixed'
      el.style.opacity = '0'
      document.body.appendChild(el)
      el.select()
      const ok = document.execCommand('copy')
      el.remove()
      if (!ok) throw new Error('copy failed')
    }
    ElMessage.success(`${label}已复制`)
  } catch {
    ElMessage.error('复制失败，请手动复制')
  }
}
