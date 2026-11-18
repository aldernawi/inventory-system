<style>
    @page { size: A4; margin: 11mm; }
    body { color: #30241f; }
    .page { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; width: min(100%, 210mm); min-height: auto; }
    .page > * { grid-column: 1 / -1; min-width: 0; }
    .no-print { justify-self: end; width: fit-content; border: 0; border-radius: 6px; padding: 9px 15px; background: #66301b; color: white; font: inherit; cursor: pointer; }
    .header, .head { border-top: 6px solid #66301b; border-bottom: 1px solid #b58970; background: #fff8f0; padding: 16px; }
    .header h1 { font-size: 18px; }
    .meta, .grid, table { margin: 0; }
    thead { display: table-header-group; }
    tr, .totals, .notes, .sign, .signatures { break-inside: avoid; }
    .page > .totals, .page > .notes, .page > .box { grid-column: auto; width: auto; margin: 0 !important; }
    .totals::before { content: 'ملخص الفاتورة'; }
    .totals::before, .notes > .label, .page > .box > .label {
        display: block; background: #66301b; color: white; font-weight: bold; padding: 7px 10px;
    }
    .notes, .page > .box { padding: 0; background: #fff8f0; }
    .notes > p { padding: 0 10px 10px; }
    .page > .box { padding-bottom: 10px; text-indent: 10px; }
    .page > .box > .label { text-indent: 0; }
    .sign, .signatures { margin-top: 10px; }
    .sign > div::after, .signatures > div::after {
        content: ''; display: block; border-bottom: 1px dashed #b58970; margin: 20px 12px 0;
    }
    @media print {
        .page { width: auto; margin: 0; padding: 0; display: block; print-color-adjust: exact; -webkit-print-color-adjust: exact; }
        body { font-size: 10px; line-height: 1.3; }
        .no-print { display: none; }
        .page > :not(.no-print) { margin-bottom: 7px; }
        .header, .head { padding: 8px; }
        .header h2, .head h1 { font-size: 17px; }
        .header h1 { font-size: 14px; }
        .muted, .label { font-size: 10px; line-height: 1.3; }
        .meta, .grid { gap: 4px; }
        .meta div, .grid .box { padding: 4px 6px; }
        .value { margin-top: 2px; }
        table { font-size: 12px; }
        th, td { padding: 4px 5px; }
        .totals div { padding: 3px 8px; }
        .totals .final { font-size: 12px; }
        .sign, .signatures { margin-top: 8px; font-size: 10px; }
        .sign > div, .signatures > div { padding: 6px 4px; }
        .sign > div::after, .signatures > div::after { margin-top: 10px; }
        .page > .notes, .page > .box { margin-top: 10px !important; }
    }
</style>
