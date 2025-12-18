<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $subject }}</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; padding: 20px; background-color: #f9f9f9;">
    <div style="max-width: 600px; margin: 0 auto; background-color: #ffffff; padding: 30px; border-radius: 8px;">
        <p>Dear {{ $recipientName }},</p>
        
        <p>{!! nl2br(e($messageContent)) !!}</p>
        
        <p>If you have any questions or concerns, please don't hesitate to reach out to us.</p>
        
        <p>Thank you for your understanding.</p>
        
        <p>
            Best regards,<br>
            <strong>JayReservices Team</strong>
        </p>
        
        <hr style="border: none; border-top: 1px solid #eee; margin: 30px 0 15px;">
        <p style="font-size: 12px; color: #888; text-align: center; margin: 0;">
            © {{ date('Y') }} JayRe Services. All rights reserved.
        </p>
    </div>
</body>
</html>
