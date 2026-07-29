<h2>Verify your email</h2>

@if(session('message'))
    <p>{{ session('message') }}</p>
@endif

@if(session('status'))
    <p>{{ session('status') }}</p>
@endif

<p>
A verification email has been sent.
Please click the link in your inbox before continuing.
</p>

<form method="POST" action="{{ route('verification.send') }}">
    @csrf
    <button type="submit">
        Resend verification email
    </button>
</form>