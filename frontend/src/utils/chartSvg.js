// Hand-built SVG chart export. Deliberately not canvas-to-SVG conversion —
// it re-draws straight from the same {labels, datasets} objects each chart
// already computes for Chart.js, so colors/values are guaranteed to match
// what's on screen. Always light-mode; this is a standalone exported file,
// not a themed UI surface.
const COLORS = {
  bg: '#fff8f0',
  border: '#f2e1d4',
  text: '#4a3b36',
  muted: '#a8938c',
  grid: '#f0e4d8',
}

function esc(s) {
  return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;')
}

function niceMax(value) {
  if (value <= 0) return 1
  const magnitude = Math.pow(10, Math.floor(Math.log10(value)))
  const residual = value / magnitude
  let niceResidual
  if (residual > 5) niceResidual = 10
  else if (residual > 2) niceResidual = 5
  else if (residual > 1) niceResidual = 2
  else niceResidual = 1
  return niceResidual * magnitude
}

function formatHourLabel(h) {
  const hh = h % 12 === 0 ? 12 : h % 12
  return `${hh}${h < 12 || h === 24 ? 'am' : 'pm'}`
}

function renderHeader(parts, { title, description, filterNote }) {
  let y = 30
  parts.push(`<text x="24" y="${y}" font-size="18" font-weight="700" fill="${COLORS.text}" font-family="sans-serif">${esc(title)}</text>`)
  y += 20
  if (description) {
    parts.push(`<text x="24" y="${y}" font-size="12" fill="${COLORS.muted}" font-family="sans-serif">${esc(description)}</text>`)
    y += 18
  }
  if (filterNote) {
    parts.push(
      `<text x="24" y="${y}" font-size="11" fill="${COLORS.muted}" font-family="sans-serif" font-style="italic">${esc(filterNote)}</text>`
    )
    y += 18
  }
  return y + 8
}

function renderLegend(parts, datasets, x, y) {
  let cx = x
  for (const ds of datasets) {
    if (!ds.label) continue
    const rawColor = ds.backgroundColor || ds.borderColor || ds.pointBackgroundColor || '#999'
    const color = Array.isArray(rawColor) ? rawColor[0] : rawColor
    parts.push(`<rect x="${cx}" y="${y - 10}" width="12" height="12" rx="3" fill="${color}" />`)
    parts.push(`<text x="${cx + 16}" y="${y}" font-size="12" fill="${COLORS.text}" font-family="sans-serif">${esc(ds.label)}</text>`)
    cx += ds.label.length * 6.4 + 28
  }
  return y + 20
}

function renderBarChart(parts, data, { stacked }, chartX, chartY, chartW, chartH) {
  const labels = data.labels
  const datasets = data.datasets
  const n = labels.length
  let maxVal = 0
  if (stacked) {
    for (let i = 0; i < n; i++) {
      const sum = datasets.reduce((s, ds) => s + (Number(ds.data[i]) || 0), 0)
      maxVal = Math.max(maxVal, sum)
    }
  } else {
    for (const ds of datasets) for (const v of ds.data) maxVal = Math.max(maxVal, Number(v) || 0)
  }
  const axisMax = niceMax(maxVal || 1)
  const gridLines = 4
  for (let g = 0; g <= gridLines; g++) {
    const val = (axisMax / gridLines) * g
    const y = chartY + chartH - (val / axisMax) * chartH
    parts.push(`<line x1="${chartX}" y1="${y}" x2="${chartX + chartW}" y2="${y}" stroke="${COLORS.grid}" stroke-width="1" />`)
    parts.push(
      `<text x="${chartX - 8}" y="${y + 4}" font-size="10" fill="${COLORS.muted}" font-family="sans-serif" text-anchor="end">${Math.round(val * 10) / 10}</text>`
    )
  }

  const slotW = chartW / n
  const barGroupW = slotW * 0.7
  const barGroupX0 = (i) => chartX + i * slotW + (slotW - barGroupW) / 2

  if (stacked) {
    for (let i = 0; i < n; i++) {
      let yCursor = chartY + chartH
      for (const ds of datasets) {
        const v = Number(ds.data[i]) || 0
        const h = (v / axisMax) * chartH
        const y = yCursor - h
        parts.push(`<rect x="${barGroupX0(i)}" y="${y}" width="${barGroupW}" height="${Math.max(h, 0)}" fill="${ds.backgroundColor}" rx="2" />`)
        yCursor = y
      }
    }
  } else {
    const barW = barGroupW / datasets.length
    for (let i = 0; i < n; i++) {
      datasets.forEach((ds, di) => {
        const v = Number(ds.data[i]) || 0
        const h = (v / axisMax) * chartH
        const x = barGroupX0(i) + di * barW
        const y = chartY + chartH - h
        parts.push(
          `<rect x="${x + 1}" y="${y}" width="${Math.max(barW - 2, 1)}" height="${Math.max(h, 0)}" fill="${ds.backgroundColor}" rx="2" />`
        )
      })
    }
  }

  const skip = Math.max(1, Math.ceil(n / 15))
  for (let i = 0; i < n; i++) {
    if (i % skip !== 0 && i !== n - 1) continue
    const x = chartX + i * slotW + slotW / 2
    parts.push(
      `<text x="${x}" y="${chartY + chartH + 16}" font-size="9" fill="${COLORS.muted}" font-family="sans-serif" text-anchor="middle">${esc(labels[i])}</text>`
    )
  }
  parts.push(`<line x1="${chartX}" y1="${chartY + chartH}" x2="${chartX + chartW}" y2="${chartY + chartH}" stroke="${COLORS.border}" stroke-width="1.5" />`)
}

function renderLineChart(parts, data, chartX, chartY, chartW, chartH) {
  const labels = data.labels
  const n = labels.length
  const datasets = data.datasets
  let maxVal = -Infinity
  let minVal = Infinity
  for (const ds of datasets) {
    for (const v of ds.data) {
      if (v == null) continue
      maxVal = Math.max(maxVal, v)
      minVal = Math.min(minVal, v)
    }
  }
  if (!isFinite(maxVal)) {
    maxVal = 1
    minVal = 0
  }
  const span0 = maxVal - minVal || 1
  const axisMin = Math.floor((minVal - span0 * 0.1) * 10) / 10
  const axisMax = Math.ceil((maxVal + span0 * 0.1) * 10) / 10
  const span = axisMax - axisMin || 1

  const gridLines = 4
  for (let g = 0; g <= gridLines; g++) {
    const val = axisMin + (span / gridLines) * g
    const y = chartY + chartH - ((val - axisMin) / span) * chartH
    parts.push(`<line x1="${chartX}" y1="${y}" x2="${chartX + chartW}" y2="${y}" stroke="${COLORS.grid}" stroke-width="1" />`)
    parts.push(`<text x="${chartX - 8}" y="${y + 4}" font-size="10" fill="${COLORS.muted}" text-anchor="end" font-family="sans-serif">${val.toFixed(1)}</text>`)
  }

  const xAt = (i) => chartX + (n <= 1 ? chartW / 2 : (i / (n - 1)) * chartW)
  const yAt = (v) => chartY + chartH - ((v - axisMin) / span) * chartH

  for (const ds of datasets) {
    const pts = ds.data.map((v, i) => (v == null ? null : [xAt(i), yAt(v)])).filter(Boolean)
    if (pts.length) {
      const d = pts.map((p, idx) => `${idx === 0 ? 'M' : 'L'}${p[0].toFixed(1)},${p[1].toFixed(1)}`).join(' ')
      const dash = ds.borderDash ? ` stroke-dasharray="${ds.borderDash.join(',')}"` : ''
      parts.push(`<path d="${d}" fill="none" stroke="${ds.borderColor}" stroke-width="2"${dash} />`)
    }
    const pr = ds.pointRadius === undefined ? 3 : ds.pointRadius
    if (typeof pr === 'number' && pr > 0) {
      ds.data.forEach((v, i) => {
        if (v == null) return
        parts.push(`<circle cx="${xAt(i).toFixed(1)}" cy="${yAt(v).toFixed(1)}" r="${pr}" fill="${ds.borderColor}" />`)
      })
    }
  }

  const skip = Math.max(1, Math.ceil(n / 10))
  labels.forEach((lab, i) => {
    if (i % skip !== 0 && i !== n - 1) return
    parts.push(
      `<text x="${xAt(i).toFixed(1)}" y="${chartY + chartH + 16}" font-size="9" fill="${COLORS.muted}" text-anchor="middle" font-family="sans-serif">${esc(lab)}</text>`
    )
  })
  parts.push(`<line x1="${chartX}" y1="${chartY + chartH}" x2="${chartX + chartW}" y2="${chartY + chartH}" stroke="${COLORS.border}" stroke-width="1.5" />`)
}

function renderRhythmChart(parts, data, chartX, chartY, chartW, chartH) {
  const labels = data.labels
  const n = labels.length
  const rowH = chartH / n
  const xAt = (hour) => chartX + (hour / 24) * chartW

  for (let h = 0; h <= 24; h += 6) {
    const x = xAt(h)
    parts.push(`<line x1="${x}" y1="${chartY}" x2="${x}" y2="${chartY + chartH}" stroke="${COLORS.grid}" stroke-width="1" />`)
    parts.push(
      `<text x="${x}" y="${chartY + chartH + 16}" font-size="9" fill="${COLORS.muted}" text-anchor="middle" font-family="sans-serif">${formatHourLabel(h)}</text>`
    )
  }

  labels.forEach((lab, i) => {
    const rowY = chartY + i * rowH
    parts.push(
      `<text x="${chartX - 8}" y="${rowY + rowH / 2 + 4}" font-size="10" fill="${COLORS.muted}" text-anchor="end" font-family="sans-serif">${esc(lab)}</text>`
    )
  })

  const barDs = data.datasets.find((d) => d.type === 'bar' || !d.type)
  const pointDs = data.datasets.find((d) => d.type === 'line')

  if (barDs) {
    for (const seg of barDs.data) {
      const rowIdx = labels.indexOf(seg.y)
      if (rowIdx === -1) continue
      const rowY = chartY + rowIdx * rowH
      const [s, e] = seg.x
      const x1 = xAt(s)
      const x2 = xAt(e)
      const barH = Math.min(rowH * 0.5, 16)
      parts.push(
        `<rect x="${x1.toFixed(1)}" y="${(rowY + rowH / 2 - barH / 2).toFixed(1)}" width="${Math.max(x2 - x1, 2).toFixed(1)}" height="${barH}" fill="${barDs.backgroundColor}" rx="3" />`
      )
    }
  }
  if (pointDs) {
    for (const pt of pointDs.data) {
      const rowIdx = labels.indexOf(pt.y)
      if (rowIdx === -1) continue
      const rowY = chartY + rowIdx * rowH
      const x = xAt(pt.x)
      parts.push(`<circle cx="${x.toFixed(1)}" cy="${(rowY + rowH / 2).toFixed(1)}" r="4" fill="${pointDs.pointBackgroundColor}" />`)
    }
  }
  parts.push(`<line x1="${chartX}" y1="${chartY + chartH}" x2="${chartX + chartW}" y2="${chartY + chartH}" stroke="${COLORS.border}" stroke-width="1" />`)
}

function renderComboChart(parts, data, chartX, chartY, chartW, chartH) {
  const labels = data.labels
  const n = labels.length
  const barDs = data.datasets.find((d) => d.type === 'bar')
  const lineDs = data.datasets.find((d) => d.type === 'line')

  let leftMax = 0
  for (const v of barDs.data) leftMax = Math.max(leftMax, Number(v) || 0)
  leftMax = niceMax(leftMax || 1)

  let rightMax = -Infinity
  let rightMin = Infinity
  for (const v of lineDs.data) {
    if (v == null) continue
    rightMax = Math.max(rightMax, v)
    rightMin = Math.min(rightMin, v)
  }
  if (!isFinite(rightMax)) {
    rightMax = 1
    rightMin = 0
  }
  const rightSpan = rightMax - rightMin || 1
  const rightAxisMin = Math.max(0, rightMin - rightSpan * 0.15)
  const rightAxisMax = rightMax + rightSpan * 0.15

  const gridLines = 4
  for (let g = 0; g <= gridLines; g++) {
    const val = (leftMax / gridLines) * g
    const y = chartY + chartH - (val / leftMax) * chartH
    parts.push(`<line x1="${chartX}" y1="${y}" x2="${chartX + chartW}" y2="${y}" stroke="${COLORS.grid}" stroke-width="1" />`)
    parts.push(`<text x="${chartX - 8}" y="${y + 4}" font-size="10" fill="${COLORS.muted}" text-anchor="end" font-family="sans-serif">${Math.round(val)}</text>`)
  }
  for (let g = 0; g <= gridLines; g++) {
    const val = rightAxisMin + ((rightAxisMax - rightAxisMin) / gridLines) * g
    const y = chartY + chartH - (g / gridLines) * chartH
    parts.push(
      `<text x="${chartX + chartW + 8}" y="${y + 4}" font-size="10" fill="${COLORS.muted}" text-anchor="start" font-family="sans-serif">${val.toFixed(1)}</text>`
    )
  }

  const slotW = chartW / n
  const barW = slotW * 0.5
  for (let i = 0; i < n; i++) {
    const v = Number(barDs.data[i]) || 0
    const h = (v / leftMax) * chartH
    const x = chartX + i * slotW + (slotW - barW) / 2
    const y = chartY + chartH - h
    parts.push(`<rect x="${x.toFixed(1)}" y="${y.toFixed(1)}" width="${barW.toFixed(1)}" height="${Math.max(h, 0).toFixed(1)}" fill="${barDs.backgroundColor}" rx="2" />`)
  }

  const xAt = (i) => chartX + i * slotW + slotW / 2
  const yAtRight = (v) => chartY + chartH - ((v - rightAxisMin) / (rightAxisMax - rightAxisMin)) * chartH
  const pts = []
  lineDs.data.forEach((v, i) => {
    if (v != null) pts.push([xAt(i), yAtRight(v)])
  })
  if (pts.length) {
    const d = pts.map((p, idx) => `${idx === 0 ? 'M' : 'L'}${p[0].toFixed(1)},${p[1].toFixed(1)}`).join(' ')
    parts.push(`<path d="${d}" fill="none" stroke="${lineDs.borderColor}" stroke-width="2" />`)
  }
  lineDs.data.forEach((v, i) => {
    if (v == null) return
    parts.push(`<circle cx="${xAt(i).toFixed(1)}" cy="${yAtRight(v).toFixed(1)}" r="4" fill="${lineDs.borderColor}" />`)
  })

  const skip = Math.max(1, Math.ceil(n / 12))
  labels.forEach((lab, i) => {
    if (i % skip !== 0 && i !== n - 1) return
    parts.push(
      `<text x="${xAt(i).toFixed(1)}" y="${chartY + chartH + 16}" font-size="9" fill="${COLORS.muted}" text-anchor="middle" font-family="sans-serif">${esc(lab)}</text>`
    )
  })
  parts.push(`<line x1="${chartX}" y1="${chartY + chartH}" x2="${chartX + chartW}" y2="${chartY + chartH}" stroke="${COLORS.border}" stroke-width="1.5" />`)
}

/**
 * Builds a standalone SVG document for one chart. `kind` selects the layout:
 * 'bar' | 'bar-stacked' | 'line' | 'rhythm' | 'combo'. `data` is the exact
 * same {labels, datasets} object passed to the on-screen Chart.js component.
 */
export function buildChartSvg({ title, description, filterNote, data, kind }) {
  const width = 760
  const headerParts = []
  let y = renderHeader(headerParts, { title, description, filterNote })

  const legendItems = (data.datasets || []).filter((d) => d.label)
  if (legendItems.length) y = renderLegend(headerParts, legendItems, 24, y)

  const chartX = 56
  const chartTopPad = y + 10
  const bottomPad = 40
  const rightPad = kind === 'combo' ? 56 : 24
  const chartW = width - chartX - rightPad
  const chartH = kind === 'rhythm' ? Math.max(120, (data.labels?.length || 1) * 34) : 260
  const totalHeight = chartTopPad + chartH + bottomPad

  const bodyParts = []
  if (kind === 'bar') renderBarChart(bodyParts, data, { stacked: false }, chartX, chartTopPad, chartW, chartH)
  else if (kind === 'bar-stacked') renderBarChart(bodyParts, data, { stacked: true }, chartX, chartTopPad, chartW, chartH)
  else if (kind === 'line') renderLineChart(bodyParts, data, chartX, chartTopPad, chartW, chartH)
  else if (kind === 'rhythm') renderRhythmChart(bodyParts, data, chartX, chartTopPad, chartW, chartH)
  else if (kind === 'combo') renderComboChart(bodyParts, data, chartX, chartTopPad, chartW, chartH)

  const exportedAt = new Date().toLocaleString([], { dateStyle: 'medium', timeStyle: 'short' })
  const footer = `<text x="${width - 24}" y="${totalHeight - 10}" font-size="9" fill="${COLORS.muted}" text-anchor="end" font-family="sans-serif">Exported from Baby Monitor · ${esc(exportedAt)}</text>`

  return `<svg xmlns="http://www.w3.org/2000/svg" width="${width}" height="${totalHeight}" viewBox="0 0 ${width} ${totalHeight}">
<rect x="0" y="0" width="${width}" height="${totalHeight}" fill="${COLORS.bg}" />
${headerParts.join('\n')}
${bodyParts.join('\n')}
${footer}
</svg>`
}

export function downloadSvg(svgString, filename) {
  const blob = new Blob([svgString], { type: 'image/svg+xml' })
  const url = URL.createObjectURL(blob)
  const a = document.createElement('a')
  a.href = url
  a.download = filename
  document.body.appendChild(a)
  a.click()
  a.remove()
  URL.revokeObjectURL(url)
}
