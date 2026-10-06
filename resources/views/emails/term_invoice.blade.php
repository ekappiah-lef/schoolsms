<!doctype html>
<html>
<body style="margin:0;padding:24px;background:#f4f5fb;font-family:Arial,Helvetica,sans-serif;color:#131b2e;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;margin:0 auto;background:#ffffff;border-radius:8px;">
    <tr><td style="padding:28px 32px 8px;">
        <div style="font-size:12px;letter-spacing:2px;text-transform:uppercase;color:#4f46e5;font-weight:bold;">{{ $school }}</div>
        <h1 style="font-size:20px;margin:12px 0 0;">Invoice for {{ $student }}</h1>
        <div style="font-size:14px;color:#777587;margin-top:4px;">{{ $invoice['label'] }}</div>
    </td></tr>
    <tr><td style="padding:12px 32px;font-size:14px;line-height:22px;color:#464555;">
        <p style="margin:0 0 16px;">Dear {{ $parentName ?: 'Parent/Guardian' }}, here are {{ $student }}'s fees for {{ $invoice['label'] }}{{ $invoice['forward']['total'] > 0 ? ', together with the balance brought forward from earlier terms' : '' }}.</p>
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="font-size:14px;border:1px solid #eceaf4;border-radius:6px;">
            @foreach($invoice['current']['lines'] as $l)
                <tr><td style="padding:9px 14px;{{ $loop->first ? '' : 'border-top:1px solid #eceaf4;' }}">{{ $l['label'] }}</td><td align="right" style="padding:9px 14px;{{ $loop->first ? '' : 'border-top:1px solid #eceaf4;' }}">GH₵ {{ number_format($l['amount']) }}</td></tr>
            @endforeach
            @if($invoice['current']['paid'] > 0)
                <tr><td style="padding:9px 14px;border-top:1px solid #eceaf4;">Already paid this term</td><td align="right" style="padding:9px 14px;border-top:1px solid #eceaf4;">− GH₵ {{ number_format($invoice['current']['paid']) }}</td></tr>
            @endif
            <tr><td style="padding:9px 14px;border-top:1px solid #eceaf4;font-weight:bold;">This term's balance</td><td align="right" style="padding:9px 14px;border-top:1px solid #eceaf4;font-weight:bold;">GH₵ {{ number_format($invoice['current']['balance']) }}</td></tr>
            @if($invoice['forward']['total'] > 0)
                @foreach($invoice['forward']['lines'] as $l)
                    <tr><td style="padding:9px 14px;border-top:1px solid #eceaf4;color:#b91c1c;">Brought forward: {{ $l['label'] }}</td><td align="right" style="padding:9px 14px;border-top:1px solid #eceaf4;color:#b91c1c;">GH₵ {{ number_format($l['balance']) }}</td></tr>
                @endforeach
            @endif
            <tr><td style="padding:12px 14px;border-top:2px solid #131b2e;font-weight:bold;color:#131b2e;font-size:16px;">Total due</td><td align="right" style="padding:12px 14px;border-top:2px solid #131b2e;font-weight:bold;color:#131b2e;font-size:16px;">GH₵ {{ number_format($invoice['total']) }}</td></tr>
        </table>
        <p style="margin:20px 0;"><a href="{{ $url }}" style="display:inline-block;background:#4f46e5;color:#ffffff;text-decoration:none;padding:12px 20px;border-radius:6px;font-weight:bold;">View full fees statement</a></p>
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
