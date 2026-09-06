<template>
  <div class="config-page">
    <div class="container">
      <div class="page-header">
        <h1><i class="fas fa-wrench"></i> 模组配置</h1>
        <p>将本站接入你的启动器 / 客户端模组，使用你的角色皮肤与披风</p>
      </div>

      <!-- 站点信息 -->
      <div class="site-info-card">
        <div class="site-info-item">
          <span class="info-label"><i class="fas fa-globe"></i> 站点地址</span>
          <code class="info-value">{{ baseUrl }}</code>
        </div>
        <div class="site-info-item">
          <span class="info-label"><i class="fas fa-palette"></i> 材质加载</span>
          <code class="info-value">{{ baseUrl }}/skins</code>
        </div>
      </div>

      <!-- 配置卡片 -->
      <div class="config-cards">
        <div v-for="cfg in configs" :key="cfg.key" class="config-card">
          <div class="config-card-header">
            <div class="config-icon" :class="cfg.color">
              <i :class="cfg.icon"></i>
            </div>
            <div class="config-meta">
              <h3>{{ cfg.name }}</h3>
              <p>{{ cfg.desc }}</p>
            </div>
            <button class="btn btn-secondary btn-sm copy-btn" @click="copyConfig(cfg)">
              <i class="fas" :class="cfg.copied ? 'fa-check' : 'fa-copy'"></i>
              {{ cfg.copied ? '已复制' : '复制配置' }}
            </button>
          </div>
          <div class="config-code">
            <pre v-html="highlight(cfg.body)"></pre>
          </div>
          <p class="config-tip"><i class="fas fa-lightbulb"></i> {{ cfg.tip }}</p>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue'

const baseUrl = computed(() => {
  return typeof window !== 'undefined' ? window.location.origin : ''
})

// 生成 JSON 配置字符串
function jsonConfig(obj) {
  return JSON.stringify(obj, null, 2)
}

const configs = computed(() => {
  const origin = baseUrl.value
  return [
      {
        key: 'csl',
        name: 'CustomSkinLoader (CSL)',
        icon: 'fas fa-cube',
        color: 'grad-green',
        desc: '离线模式加载皮肤与披风的经典模组配置',
      tip: '将下方内容写入 .minecraft/config/CustomSkinLoader.CSL 并重启游戏。',
      body:
`# CustomSkinLoader 配置
["CustomSkinLoader": {
    "target": "magic",
    "manualLoadList": [
        {
            "name": "OakSkin",
            "type": "JsonAPI",
            "root": "${origin}/skins"
        }
    ],
    "enableDynamicSkin": true,
    "tryLoadDefault": true
}]`
    },
    {
      key: 'usc',
      name: 'Universal Skin Cheap (USC)',
      icon: 'fas fa-user',
      color: 'grad-amber',
      desc: '统一皮肤站，适用于离线 / 单机环境',
      tip: '在 USC 配置中加入本站节点即可同步加载所有皮肤。',
      body: jsonConfig({
        apiRoot: `${origin}/skins`,
        userAgent: 'Mozilla/5.0',
        timeout: 5
      })
    }
  ]
})

function escapeHtml(str) {
  return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
}

function highlight(code) {
  const html = escapeHtml(code)
  return html.replace(/(\".*?\")/g, '<span class="tok-str">$1</span>')
}

function copyConfig(cfg) {
  window.__cfgCopied = cfg
  if (navigator.clipboard && window.isSecureContext) {
    navigator.clipboard.writeText(cfg.body).then(() => flashCopied(cfg)).catch(() => fallbackCopy(cfg))
  } else {
    fallbackCopy(cfg)
  }
}

function fallbackCopy(cfg) {
  const ta = document.createElement('textarea')
  ta.value = cfg.body
  ta.style.position = 'fixed'
  ta.style.opacity = '0'
  document.body.appendChild(ta)
  ta.select()
  try {
    document.execCommand('copy')
  } catch (e) {}
  document.body.removeChild(ta)
  flashCopied(cfg)
}

function flashCopied(cfg) {
  if (cfg._timer) clearTimeout(cfg._timer)
  cfg.copied = true
  cfg._timer = setTimeout(() => { cfg.copied = false }, 1800)
}
</script>

<style scoped>
.config-page {
  padding: 30px 0 60px;
}

.page-header {
  text-align: center;
  margin-bottom: 24px;
}

.page-header h1 {
  font-size: 24px;
  font-weight: 800;
  margin-bottom: 6px;
}

.page-header h1 i {
  color: var(--accent-primary);
  margin-right: 6px;
}

.page-header p {
  color: var(--text-muted);
  font-size: 14px;
}

/* Site info card */
.site-info-card {
  background: var(--bg-card);
  border: 1px solid var(--border-color);
  border-radius: var(--border-radius);
  padding: 20px 24px;
  margin-bottom: 24px;
  display: grid;
  gap: 12px;
}

.site-info-item {
  display: flex;
  align-items: center;
  gap: 12px;
  flex-wrap: wrap;
}

.info-label {
  font-size: 13px;
  font-weight: 600;
  color: var(--text-secondary);
  min-width: 140px;
  display: inline-flex;
  align-items: center;
  gap: 6px;
}

.info-label i {
  color: var(--accent-primary);
}

.info-value {
  background: var(--bg-tertiary);
  border: 1px solid var(--border-color);
  border-radius: 6px;
  padding: 4px 10px;
  font-size: 12px;
  color: var(--accent-secondary);
  word-break: break-all;
}

/* Config cards */
.config-cards {
  display: grid;
  grid-template-columns: 1fr;
  gap: 20px;
}

.config-card {
  background: var(--bg-card);
  border: 1px solid var(--border-color);
  border-radius: var(--border-radius);
  overflow: hidden;
}

.config-card-header {
  display: flex;
  align-items: center;
  gap: 14px;
  padding: 18px 20px;
  border-bottom: 1px solid var(--border-color);
}

.config-icon {
  width: 44px;
  height: 44px;
  border-radius: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #fff;
  font-size: 18px;
  flex-shrink: 0;
}

.grad-blue { background: linear-gradient(135deg, #0984e3, #74b9ff); }
.grad-green { background: linear-gradient(135deg, #00b894, #55efc4); }
.grad-amber { background: linear-gradient(135deg, #fdcb6e, #f39c12); }

.config-meta {
  flex: 1;
}

.config-meta h3 {
  font-size: 15px;
  font-weight: 700;
  margin-bottom: 2px;
}

.config-meta p {
  font-size: 12px;
  color: var(--text-muted);
}

.copy-btn {
  flex-shrink: 0;
}

.config-code {
  background: #181830;
  padding: 18px 20px;
  overflow-x: auto;
}

.config-code pre {
  margin: 0;
  font-family: 'Consolas', 'Courier New', monospace;
  font-size: 12px;
  line-height: 1.6;
  color: #8be9fd;
  white-space: pre-wrap;
  word-break: break-all;
}

.tok-str {
  color: #50fa7b;
}

.config-tip {
  padding: 12px 20px;
  font-size: 12px;
  color: var(--text-muted);
  background: var(--bg-tertiary);
  display: flex;
  align-items: center;
  gap: 6px;
}

.config-tip i {
  color: var(--warning);
}

@media (max-width: 640px) {
  .config-card-header {
    flex-wrap: wrap;
  }
  .copy-btn {
    margin-left: auto;
  }
}
</style>