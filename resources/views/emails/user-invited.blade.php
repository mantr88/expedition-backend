<p>Привіт, {{ $user->name }}!</p>

<p>Вас запрошено до корпоративного месенджера.</p>

<p>
    <a href="{{ config('app.frontend_url') }}/invite/accept?token={{ $token }}&email={{ urlencode($user->email) }}">
        Прийняти запрошення та встановити пароль
    </a>
</p>
