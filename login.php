<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=0, minimal-ui">
  <title>Login | Academic Grading System</title>

  <!-- Bootstrap CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

  <!-- Icons -->
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">

  <!-- Google Fonts -->
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">

  <!-- Styles -->
  <style>
    :root {
      --bg1: #0ea5e9;
      /* sky-500 */
      --bg2: #6366f1;
      /* indigo-500 */
      --bg3: #a855f7;
      /* purple-500 */
      --glass: #0b1220;
      --glass-2: #0f172a;
      --text: #0f172a;
      --muted: #6b7280;
      --focus: #7c3aed;
      --card-blur: 18px;
      --radius-xl: 26px;
      --radius-lg: 18px;
      --shadow-1: 0 20px 60px rgba(0, 0, 0, .25);
      --shadow-2: 0 10px 30px rgba(0, 0, 0, .18);
      --ring: 0 0 0 6px rgba(124, 58, 237, .12);
      --grad: linear-gradient(135deg, #2563eb 0%, #7c3aed 50%, #db2777 100%);
      --btn-grad: linear-gradient(135deg, #2563eb, #7c3aed);
      --btn-grad-hover: linear-gradient(135deg, #1d4ed8, #6d28d9);
    }

    * {
      font-family: 'Poppins', system-ui, -apple-system, Segoe UI, Roboto, sans-serif;
    }

    body {
      min-height: 100vh;
      margin: 0;
      color: #0f172a;
      background:
        radial-gradient(1000px 500px at -10% -10%, rgba(14, 165, 233, .15), transparent 60%),
        radial-gradient(900px 600px at 110% 0%, rgba(99, 102, 241, .18), transparent 60%),
        radial-gradient(800px 600px at 100% 110%, rgba(168, 85, 247, .20), transparent 60%),
        linear-gradient(180deg, #f8fafc 0%, #eef2ff 100%);
      position: relative;
      overflow: hidden;
    }

    /* Animated orbs */
    .orb {
      position: absolute;
      border-radius: 50%;
      filter: blur(40px);
      opacity: .65;
      mix-blend-mode: multiply;
      pointer-events: none;
      animation: float 16s ease-in-out infinite;
    }

    .orb.one {
      width: 340px;
      height: 340px;
      background: #60a5fa;
      top: -80px;
      left: -80px;
      animation-delay: 0s;
    }

    .orb.two {
      width: 420px;
      height: 420px;
      background: #a78bfa;
      bottom: -120px;
      right: -60px;
      animation-delay: 2s;
    }

    .orb.three {
      width: 280px;
      height: 280px;
      background: #fb7185;
      bottom: 10%;
      left: -100px;
      animation-delay: 4s;
    }

    @keyframes float {

      0%,
      100% {
        transform: translateY(0) translateX(0) scale(1);
      }

      50% {
        transform: translateY(-20px) translateX(10px) scale(1.03);
      }
    }

    .auth-wrap {
      min-height: 100vh;
      display: grid;
      place-items: center;
      padding: 40px 16px;
      position: relative;
      z-index: 1;
    }

    .auth-card {
      width: 100%;
      max-width: 980px;
      background: rgba(255, 255, 255, 0.42);
      backdrop-filter: blur(var(--card-blur));
      border-radius: var(--radius-xl);
      box-shadow: var(--shadow-1);
      border: 1px solid rgba(255, 255, 255, .35);
      overflow: hidden;
      display: grid;
      grid-template-columns: 1.1fr 1fr;
    }

    /* Left showcase */
    .auth-showcase {
      position: relative;
      background: radial-gradient(120% 120% at 0% 0%, rgba(37, 99, 235, .18), transparent 60%),
        radial-gradient(100% 120% at 100% 100%, rgba(124, 58, 237, .22), transparent 60%),
        var(--grad);
      color: #fff;
      padding: 48px 40px;
      display: flex;
      flex-direction: column;
      justify-content: space-between;
      isolation: isolate;
    }

    .brand {
      display: flex;
      align-items: center;
      gap: 14px;
    }

    .brand-badge {
      width: 48px;
      height: 48px;
      border-radius: 14px;
      background: rgba(255, 255, 255, .2);
      display: grid;
      place-items: center;
      border: 1px solid rgba(255, 255, 255, .35);
      box-shadow: inset 0 0 0 1px rgba(255, 255, 255, .2), 0 8px 20px rgba(0, 0, 0, .18);
    }

    .brand h4 {
      margin: 0;
      font-weight: 700;
      letter-spacing: .3px;
    }

    .hero-title {
      font-size: 30px;
      font-weight: 800;
      line-height: 1.15;
      margin: 18px 0 8px;
    }

    .hero-sub {
      color: rgba(255, 255, 255, .92);
      font-weight: 400;
    }

    .check-list {
      margin-top: 22px;
      display: grid;
      gap: 10px;
    }

    .check-item {
      display: flex;
      align-items: center;
      gap: 10px;
      color: #f8fafc;
    }

    .check-item i {
      color: #bbf7d0;
    }

    .showcase-footer {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-top: 24px;
      gap: 12px;
      color: rgba(255, 255, 255, .9);
    }

    .badge-soft {
      padding: 6px 10px;
      border-radius: 999px;
      font-weight: 700;
      font-size: 12px;
      background: rgba(255, 255, 255, .18);
      border: 1px solid rgba(255, 255, 255, .35);
    }

    /* Right form */
    .auth-form {
      background: rgba(255, 255, 255, .86);
      padding: 40px 34px;
      display: flex;
      flex-direction: column;
      justify-content: center;
    }

    .auth-form h3 {
      font-weight: 800;
      color: #0f172a;
      margin-bottom: 6px;
    }

    .auth-form .muted {
      color: #6b7280;
      margin-bottom: 18px;
    }

    .form-floating label {
      color: #6b7280;
    }

    .input-icon {
      position: relative;
    }

    .input-icon .fa-solid {
      position: absolute;
      left: 12px;
      top: 50%;
      transform: translateY(-50%);
      color: #64748b;
      opacity: .9;
      font-size: 14px;
    }

    .input-icon input {
      padding-left: 38px !important;
      height: 48px;
      border-radius: 12px;
      border: 2px solid #e5e7eb;
      transition: .2s ease;
    }

    .input-icon input:focus {
      border-color: #7c3aed;
      box-shadow: var(--ring);
      outline: none;
    }

    .toggle-pass {
      position: absolute;
      right: 12px;
      top: 50%;
      transform: translateY(-50%);
      color: #64748b;
      background: transparent;
      border: none;
      padding: 0 6px;
      cursor: pointer;
    }

    .btn-grad {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 10px;
      background: var(--btn-grad);
      color: #fff;
      border: none;
      border-radius: 12px;
      height: 48px;
      font-weight: 700;
      letter-spacing: .2px;
      box-shadow: 0 12px 24px rgba(124, 58, 237, .25);
      transition: transform .12s ease, box-shadow .2s ease, filter .2s ease;
    }

    .btn-grad:hover {
      background: var(--btn-grad-hover);
      transform: translateY(-1px);
      box-shadow: 0 14px 30px rgba(124, 58, 237, .35);
    }

    .btn-grad:active {
      transform: translateY(0);
      filter: brightness(.95);
    }

    .divider {
      display: flex;
      align-items: center;
      gap: 12px;
      color: #94a3b8;
      margin: 18px 0;
    }

    .divider::before,
    .divider::after {
      content: "";
      flex: 1;
      height: 1px;
      background: linear-gradient(90deg, transparent, #e5e7eb, transparent);
    }

    .link {
      color: #6366f1;
      text-decoration: none;
      font-weight: 600;
    }

    .link:hover {
      color: #4338ca;
      text-decoration: underline;
    }

    .foot-note {
      color: #94a3b8;
      font-size: 12px;
      margin-top: 14px;
    }

    .remember-wrap {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 8px;
      margin: 10px 0 14px;
    }

    .form-check-input {
      width: 18px;
      height: 18px;
      cursor: pointer;
    }

    /* Responsive */
    @media (max-width: 992px) {
      .auth-card {
        grid-template-columns: 1fr;
      }

      .auth-showcase {
        padding: 36px 28px;
      }

      .auth-form {
        padding: 28px 22px;
      }
    }

    /* Subtle watermark */
    .watermark {
      position: fixed;
      bottom: 14px;
      right: 16px;
      font-size: 12px;
      color: #94a3b8;
      z-index: 1;
      user-select: none;
    }
  </style>
</head>

<body>

  <!-- Animated background orbs -->
  <span class="orb one"></span>
  <span class="orb two"></span>
  <span class="orb three"></span>

  <div class="auth-wrap">
    <div class="auth-card">

      <!-- Showcase -->
      <aside class="auth-showcase">
        <div>
          <div class="brand">
            <div class="brand-badge">
              <i class="fa-solid fa-graduation-cap fa-lg text-white"></i>
            </div>
            <h4>Academic Grading System</h4>
          </div>

          <h1 class="hero-title">Welcome back</h1>
          <p class="hero-sub">Sign in to access your dashboard, register courses, and track results with precision analytics.</p>

          <div class="check-list">
            <div class="check-item"><i class="fa-solid fa-circle-check"></i> Secure student & lecturer access</div>
            <div class="check-item"><i class="fa-solid fa-circle-check"></i> Real-time grading & insights</div>
            <div class="check-item"><i class="fa-solid fa-circle-check"></i> Fast, modern UI with accessibility</div>
          </div>
        </div>

        <div class="showcase-footer">
          <span class="badge-soft">v1.0.0</span>
          <span>© 2025</span>
        </div>
      </aside>

      <!-- Form -->
      <section class="auth-form">
        <h3>Sign in</h3>
        <div class="muted">Use your institutional credentials to continue.</div>

        <!-- Add action and method as needed -->
        <form id="loginForm" class="needs-validation" method="post" action="authenticate.php" novalidate>
          <!-- CSRF protection token placeholder -->
          <!-- <input type="hidden" name="csrf_token" value="<?php // echo $_SESSION['csrf_token'] ?? '' 
                                                              ?>"> -->

          <div class="mb-3 input-icon">
            <i class="fa-solid fa-user"></i>
            <input type="text" class="form-control" id="username" name="username" placeholder="Username or Matric No" required>
            <div class="invalid-feedback">Please enter your username.</div>
          </div>

          <div class="mb-2 input-icon">
            <i class="fa-solid fa-lock"></i>
            <input type="password" class="form-control" id="password" name="password" placeholder="Password" required minlength="6">
            <button type="button" class="toggle-pass" aria-label="Toggle password visibility" onclick="togglePassword()">
              <i id="eye" class="fa-solid fa-eye"></i>
            </button>
            <div class="invalid-feedback">Please enter a valid password.</div>
          </div>

          <div class="remember-wrap">
            <div class="form-check">
              <input class="form-check-input" type="checkbox" value="1" id="remember" name="remember">
              <label class="form-check-label" for="remember">Remember me</label>
            </div>
            <a href="#" class="link">Forgot password?</a>
          </div>

          <button id="submitBtn" type="submit" class="btn-grad w-100">
            <span class="btn-label">Login</span>
            <i class="fa-solid fa-arrow-right"></i>
          </button>
          <div class="divider"><span>Dont have an account?</span></div>
          <div class="text-center">
            <a href="./register.php" class="link">Sign up instead</a>
          </div>

          <div class="divider"><span>Secure & private</span></div>
          <div class="foot-note">By continuing, you agree to our Terms and acknowledge our Privacy Policy.</div>
        </form>
      </section>
    </div>
  </div>

  <div class="watermark">Academic Grading System</div>

  <!-- Bootstrap JS -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

  <script>
    // Password visibility toggle
    function togglePassword() {
      const input = document.getElementById('password');
      const icon = document.getElementById('eye');
      const isText = input.type === 'text';
      input.type = isText ? 'password' : 'text';
      icon.classList.toggle('fa-eye');
      icon.classList.toggle('fa-eye-slash');
    }

    // Client-side validation + loading state
    (function() {
      const form = document.getElementById('loginForm');
      const btn = document.getElementById('submitBtn');
      const label = btn.querySelector('.btn-label');

      form.addEventListener('submit', function(e) {
        if (!form.checkValidity()) {
          e.preventDefault();
          e.stopPropagation();
        } else {
          // Optional loading UX
          btn.disabled = true;
          label.textContent = 'Signing in...';
          btn.insertAdjacentHTML('beforeend', '<span class="spinner-border spinner-border-sm ms-2" role="status" aria-hidden="true"></span>');
        }
        form.classList.add('was-validated');
      }, false);
    })();
  </script>
</body>

</html>