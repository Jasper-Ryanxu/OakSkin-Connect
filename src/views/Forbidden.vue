<template>
  <div class="forbidden-page">
    <div class="forbidden-container">
      <div class="forbidden-code">403</div>
      <h1>权限不足</h1>
      <p>你没有访问此页面的权限，请联系站长获取权限。</p>
      <div class="forbidden-actions">
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
.forbidden-page {
  min-height: calc(100vh - 60px);
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 40px 20px;
  background:
    radial-gradient(ellipse at 50% 0%, rgba(225, 112, 85, 0.08) 0%, transparent 60%);
}

.forbidden-container {
  text-align: center;
  max-width: 420px;
}

.forbidden-code {
  font-size: 96px;
  font-weight: 800;
  background: linear-gradient(135deg, #e17055, #d63031);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
  line-height: 1;
  margin-bottom: 8px;
}

.forbidden-container h1 {
  font-size: 24px;
  font-weight: 800;
  margin-bottom: 8px;
}

.forbidden-container p {
  font-size: 14px;
  color: var(--text-muted);
  margin-bottom: 28px;
}

.forbidden-actions {
  display: flex;
  gap: 12px;
  justify-content: center;
}
</style>