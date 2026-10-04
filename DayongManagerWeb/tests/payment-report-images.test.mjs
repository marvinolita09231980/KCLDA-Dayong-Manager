import test from 'node:test';
import assert from 'node:assert/strict';
import { imagePages, renderImage } from '../public/js/payment-report-images.mjs';

const report = count => ({
    title: 'Unpaid Members Report', generatedAt: 'Sep 28, 2026', council: 'North', filterCycles: 'Third cycle',
    headers: ['Member', 'Council', 'Third cycle (PHP)', 'Amount to be collected (PHP)'],
    rows: Array.from({ length: count }, (_, i) => [`Member ${i + 1}`, 'North', '0.00', '100.00']),
    totals: ['Total', '', '0.00', `${count * 100}.00`],
});

test('15 members per image, no extra page at boundaries, totals only on last page', () => {
    for (const count of [0, 1, 15, 16, 30, 31]) {
        const input = report(count);
        const pages = imagePages(input);
        assert.equal(pages.length, Math.max(1, Math.ceil(count / 15)));
        assert.deepEqual(pages.flatMap(page => page.rows), input.rows);
        assert.ok(pages.every(page => page.rows.length <= 15));
        assert.equal(pages.filter(page => page.totals).length, 1);
        assert.deepEqual(pages.at(-1).totals, input.totals);
    }
});

test('wide reports include every cycle and each member exactly once', () => {
    const input = report(16);
    input.headers = ['Member', 'Council', 'A', 'B', 'C', 'D', 'Amount to be collected'];
    const pages = imagePages(input);
    assert.equal(pages.length, 2);
    assert.deepEqual(pages[0].indices, [0, 1, 2, 3, 4, 5, 6]);
    assert.deepEqual(pages[1].indices, pages[0].indices);
    assert.deepEqual(pages.map(page => page.rows.length), [15, 1]);
    assert.deepEqual(pages.flatMap(page => page.rows), input.rows);
});

test('each rendered image includes report header, column headings and its own member range', () => {
    const drawn = [];
    const context = {
        measureText: text => ({ width: text.length * 10 }),
        fillRect() {}, strokeRect() {}, fillText: text => drawn.push(text),
    };
    globalThis.document = { createElement: () => ({ getContext: () => context }) };
    const input = report(16);
    for (const page of imagePages(input)) {
        drawn.length = 0;
        const canvas = renderImage(input, page);
        assert.equal(canvas.width, 1700);
        assert.ok(canvas.height >= 2600);
        assert.ok(drawn.includes('KCLDA — Unpaid Members Report'));
        assert.ok(drawn.includes('Third cycle (PHP)'));
        assert.ok(drawn.includes('Member'));
        assert.ok(drawn.some(text => text.includes(`Members ${page.from}–${page.to} of 16`)));
        assert.equal(drawn.filter(text => /^Member \d+$/.test(text)).length, page.rows.length);
    }
    delete globalThis.document;
});
