<x-mail-layout>
    <h1>Welcome to {{ $appName }}!</h1>
    <p>Thank you for registering with Carvach. Please use the verification code below to verify your email address and complete your registration.</p>
    <p>Your verification code: <strong>{{ $code }}</strong></p>
    <p>Enter this code on the verification page to activate your account.</p>
    <p>If you did not create an account with Carvach, please ignore this email.</p>
    <p>Thank you,<br>The {{ $appName }} Team</p>

<br/>
<center><a href="{{\Fleetbase\Support\Utils::consoleUrl('onboard/verify-email')}}?hello={{base64_encode($user->uuid)}}&code={{$code}}" style="color:white;background:#0a74d1;text-decoration:none; padding:5px 15px; border-radius:10px; font-weight:bolder; font-size:20px">Verify Email</a></center> <br/>
</table>


</x-mail-layout>
