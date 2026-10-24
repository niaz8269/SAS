@php($digits = preg_replace('/\D/', '', (string) $student->phone))
<div style="font-size:.875rem;line-height:1.7">
    <p><strong>{{ $student->name }}</strong></p>
    <ul style="list-style:none;padding:0;margin:.5rem 0">
        <li>Email: <a href="mailto:{{ $student->email }}" style="text-decoration:underline">{{ $student->email }}</a></li>
        @if ($student->phone)<li>Phone: <a href="tel:+{{ $digits }}" style="text-decoration:underline">{{ $student->phone }}</a>@if (strlen($digits) >= 8) &middot; <a href="https://wa.me/{{ $digits }}" target="_blank" rel="noopener noreferrer" style="text-decoration:underline">WhatsApp</a>@endif</li>@endif
        @if ($wechat)<li>WeChat ID: {{ $wechat }}</li>@endif
        <li>Time zone: {{ $student->timezone }}</li>
    </ul>
    <p style="color:#6b7280">Use the <strong>Messages</strong> tab to write to the student inside SAS; they are notified and reply there.</p>
</div>