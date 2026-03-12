# Markdown 渲染一致性报告

- 用例数：8

## Headings h1-h6

### Input
```md
# H1
## H2
### H3
#### H4
##### H5
###### H6
```

### Output
```html
<h1>H1</h1><h2>H2</h2><h3>H3</h3><h4>H4</h4><h5>H5</h5><h6>H6</h6>
```

## Unordered list

### Input
```md
- A
- B
- C
```

### Output
```html
<ul><li>A</li><li>B</li><li>C</li></ul>
```

## Ordered list

### Input
```md
1. A
2) B
3. C
```

### Output
```html
<ol><li>A</li><li>B</li><li>C</li></ol>
```

## Inline code

### Input
```md
Use `code` here
```

### Output
```html
<p>Use <code>code</code> here</p>
```

## Fenced code block

### Input
```md
```js
const a = 1 < 2
```
```

### Output
```html
<pre><code class="language-js">const a = 1 &lt; 2</code></pre>
```

## Blockquote

### Input
```md
> quote line
```

### Output
```html
<blockquote>quote line</blockquote>
```

## Horizontal rule

### Input
```md
---
```

### Output
```html
<hr/>
```

## XSS escape

### Input
```md
<script>alert(1)</script>
```

### Output
```html
<p>&lt;script&gt;alert(1)&lt;/script&gt;</p>
```
