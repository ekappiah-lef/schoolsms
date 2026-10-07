<!doctype html>
<html>
<body style="margin:0;padding:24px;background:#f4f5fb;font-family:Arial,Helvetica,sans-serif;color:#131b2e;">
@php($cell = 'padding:9px 12px;border-top:1px solid #eceaf4;')
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;margin:0 auto;background:#ffffff;border-radius:8px;">
    <tr><td style="padding:28px 32px 8px;">
        <div style="font-size:12px;letter-spacing:2px;text-transform:uppercase;color:#4f46e5;font-weight:bold;">{{ $school }}</div>
        <h1 style="font-size:20px;margin:12px 0 0;">Invoice for {{ $student }}</h1>
        <div style="font-size:14px;color:#777587;margin-top:4px;">{{ $invoice['label'] }}</div>
    </td></tr>
    <tr><td style="padding:12px 32px;font-size:14px;line-height:22px;color:#464555;">
        <p style="margin:0 0 16px;">Dear {{ $parentName ?: 'Parent/Guardian' }}, here is {{ $student }}'s invoice for {{ $invoice['label'] }}.</p>
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="font-size:14px;border:1px solid #eceaf4;border-radius:6px;">
            <tr style="background:#f7f7fc;font-size:12px;color:#777587;">
                <td style="padding:8px 12px;">Item</td><td align="right" style="padding:8px 12px;">Amount</td><td align="right" style="padding:8px 12px;">Paid</td><td align="right" style="padding:8px 12px;">Balance</td>
            </tr>
            @foreach($invoice['table'] as $i => $r)
                @if(!empty($r['forward']) && empty($invoice['table'][$i - 1]['forward'] ?? null))
                    <tr><td colspan="4" style="padding:7px 12px;border-top:1px solid #eceaf4;background:#fef2f2;color:#b91c1c;font-size:11px;font-weight:bold;letter-spacing:1px;">BALANCE BROUGHT FORWARD</td></tr>
                @endif
                <tr style="{{ !empty($r['forward']) ? 'color:#b91c1c;' : '' }}">
                    <td style="{{ $cell }}">{{ $r['label'] }}</td>
                    <td align="right" style="{{ $cell }}">{{ number_format($r['amount']) }}</td>
                    <td align="right" style="{{ $cell }}">{{ $r['paid'] ? number_format($r['paid']) : '' }}</td>
                    <td align="right" style="{{ $cell }}">{{ number_format($r['balance']) }}</td>
                </tr>
            @endforeach
            <tr><td colspan="3" style="padding:12px;border-top:2px solid #131b2e;font-weight:bold;color:#131b2e;font-size:16px;">Total due</td><td align="right" style="padding:12px;border-top:2px solid #131b2e;font-weight:bold;color:#131b2e;font-size:16px;white-space:nowrap;">GH₵ {{ number_format($invoice['total']) }}</td></tr>
        </table>
        <p style="margin:20px 0;"><a href="{{ $url }}" style="display:inline-block;background:#4f46e5;color:#ffffff;text-decoration:none;padding:12px 20px;border-radius:6px;font-weight:bold;">View and pay online</a></p>
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
