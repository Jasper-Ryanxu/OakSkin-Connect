<template>
  <div class="error-page">
    <div class="error-container">
      <div class="error-code">500</div>
      <h1>服务器错误</h1>
      <p>服务器遇到了意外错误，请稍后重试或联系站长。</p>
      <div class="error-actions">
        <router-link to="/" class="btn btn-secondary">
          <i class="fas fa-home"></i> 返回首页
        </router-link>
        <a href="javascript:location.reload()" class="btn btn-primary">
          <i class="fas fa-redo"></i> 刷新页面
        </a>
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
    radial-gradient(ellipse at 50% 0%, rgba(214, 48, 49, 0.08) 0%, transparent 60%);
}

.error-container {
  text-align: center;
  max-width: 420px;
}

.error-code {
  font-size: 96px;
  font-weight: 800;
  background: linear-gradient(135deg, #d63031, #e17055);
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