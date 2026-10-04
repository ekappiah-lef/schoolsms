<!doctype html>
<html>
<body style="margin:0;padding:24px;background:#f4f5fb;font-family:Arial,Helvetica,sans-serif;color:#131b2e;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;margin:0 auto;background:#ffffff;border-radius:8px;">
    <tr><td style="padding:28px 32px 8px;">
        <div style="font-size:12px;letter-spacing:2px;text-transform:uppercase;color:#4f46e5;font-weight:bold;">{{ $r['school']['name'] }}</div>
        <h1 style="font-size:20px;margin:12px 0 0;">Payment received</h1>
    </td></tr>
    <tr><td style="padding:12px 32px;font-size:14px;line-height:22px;color:#464555;">
        <p style="margin:0 0 16px;">Thank you. We have received a payment for <strong style="color:#131b2e;">{{ $r['student']['name'] }}</strong> ({{ $r['student']['class'] }}).</p>
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="font-size:14px;border:1px solid #eceaf4;border-radius:6px;">
            <tr><td style="padding:10px 14px;">Receipt</td><td align="right" style="padding:10px 14px;">{{ $r['number'] }}</td></tr>
            <tr><td style="padding:10px 14px;border-top:1px solid #eceaf4;">Payment for</td><td align="right" style="padding:10px 14px;border-top:1px solid #eceaf4;">{{ $r['item'] }}</td></tr>
            <tr><td style="padding:10px 14px;border-top:1px solid #eceaf4;">Amount paid</td><td align="right" style="padding:10px 14px;border-top:1px solid #eceaf4;font-weight:bold;color:#006e4b;">GH₵ {{ number_format($r['amount']) }}</td></tr>
            <tr><td style="padding:10px 14px;border-top:1px solid #eceaf4;font-weight:bold;color:#131b2e;">Balance</td><td align="right" style="padding:10px 14px;border-top:1px solid #eceaf4;font-weight:bold;color:{{ $r['balance'] > 0 ? '#ba1a1a' : '#006e4b' }};">{{ $r['balance'] > 0 ? 'GH₵ '.number_format($r['balance']) : 'Fully paid' }}</td></tr>
        </table>
        <p style="margin:16px 0 0;">The receipt is attached as a PDF.</p>
    </td></tr>
    <tr><td style="padding:20px 32px 28px;font-size:12px;line-height:18px;color:#777587;border-top:1px solid #eceaf4;">
        {{ $r['school']['name'] }}@if($r['school']['phone']) · Tel: {{ $r['school']['phone'] }}@endif
    </td></tr>
</table>
</body>
</html>
