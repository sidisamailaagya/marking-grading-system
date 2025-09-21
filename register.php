<?php
session_start();

// Get any error/success messages
$error = $_SESSION['error'] ?? '';
$success = $_SESSION['success'] ?? '';
$formData = $_SESSION['form_data'] ?? [];

// Clear messages after displaying
unset($_SESSION['error'], $_SESSION['success'], $_SESSION['form_data']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=0, minimal-ui">
  <title>Register | Academic Grading System</title>

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">

  <style>
    :root{
      --bg1:#0ea5e9;
      --bg2:#6366f1;
      --bg3:#a855f7;
      --text:#0f172a;
      --muted:#6b7280;
      --focus:#7c3aed;
      --card-blur:18px;
      --radius-xl:26px;
      --radius-lg:18px;
      --shadow-1:0 20px 60px rgba(0,0,0,.25);
      --ring: 0 0 0 6px rgba(124,58,237,.12);
      --grad: linear-gradient(135deg, #2563eb 0%, #7c3aed 50%, #db2777 100%);
      --btn-grad: linear-gradient(135deg, #2563eb, #7c3aed);
      --btn-grad-hover: linear-gradient(135deg, #1d4ed8, #6d28d9);
      --ok:#10b981; --warn:#f59e0b; --bad:#ef4444;
    }

    * { font-family: 'Poppins', system-ui, -apple-system, Segoe UI, Roboto, sans-serif; }

    body{
      min-height: 100vh;
      margin: 0;
      color: var(--text);
      background:
        radial-gradient(1000px 500px at -10% -10%, rgba(14,165,233,.15), transparent 60%),
        radial-gradient(900px 600px at 110% 0%, rgba(99,102,241,.18), transparent 60%),
        radial-gradient(800px 600px at 100% 110%, rgba(168,85,247,.20), transparent 60%),
        linear-gradient(180deg, #f8fafc 0%, #eef2ff 100%);
      position: relative;
      overflow: hidden;
    }

    /* Animated orbs */
    .orb{
      position: absolute; border-radius: 50%;
      filter: blur(40px); opacity: .65; mix-blend-mode: multiply; pointer-events:none;
      animation: float 16s ease-in-out infinite;
    }
    .orb.one{ width: 340px; height: 340px; background: #60a5fa; top: -80px; left: -80px; animation-delay: 0s; }
    .orb.two{ width: 420px; height: 420px; background: #a78bfa; bottom: -120px; right: -60px; animation-delay: 2s; }
    .orb.three{ width: 280px; height: 280px; background: #fb7185; bottom: 10%; left: -100px; animation-delay: 4s; }
    @keyframes float{
      0%, 100% { transform: translateY(0) translateX(0) scale(1); }
      50% { transform: translateY(-20px) translateX(10px) scale(1.03); }
    }

    .auth-wrap{
      min-height: 100vh;
      display: grid;
      place-items: center;
      padding: 40px 16px;
      position: relative;
      z-index: 1;
    }

    .auth-card{
      width: 100%;
      max-width: 980px;
      background: rgba(255,255,255,0.42);
      backdrop-filter: blur(var(--card-blur));
      border-radius: var(--radius-xl);
      box-shadow: var(--shadow-1);
      border: 1px solid rgba(255,255,255,.35);
      overflow: hidden;
      display: grid;
      grid-template-columns: 1.1fr 1fr;
    }

    /* Left panel */
    .auth-left{
      background: var(--grad);
      padding: 60px 50px;
      display: flex;
      flex-direction: column;
      justify-content: center;
      position: relative;
      overflow: hidden;
    }

    .auth-left::before{
      content: '';
      position: absolute;
      inset: 0;
      background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="grid" width="10" height="10" patternUnits="userSpaceOnUse"><path d="M 10 0 L 0 0 0 10" fill="none" stroke="rgba(255,255,255,0.1)" stroke-width="0.5"/></pattern></defs><rect width="100" height="100" fill="url(%23grid)"/></svg>');
      opacity: 0.3;
    }

    .auth-left > *{ position: relative; z-index: 1; }

    .auth-left h1{
      font-size: 2.8rem;
      font-weight: 700;
      color: white;
      margin: 0 0 20px;
      line-height: 1.1;
    }

    .auth-left p{
      font-size: 1.1rem;
      color: rgba(255,255,255,0.9);
      margin: 0 0 40px;
      line-height: 1.6;
    }

    .feature-list{
      list-style: none;
      padding: 0;
      margin: 0;
    }

    .feature-list li{
      display: flex;
      align-items: center;
      color: rgba(255,255,255,0.95);
      margin-bottom: 16px;
      font-size: 1rem;
    }

    .feature-list li i{
      width: 24px;
      height: 24px;
      background: rgba(255,255,255,0.2);
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      margin-right: 16px;
      font-size: 12px;
    }

    /* Right panel */
    .auth-right{
      padding: 60px 50px;
      display: flex;
      flex-direction: column;
      justify-content: center;
    }

    .auth-header{
      text-align: center;
      margin-bottom: 40px;
    }

    .auth-header h2{
      font-size: 2rem;
      font-weight: 700;
      margin: 0 0 8px;
      color: var(--text);
    }

    .auth-header p{
      color: var(--muted);
      margin: 0;
      font-size: 1rem;
    }

    .form-group{
      margin-bottom: 24px;
    }

    .form-label{
      display: block;
      font-weight: 600;
      color: var(--text);
      margin-bottom: 8px;
      font-size: 0.95rem;
    }

    .form-control{
      width: 100%;
      padding: 16px 20px;
      border: 2px solid #e5e7eb;
      border-radius: var(--radius-lg);
      font-size: 1rem;
      transition: all 0.3s ease;
      background: rgba(255,255,255,0.8);
      backdrop-filter: blur(10px);
    }

    .form-control:focus{
      outline: none;
      border-color: var(--focus);
      box-shadow: var(--ring);
      background: rgba(255,255,255,0.95);
    }

    .input-group{
      position: relative;
    }

    .input-group .form-control{
      padding-right: 50px;
    }

    .input-group .toggle-password{
      position: absolute;
      right: 16px;
      top: 50%;
      transform: translateY(-50%);
      background: none;
      border: none;
      color: var(--muted);
      cursor: pointer;
      padding: 4px;
      border-radius: 4px;
      transition: color 0.2s ease;
    }

    .input-group .toggle-password:hover{
      color: var(--focus);
    }

    .btn-primary{
      width: 100%;
      padding: 16px;
      background: var(--btn-grad);
      border: none;
      border-radius: var(--radius-lg);
      color: white;
      font-weight: 600;
      font-size: 1.05rem;
      cursor: pointer;
      transition: all 0.3s ease;
      position: relative;
      overflow: hidden;
    }

    .btn-primary:hover{
      background: var(--btn-grad-hover);
      transform: translateY(-2px);
      box-shadow: 0 12px 40px rgba(124,58,237,0.4);
    }

    .btn-primary:active{
      transform: translateY(0);
    }

    .form-check{
      display: flex;
      align-items: flex-start;
      gap: 12px;
      margin: 24px 0;
    }

    .form-check-input{
      width: 20px;
      height: 20px;
      border: 2px solid #d1d5db;
      border-radius: 4px;
      background: rgba(255,255,255,0.8);
      cursor: pointer;
      flex-shrink: 0;
      margin-top: 2px;
    }

    .form-check-input:checked{
      background: var(--focus);
      border-color: var(--focus);
    }

    .form-check-label{
      font-size: 0.9rem;
      color: var(--muted);
      line-height: 1.5;
      cursor: pointer;
    }

    .form-check-label a{
      color: var(--focus);
      text-decoration: none;
      font-weight: 600;
    }

    .form-check-label a:hover{
      text-decoration: underline;
    }

    .auth-footer{
      text-align: center;
      margin-top: 32px;
      padding-top: 24px;
      border-top: 1px solid rgba(0,0,0,0.1);
    }

    .auth-footer p{
      color: var(--muted);
      margin: 0;
      font-size: 0.95rem;
    }

    .auth-footer a{
      color: var(--focus);
      text-decoration: none;
      font-weight: 600;
    }

    .auth-footer a:hover{
      text-decoration: underline;
    }

    /* Alert styles */
    .alert{
      padding: 16px 20px;
      border-radius: var(--radius-lg);
      margin-bottom: 24px;
      border: none;
      font-weight: 500;
    }

    .alert-danger{
      background: rgba(239,68,68,0.1);
      color: #dc2626;
      border-left: 4px solid #dc2626;
    }

    .alert-success{
      background: rgba(16,185,129,0.1);
      color: #059669;
      border-left: 4px solid #059669;
    }

    /* Password strength indicator */
    .password-strength{
      margin-top: 8px;
      font-size: 0.85rem;
    }

    .strength-bar{
      height: 4px;
      background: #e5e7eb;
      border-radius: 2px;
      margin: 8px 0 4px;
      overflow: hidden;
    }

    .strength-fill{
      height: 100%;
      transition: all 0.3s ease;
      border-radius: 2px;
    }

    .strength-weak .strength-fill{ width: 25%; background: var(--bad); }
    .strength-fair .strength-fill{ width: 50%; background: var(--warn); }
    .strength-good .strength-fill{ width: 75%; background: var(--ok); }
    .strength-strong .strength-fill{ width: 100%; background: var(--ok); }

    /* Responsive */
    @media (max-width: 768px){
      .auth-card{
        grid-template-columns: 1fr;
        max-width: 480px;
      }
      
      .auth-left{
        padding: 40px 30px;
        text-align: center;
      }
      
      .auth-left h1{
        font-size: 2.2rem;
      }
      
      .auth-right{
        padding: 40px 30px;
      }
    }

    @media (max-width: 480px){
      .auth-wrap{
        padding: 20px 12px;
      }
      
      .auth-left, .auth-right{
        padding: 30px 24px;
      }
      
      .auth-left h1{
        font-size: 1.8rem;
      }
    }
  </style>
</head>
<body>
  <!-- Animated background orbs -->
  <div class="orb one"></div>
  <div class="orb two"></div>
  <div class="orb three"></div>

  <div class="auth-wrap">
    <div class="auth-card">
      <!-- Left Panel -->
      <div class="auth-left">
        <h1>Join Our Academic Community</h1>
        <p>Activate your student account using your matric number to access grades, assignments, and academic resources.</p>
        
        <ul class="feature-list">
          <li>
            <i class="fas fa-chart-line"></i>
            Track your academic progress
          </li>
          <li>
            <i class="fas fa-book-open"></i>
            Access course materials
          </li>
          <li>
            <i class="fas fa-users"></i>
            Connect with classmates
          </li>
          <li>
            <i class="fas fa-graduation-cap"></i>
            View grades and transcripts
          </li>
        </ul>
      </div>

      <!-- Right Panel -->
      <div class="auth-right">
        <div class="auth-header">
          <h2>Activate Account</h2>
          <p>Enter your matric number and create your password</p>
        </div>

        <?php if ($error): ?>
          <div class="alert alert-danger">
            <i class="fas fa-exclamation-triangle me-2"></i>
            <?= $error ?>
          </div>
        <?php endif; ?>

        <?php if ($success): ?>
          <div class="alert alert-success">
            <i class="fas fa-check-circle me-2"></i>
            <?= $success ?>
          </div>
        <?php endif; ?>

        <form action="register_action.php" method="POST" id="registerForm">
          <div class="form-group">
            <label for="matric" class="form-label">Matric Number</label>
            <input 
              type="text" 
              class="form-control" 
              id="matric" 
              name="matric" 
              placeholder="Enter your matric number"
              value="<?= htmlspecialchars($formData['matric'] ?? '') ?>"
              required
            >
          </div>

          <div class="form-group">
            <label for="email" class="form-label">Email Address</label>
            <input 
              type="email" 
              class="form-control" 
              id="email" 
              name="email" 
              placeholder="Enter your email address"
              value="<?= htmlspecialchars($formData['email'] ?? '') ?>"
              required
            >
          </div>

          <div class="form-group">
            <label for="password" class="form-label">Password</label>
            <div class="input-group">
              <input 
                type="password" 
                class="form-control" 
                id="password" 
                name="password" 
                placeholder="Create a strong password"
                required
              >
              <button type="button" class="toggle-password" data-target="password">
                <i class="fas fa-eye"></i>
              </button>
            </div>
            <div class="password-strength" id="passwordStrength">
              <div class="strength-bar">
                <div class="strength-fill"></div>
              </div>
              <span class="strength-text">Password strength</span>
            </div>
          </div>

          <div class="form-group">
            <label for="confirm" class="form-label">Confirm Password</label>
            <div class="input-group">
              <input 
                type="password" 
                class="form-control" 
                id="confirm" 
                name="confirm" 
                placeholder="Confirm your password"
                required
              >
              <button type="button" class="toggle-password" data-target="confirm">
                <i class="fas fa-eye"></i>
              </button>
            </div>
          </div>

          <div class="form-check">
            <input type="checkbox" class="form-check-input" id="terms" name="terms" value="1" required>
            <label class="form-check-label" for="terms">
              I agree to the <a href="#" target="_blank">Terms of Service</a> and <a href="#" target="_blank">Privacy Policy</a>
            </label>
          </div>

          <button type="submit" class="btn-primary">
            <i class="fas fa-user-plus me-2"></i>
            Activate Account
          </button>
        </form>

        <div class="auth-footer">
          <p>Already have an account? <a href="login.php">Sign in here</a></p>
        </div>
      </div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
  
  <script>
    // Password visibility toggle
    document.querySelectorAll('.toggle-password').forEach(button => {
      button.addEventListener('click', function() {
        const targetId = this.getAttribute('data-target');
        const input = document.getElementById(targetId);
        const icon = this.querySelector('i');
        
        if (input.type === 'password') {
          input.type = 'text';
          icon.classList.remove('fa-eye');
          icon.classList.add('fa-eye-slash');
        } else {
          input.type = 'password';
          icon.classList.remove('fa-eye-slash');
          icon.classList.add('fa-eye');
        }
      });
    });

    // Password strength checker
    const passwordInput = document.getElementById('password');
    const strengthIndicator = document.getElementById('passwordStrength');
    const strengthBar = strengthIndicator.querySelector('.strength-fill');
    const strengthText = strengthIndicator.querySelector('.strength-text');

    passwordInput.addEventListener('input', function() {
      const password = this.value;
      const strength = calculatePasswordStrength(password);
      
      // Remove all strength classes
      strengthIndicator.className = 'password-strength';
      
      if (password.length === 0) {
        strengthText.textContent = 'Password strength';
        return;
      }
      
      if (strength.score <= 1) {
        strengthIndicator.classList.add('strength-weak');
        strengthText.textContent = 'Weak password';
      } else if (strength.score === 2) {
        strengthIndicator.classList.add('strength-fair');
        strengthText.textContent = 'Fair password';
      } else if (strength.score === 3) {
        strengthIndicator.classList.add('strength-good');
        strengthText.textContent = 'Good password';
      } else {
        strengthIndicator.classList.add('strength-strong');
        strengthText.textContent = 'Strong password';
      }
    });

    function calculatePasswordStrength(password) {
      let score = 0;
      const checks = {
        length: password.length >= 8,
        lowercase: /[a-z]/.test(password),
        uppercase: /[A-Z]/.test(password),
        numbers: /[0-9]/.test(password),
        symbols: /[^A-Za-z0-9\s]/.test(password)
      };
      
      // Length check
      if (checks.length) score++;
      
      // Character variety
      if (checks.lowercase) score++;
      if (checks.uppercase) score++;
      if (checks.numbers) score++;
      if (checks.symbols) score++;
      
      // Bonus for longer passwords
      if (password.length >= 12) score++;
      
      return { score: Math.min(score, 4), checks };
    }

    // Form validation
    document.getElementById('registerForm').addEventListener('submit', function(e) {
      const password = document.getElementById('password').value;
      const confirm = document.getElementById('confirm').value;
      const terms = document.getElementById('terms').checked;
      
      let errors = [];
      
      if (password.length < 8) {
        errors.push('Password must be at least 8 characters long');
      }
      
      if (!/[0-9]/.test(password)) {
        errors.push('Password must contain at least one number');
      }
      
      if (!/[^A-Za-z0-9\s]/.test(password)) {
        errors.push('Password must contain at least one symbol');
      }
      
      if (password !== confirm) {
        errors.push('Passwords do not match');
      }
      
      if (!terms) {
        errors.push('You must agree to the Terms and Privacy Policy');
      }
      
      if (errors.length > 0) {
        e.preventDefault();
        alert('Please fix the following errors:\n\n' + errors.join('\n'));
      }
    });

    // Auto-hide alerts after 5 seconds
    setTimeout(function() {
      const alerts = document.querySelectorAll('.alert');
      alerts.forEach(alert => {
        alert.style.transition = 'opacity 0.5s ease';
        alert.style.opacity = '0';
        setTimeout(() => alert.remove(), 500);
      });
    }, 5000);
  </script>
</body>
</html>