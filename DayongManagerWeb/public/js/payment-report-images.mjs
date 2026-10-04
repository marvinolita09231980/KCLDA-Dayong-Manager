export function imagePages(report) {
    const pages = [];
    const batches = Math.max(1, Math.ceil(report.rows.length / 15));
    const indices = report.headers.map((_, index) => index);
        for (let batch = 0; batch < batches; batch++) {
            pages.push({
                indices, batch: batch + 1, batches,
                from: report.rows.length ? batch * 15 + 1 : 0,
                to: Math.min(report.rows.length, (batch + 1) * 15),
                rows: report.rows.slice(batch * 15, (batch + 1) * 15),
                totals: batch === batches - 1 ? report.totals : null,
            });
        }
    return pages;
}

function wrap(ctx, value, width) {
    const lines = [];
    let line = '';
    for (const character of String(value ?? '')) {
        if (character === '\n' || (line && ctx.measureText(line + character).width > width)) {
            lines.push(line.trimEnd());
            line = character === '\n' ? '' : character;
        } else line += character;
    }
    lines.push(line.trimEnd());
    return lines;
}

export function renderImage(report, page) {
    const canvas = document.createElement('canvas');
    canvas.width = Math.max(1700, page.indices.length * 240 + 110);
    const ctx = canvas.getContext('2d');
    const margin = 55;
    const width = canvas.width - 2 * margin;
    const cellWidth = width / page.indices.length;
    const font = '22px Arial, sans-serif';
    ctx.font = `bold ${font}`;
    const title = wrap(ctx, `KCLDA — ${report.title}`, width);
    ctx.font = font;
    const metadata = [
        `Generated ${report.generatedAt} • ${report.council || 'All councils'}`,
        `Member-list cycles: ${report.filterCycles}`,
        `Members ${page.from}–${page.to} of ${report.rows.length} • Image ${page.batch} of ${page.batches}`,
    ].flatMap(value => wrap(ctx, value, width));
    const tableRows = [
        { values: report.headers, heading: true },
        ...page.rows.map(values => ({ values })),
        ...(page.totals ? [{ values: ['Report grand total (PHP)', ...page.totals.slice(1)], total: true }] : []),
    ].map(row => {
        ctx.font = row.heading || row.total ? `bold ${font}` : font;
        const cells = page.indices.map(index => wrap(ctx, row.values[index], cellWidth - 28));
        return { ...row, cells, height: Math.max(64, 30 * Math.max(...cells.map(lines => lines.length)) + 28) };
    });
    const tableTop = margin + title.length * 38 + 20 + metadata.length * 30 + 28;
    canvas.height = Math.max(2600, tableTop + tableRows.reduce((sum, row) => sum + row.height, 0) + 140);
    ctx.fillStyle = '#ffffff';
    ctx.fillRect(0, 0, canvas.width, canvas.height);
    ctx.textBaseline = 'top';
    ctx.fillStyle = '#203750';
    ctx.font = `bold ${font}`;
    title.forEach((line, i) => ctx.fillText(line, margin, margin + i * 38));
    ctx.font = font;
    metadata.forEach((line, i) => ctx.fillText(line, margin, margin + title.length * 38 + 20 + i * 30));
    let y = tableTop;
    tableRows.forEach((row, rowIndex) => {
        ctx.fillStyle = row.heading || row.total ? '#e8eef5' : rowIndex % 2 ? '#ffffff' : '#f7f9fc';
        ctx.fillRect(margin, y, width, row.height);
        ctx.font = row.heading || row.total ? `bold ${font}` : font;
        row.cells.forEach((lines, column) => {
            const x = margin + column * cellWidth;
            ctx.strokeStyle = '#c8d3df';
            ctx.strokeRect(x, y, cellWidth, row.height);
            ctx.fillStyle = '#203750';
            ctx.textAlign = !row.heading && page.indices[column] >= 2 ? 'right' : 'left';
            lines.forEach((line, i) => ctx.fillText(line, ctx.textAlign === 'right' ? x + cellWidth - 14 : x + 14, y + 14 + i * 30));
        });
        y += row.height;
    });
    ctx.textAlign = 'left';
    ctx.font = font;
    ctx.fillStyle = '#526176';
    if (!page.rows.length) ctx.fillText('No members match the selected filters.', margin, y + 22);
    ctx.fillText('Amounts in PHP. Amount to be collected covers all selected cycles.', margin, canvas.height - 60);
    return canvas;
}

async function pngBlob(canvas) {
    return new Promise((resolve, reject) => canvas.toBlob(blob => blob ? resolve(blob) : reject(new Error('Image could not be created.')), 'image/png'));
}

function save(blob, filename) {
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = filename;
    document.body.append(link);
    link.click();
    link.remove();
    setTimeout(() => URL.revokeObjectURL(url), 60000);
}

if (typeof window !== 'undefined') {
    window.addEventListener('download-payment-report-images', async event => {
        const report = event.detail.report;
        const status = document.createElement('div');
        status.setAttribute('role', 'status');
        status.style.cssText = 'position:fixed;bottom:24px;right:24px;z-index:99999;background:#203750;color:white;padding:16px;border-radius:8px;max-width:380px';
        status.textContent = 'Preparing report images…';
        document.body.append(status);
        try {
            const pages = imagePages(report);
            const zip = pages.length > 1 ? new window.JSZip() : null;
            for (let i = 0; i < pages.length; i++) {
                status.textContent = `Preparing image ${i + 1} of ${pages.length}…`;
                const canvas = renderImage(report, pages[i]);
                const blob = await pngBlob(canvas);
                canvas.width = canvas.height = 1;
                if (zip) zip.file(`${report.filename}-${String(i + 1).padStart(3, '0')}.png`, await blob.arrayBuffer());
                else save(blob, `${report.filename}.png`);
            }
            if (zip) save(await zip.generateAsync({ type: 'blob', compression: 'STORE' }), `${report.filename}-images.zip`);
            status.textContent = 'Report images downloaded.';
        } catch (error) {
            status.setAttribute('role', 'alert');
            status.textContent = 'Could not create the report images. Please try downloading again.';
            console.error('Report image download failed', error);
        }
        setTimeout(() => status.remove(), 10000);
    });
}
