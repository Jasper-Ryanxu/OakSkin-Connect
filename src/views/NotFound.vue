<template>
  <div class="error-page">
    <div class="error-container">
      <div class="error-code">404</div>
      <h1>页面未找到</h1>
      <p>你访问的页面不存在或已被移除，请检查链接是否正确。</p>
      <div class="error-actions">
        <router-link to="/" class="btn btn-secondary">
          <i class="fas fa-home"></i> 返回首页
        </router-link>
        <router-link to="/dashboard" class="btn btn-primary">
          <i class="fas fa-chart-pie"></i> 我的仪表盘
        </router-link>
      </div>
    </div>
  </div>
</template>

<style scoped>
.error-page {
  min-height: calc(100vh - 60px);
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 40px 20px;
  background:
    radial-gradient(ellipse at 50% 0%, rgba(108, 92, 231, 0.08) 0%, transparent 60%);
}

.error-container {
  text-align: center;
  max-width: 420px;
}

.error-code {
  font-size: 96px;
  font-weight: 800;
  background: linear-gradient(135deg, #6c5ce7, #a29bfe);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
  line-height: 1;
  margin-bottom: 8px;
}

.error-container h1 {
  font-size: 24px;
  font-weight: 800;
  margin-bottom: 8px;
}

.error-container p {
  font-size: 14px;
  color: var(--text-muted);
  margin-bottom: 28px;
}

.error-actions {
  display: flex;
  gap: 12px;
  justify-content: center;
}
</style>