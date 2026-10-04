<!doctype html>
<html>
<body style="margin:0;padding:24px;background:#f4f5fb;font-family:Arial,Helvetica,sans-serif;color:#131b2e;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;margin:0 auto;background:#ffffff;border-radius:8px;">
    <tr><td style="padding:28px 32px 8px;">
        <div style="font-size:12px;letter-spacing:2px;text-transform:uppercase;color:#4f46e5;font-weight:bold;">{{ $school }}</div>
        <h1 style="font-size:20px;margin:12px 0 0;">Congratulations{{ $parentName ? ', '.$parentName : '' }}</h1>
    </td></tr>
    <tr><td style="padding:12px 32px 4px;font-size:14px;line-height:22px;color:#464555;">
        <p style="margin:0 0 12px;">We are pleased to inform you that <strong style="color:#131b2e;">{{ $student }}</strong> has been admitted to {{ $school }}{{ $class ? ' into '.$class : '' }}.</p>
        <p style="margin:0 0 12px;">
            @if($policyPath)
                The school policy is attached to this email. Please read it with your ward; by accepting admission you agree to follow it.
            @else
                Please contact the school office for a copy of the school policy.
            @endif
        </p>
        <p style="margin:0 0 12px;">A separate email contains a link to your ward's fees, including any optional services you selected.</p>
        <p style="margin:0;">We look forward to working with you.</p>
    </td></tr>
    <tr><td style="padding:20px 32px 28px;font-size:12px;line-height:18px;color:#777587;border-top:1px solid #eceaf4;">
        {{ $school }}@if($address) · {{ $address }}@endif<br>
        @if($phone)Tel: {{ $phone }}@endif @if($email) · {{ $email }}@endif
    </td></tr>
</table>
</body>
</html>
