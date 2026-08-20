<!DOCTYPE html>
<html lang="ar" dir="rtl">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>فاتورة {{ $invoice->invoice_number }}</title>
        <style>
            * { box-sizing: border-box; }
            body { margin: 0; background: #f8fafc; color: #0f172a; direction: rtl; font-family: Tahoma, Arial, sans-serif; }
            .page { width: 210mm; min-height: 297mm; margin: 0 auto; padding: 18mm; background: #fff; }
            .header { display: flex; justify-content: space-between; gap: 20px; border-bottom: 2px solid #f59e0b; padding-bottom: 16px; }
            h1, h2, p { margin: 0; } h1 { font-size: 25px; } h2 { font-size: 17px; color: #92400e; } .muted { color: #64748b; font-size: 13px; line-height: 1.8; }
            .meta { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; margin: 22px 0; } .meta div { border: 1px solid #e2e8f0; border-radius: 8px; padding: 10px; } .label { color: #64748b; font-size: 12px; display: block; } .value { font-weight: 700; margin-top: 4px; display: block; }
            table { width: 100%; border-collapse: collapse; margin-top: 18px; font-size: 13px; } th { background: #fef3c7; color: #78350f; } th, td { border: 1px solid #e2e8f0; padding: 10px; text-align: right; } .totals { width: 280px; margin-top: 18px; margin-right: auto; } .totals div { display: flex; justify-content: space-between; gap: 20px; padding: 8px 0; border-bottom: 1px solid #e2e8f0; } .totals .final { color: #78350f; font-size: 17px; font-weight: 700; } .notes { margin-top: 28px; border: 1px solid #e2e8f0; border-radius: 8px; padding: 12px; min-height: 65px; } .signatures { display: grid; grid-template-columns: repeat(2, 1fr); gap: 60px; margin-top: 64px; text-align: center; font-size: 13px; } .line { border-top: 1px solid #64748b; padding-top: 8px; }
            @media print { body { background: #fff; } .page { width: auto; min-height: auto; margin: 0; padding: 0; } .no-print { display: none; } }
        </style>
    </head>
    <body>
        <main class="page">
            <button class="no-print" type="button" onclick="window.print()" style="margin-bottom:16px;padding:9px 15px;border:0;border-radius:6px;background:#f59e0b;color:#451a03;font-weight:bold;cursor:pointer">طباعة</button>
            <header class="header"><div><h2>نظام إدارة مخزن السلامي</h2><h1 style="margin-top:6px">فاتورة بيع</h1></div><div><span class="label">رقم الفاتورة</span><strong style="font-family:monospace;font-size:17px">{{ $invoice->invoice_number }}</strong><p class="muted" style="margin-top:6px">{{ $invoice->invoice_date->format('Y-m-d') }}</p></div></header>
            <section class="meta"><div><span class="label">المحل / العميل</span><span class="value">{{ $invoice->customer->name }}</span></div><div><span class="label">حالة الفاتورة</span><span class="value">{{ $invoice->status->value==='confirmed'?'معتمدة':($invoice->status->value==='cancelled'?'ملغاة':'مسودة') }}</span></div><div><span class="label">نوع الدفع</span><span class="value">{{ $invoice->payment_type->value==='cash'?'نقدي':($invoice->payment_type->value==='credit'?'آجل':'جزئي') }}</span></div><div><span class="label">حالة السداد</span><span class="value">{{ $invoice->payment_status->value==='paid'?'مسددة':($invoice->payment_status->value==='partial'?'جزئية':'غير مسددة') }}</span></div></section>
            <table><thead><tr><th>#</th><th>الصنف</th><th>الوحدة</th><th>الكمية</th><th>سعر الوحدة</th><th>الإجمالي</th></tr></thead><tbody>@foreach($invoice->items as $item)<tr><td>{{ $loop->iteration }}</td><td>{{ $item->product_name }}</td><td>{{ $item->unit }}</td><td>{{ $item->quantity }}</td><td>{{ $item->unit_price }}</td><td>{{ $item->line_total }}</td></tr>@endforeach</tbody></table>
            <section class="totals"><div><span>المجموع الفرعي</span><strong>{{ $invoice->subtotal_amount }}</strong></div><div><span>الخصم</span><strong>{{ $invoice->discount_amount }}</strong></div><div class="final"><span>الإجمالي</span><strong>{{ $invoice->total_amount }}</strong></div><div><span>المدفوع</span><strong>{{ $invoice->paid_amount }}</strong></div><div><span>المتبقي</span><strong>{{ $invoice->remaining_amount }}</strong></div></section>
            <section class="notes"><span class="label">ملاحظات</span><p style="margin-top:8px;font-size:13px;white-space:pre-line">{{ $invoice->notes ?? '—' }}</p></section>
            <section class="signatures"><div class="line">توقيع المستلم</div><div class="line">توقيع مسؤول المخزن</div></section>
        </main>
    </body>
</html>
