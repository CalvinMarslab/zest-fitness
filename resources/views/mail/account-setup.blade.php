@component('mail::message')
# Welcome to Zest Athletic, {{ $user->name }}!

Your account has been created. Please click the button below to set your password and activate your account.

This link is valid for **24 hours** and can only be used once.

@component('mail::button', ['url' => $resetUrl, 'color' => 'primary'])
Set Up My Account
@endcomponent

If you did not expect this email, you can safely ignore it — your account will remain inactive until you complete setup.

If the button doesn't work, copy and paste this link into your browser:

{{ $resetUrl }}

Thanks,<br>
{{ config('app.name') }}
@endcomponent
