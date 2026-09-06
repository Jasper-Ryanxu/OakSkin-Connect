/**
 * 轻量级 Markdown 渲染
 * 支持：标题、粗体、斜体、链接、图片、行内代码、代码块、列表、引用、分割线
 */
export function renderMarkdown(text) {
  if (!text) return ''

  let html = text
    // 转义 HTML
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')

  // 代码块 (```) - 必须在其他处理之前
  html = html.replace(/```(\w*)\n([\s\S]*?)```/g, (_m, _lang, code) => {
    return `<pre><code>${code.trim()}</code></pre>`
  })

  // 行内代码
  html = html.replace(/`([^`]+)`/g, '<code>$1</code>')

  // 分割线
  html = html.replace(/^---$/gm, '<hr>')

  // 标题
  html = html.replace(/^### (.+)$/gm, '<h3>$1</h3>')
  html = html.replace(/^## (.+)$/gm, '<h2>$1</h2>')
  html = html.replace(/^# (.+)$/gm, '<h1>$1</h1>')

  // 引用
  html = html.replace(/^> (.+)$/gm, '<blockquote>$1</blockquote>')

  // 无序列表
  html = html.replace(/^- (.+)$/gm, '<li>$1</li>')
  html = html.replace(/(<li>.*<\/li>\n?)+/g, '<ul>$&</ul>')

  // 有序列表
  html = html.replace(/^\d+\. (.+)$/gm, '<li>$1</li>')

  // 图片
  html = html.replace(/!\[([^\]]*)\]\(([^)]+)\)/g, '<img src="$2" alt="$1" style="max-width:100%">')

  // 链接
  html = html.replace(/\[([^\]]+)\]\(([^)]+)\)/g, '<a href="$2" target="_blank" rel="noopener">$1</a>')

  // 粗体 + 斜体
  html = html.replace(/\*\*\*(.+?)\*\*\*/g, '<strong><em>$1</em></strong>')
  html = html.replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>')
  html = html.replace(/\*(.+?)\*/g, '<em>$1</em>')

  // 段落（连续两行换行分割）
  const blocks = html.split(/\n\n+/)
  html = blocks
    .map(block => {
      block = block.trim()
      if (!block) return ''
      // 已经是块级元素的不再包裹
      if (/^<(h[1-3]|ul|ol|li|pre|blockquote|hr|table)/.test(block)) return block
      // 单行处理
      const lines = block.split('\n')
      if (lines.length === 1) return `<p>${lines[0]}</p>`
      return `<p>${lines.join('<br>')}</p>`
    })
    .join('\n')

  return html
}