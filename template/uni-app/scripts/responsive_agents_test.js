const assert = require('assert')

/**
 * 根据屏幕宽度推导“智能体矩阵”卡片列数。
 * 该逻辑需与 pages/ai/agents.vue 中的媒体查询保持一致：
 * - max-width: 360px  => 1 列
 * - min-width: 1024px => 3 列
 * - 其他              => 2 列
 *
 * @param {number} widthPx 屏幕宽度（px）
 * @returns {number} 列数（1/2/3）
 */
function calcAgentGridColumns(widthPx) {
  const w = Number(widthPx) || 0
  if (w <= 360) return 1
  if (w >= 1024) return 3
  return 2
}

function run() {
  const cases = [
    { w: 320, expect: 1 },
    { w: 360, expect: 1 },
    { w: 361, expect: 2 },
    { w: 375, expect: 2 },
    { w: 414, expect: 2 },
    { w: 768, expect: 2 },
    { w: 1024, expect: 3 },
    { w: 1280, expect: 3 },
  ]

  cases.forEach((c) => {
    assert.strictEqual(
      calcAgentGridColumns(c.w),
      c.expect,
      `width=${c.w} should be ${c.expect} columns`
    )
  })

  console.log('OK: responsive agents grid cases passed:', cases.length)
}

if (require.main === module) run()

module.exports = { calcAgentGridColumns }

