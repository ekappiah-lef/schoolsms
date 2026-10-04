<!doctype html>
<html>
<body style="margin:0;padding:24px;background:#f4f5fb;font-family:Arial,Helvetica,sans-serif;color:#131b2e;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;margin:0 auto;background:#ffffff;border-radius:8px;">
    <tr><td style="padding:28px 32px 8px;">
        <div style="font-size:12px;letter-spacing:2px;text-transform:uppercase;color:#4f46e5;font-weight:bold;">{{ $school }}</div>
        <h1 style="font-size:20px;margin:12px 0 0;">Fees for {{ $student }}</h1>
    </td></tr>
    <tr><td style="padding:12px 32px;font-size:14px;line-height:22px;color:#464555;">
        <p style="margin:0 0 16px;">Dear {{ $parentName ?: 'Parent/Guardian' }}, below is a summary of the fees for this term. Open the statement to see the full breakdown.</p>
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="font-size:14px;border:1px solid #eceaf4;border-radius:6px;">
            <tr><td style="padding:10px 14px;">School fees</td><td align="right" style="padding:10px 14px;">GH₵ {{ number_format($schoolTotals['balance']) }} due</td></tr>
            <tr><td style="padding:10px 14px;border-top:1px solid #eceaf4;">Optional services</td><td align="right" style="padding:10px 14px;border-top:1px solid #eceaf4;">GH₵ {{ number_format($optionalTotals['balance']) }} due</td></tr>
            <tr><td style="padding:10px 14px;border-top:1px solid #eceaf4;font-weight:bold;color:#131b2e;">Total outstanding</td><td align="right" style="padding:10px 14px;border-top:1px solid #eceaf4;font-weight:bold;color:#131b2e;">GH₵ {{ number_format($totals['balance']) }}</td></tr>
        </table>
        <p style="margin:20px 0;"><a href="{{ $url }}" style="display:inline-block;background:#4f46e5;color:#ffffff;text-decoration:none;padding:12px 20px;border-radius:6px;font-weight:bold;">View fees statement</a></p>
        @if($instructions)
            <p style="margin:0 0 6px;font-weight:bold;color:#131b2e;">How to pay</p>
            <p style="margin:0;white-space:pre-line;">{{ $instructions }}</p>
        @endif
    </td></tr>
    <tr><td style="padding:20px 32px 28px;font-size:12px;line-height:18px;color:#777587;border-top:1px solid #eceaf4;">
        All fees paid are non-refundable, except where the school states otherwise in writing.@if($phone) Questions? Call {{ $phone }}.@endif
    </td></tr>
</table>
</body>
</html>
