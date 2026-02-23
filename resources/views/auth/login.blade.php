<!doctype html>
<html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Login SGA ON</title>
<style>body{font-family:Arial;background:#0f3d7a;display:grid;place-items:center;height:100vh;margin:0}.box{background:#fff;padding:24px;border-radius:10px;width:340px}input{width:100%;padding:10px;border:1px solid #ccc;border-radius:6px;margin-bottom:10px}.btn{width:100%;padding:10px;background:#0f3d7a;color:#fff;border:0;border-radius:6px}</style>
</head><body>
<div class="box">
    <h2>SGA ON English</h2>
    <p>Iniciar sesión</p>
    <form method="POST" action="{{ route('login.attempt') }}">@csrf
        <input type="email" name="email" placeholder="Email" required value="{{ old('email') }}">
        <input type="password" name="password" placeholder="Password" required>
        <button class="btn" type="submit">Entrar</button>
    </form>
</div>
</body></html>
