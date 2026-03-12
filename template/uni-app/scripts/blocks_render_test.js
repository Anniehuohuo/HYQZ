const assert = require('assert')

function isArray(v) {
  return Array.isArray(v)
}

function validateBlocks(blocks) {
  assert.ok(isArray(blocks), 'blocks must be array')
  for (const b of blocks) {
    assert.ok(b && typeof b === 'object', 'block must be object')
    assert.ok(typeof b.type === 'string' && b.type.length > 0, 'block.type required')
    if (b.type === 'p') {
      assert.ok(isArray(b.inlines), 'p.inlines must be array')
    }
    if (b.type === 'list') {
      assert.ok(isArray(b.items), 'list.items must be array')
    }
    if (b.type === 'code') {
      assert.ok(typeof b.text === 'string', 'code.text must be string')
    }
    if (b.type === 'quote') {
      assert.ok(isArray(b.blocks), 'quote.blocks must be array')
    }
    if (b.type === 'image') {
      assert.ok(isArray(b.urls), 'image.urls must be array')
    }
  }
}

const cases = [
  {
    name: 'short_text',
    blocks: [{ type: 'p', inlines: [{ type: 'text', text: '你好' }] }],
  },
  {
    name: 'long_text',
    blocks: [{ type: 'p', inlines: [{ type: 'text', text: 'a'.repeat(20000) }] }],
  },
  {
    name: 'multi_paragraph',
    blocks: [
      { type: 'h2', text: '标题' },
      { type: 'p', inlines: [{ type: 'text', text: '第一段' }] },
      { type: 'p', inlines: [{ type: 'text', text: '第二段' }] },
      { type: 'list', ordered: false, items: [{ inlines: [{ type: 'text', text: '条目1' }] }] },
    ],
  },
  {
    name: 'rich_mix',
    blocks: [
      { type: 'h2', text: '三角模型分析' },
      { type: 'p', inlines: [{ type: 'text', text: '内容 {#FF3B30|重点}' }] },
      { type: 'code', lang: 'js', text: 'console.log(1)\n' },
      { type: 'quote', title: '引用', blocks: [{ type: 'p', inlines: [{ type: 'text', text: '卡片内容' }] }] },
      { type: 'image', urls: ['https://example.com/a.png'], aspectRatio: 0 },
    ],
  },
  {
    name: 'empty',
    blocks: [],
  },
]

for (const c of cases) {
  try {
    validateBlocks(c.blocks)
  } catch (e) {
    console.error('FAIL:', c.name, e.message)
    process.exit(1)
  }
}

console.log('OK: blocks render fixtures passed:', cases.length)

