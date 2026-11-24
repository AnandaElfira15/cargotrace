<!DOCTYPE html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"></head>
<body style="margin:0;padding:24px;background:#eef2fb;font-family:Arial,Helvetica,sans-serif;color:#0f1b3f;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;margin:0 auto;background:#ffffff;border-radius:12px;overflow:hidden;border:1px solid #d9e2f5;">
    <tr>
        <td style="background:#0f1b3f;padding:18px 24px;color:#ffffff;font-size:18px;font-weight:bold;">CargoTrace</td>
    </tr>
    <tr>
        <td style="padding:24px;">
            <h1 style="margin:0 0 12px;font-size:18px;color:#0f1b3f;">{{ $notification->title }}</h1>
            <p style="margin:0 0 20px;font-size:14px;line-height:1.6;color:#223470;">{{ $notification->message }}</p>
            <a href="{{ $url }}" style="display:inline-block;background:#182655;color:#ffffff;text-decoration:none;padding:10px 18px;border-radius:8px;font-size:14px;font-weight:bold;">Open in CargoTrace</a>
        </td>
    </tr>
    <tr>
        <td style="padding:14px 24px;border-top:1px solid #d9e2f5;font-size:12px;color:#5a77c2;">
            You receive this e-mail because notifications are enabled for your CargoTrace account.
        </td>
    </tr>
</table>
</body>
</html>
