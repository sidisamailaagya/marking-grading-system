<!DOCTYPE html>
  <html lang="en">
  <head>
    <meta charset="utf-8" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=0, minimal-ui">
    <title>Profile Settings | Student Portal</title>
    <meta name="description" content="View details and update email & password" />
    <link rel="icon" href="../Admin/assets/images/favicon.ico" type="image/x-icon">
    <link rel="stylesheet" href="../Admin/assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
      :root {
        --primary-color:#667eea; --primary-dark:#5a67d8; --secondary-color:#764ba2;
        --success-color:#48bb78; --warning-color:#ed8936; --danger-color:#f56565; --info-color:#4299e1;
        --light-bg:#f8fafc; --card-shadow:0 10px 25px rgba(0,0,0,0.1); --card-shadow-hover:0 20px 40px rgba(0,0,0,0.15);
        --radius:14px; --transition: all .25s ease;
      }
      body { font-family:'Inter',sans-serif; background: linear-gradient(135deg,#667eea 0%,#764ba2 100%); min-height:100vh; }
      .pcoded-navbar{ background:rgba(255,255,255,0.96); backdrop-filter:blur(10px); border-right:1px solid rgba(0,0,0,0.05); box-shadow:0 0 30px rgba(0,0,0,0.12); }
      .pcoded-header{ background:rgba(255,255,255,0.96); backdrop-filter:blur(10px); border-bottom:1px solid rgba(0,0,0,0.05); box-shadow:0 6px 20px rgba(0,0,0,0.06); }
      .page-hero{ background: linear-gradient(135deg, rgba(102,126,234,.92), rgba(118,75,162,.92)); color:#fff; padding: 2rem 0; border-radius: 0 0 26px 26px; margin-bottom: 1.5rem; position:relative; overflow:hidden; }
      .page-hero::before{ content:''; position:absolute; inset:0; opacity:.15; background: radial-gradient(600px 200px at 10% 10%, #fff, transparent), radial-gradient(600px 200px at 90% 80%, #fff, transparent); }
      .container{ padding: 0 1.25rem; }
      .main{ max-width:1400px; margin:0 auto; padding:1.25rem; }
      .grid{ display:grid; grid-template-columns: 1fr 1fr; gap:1.25rem; }
      .card{ background:#fff; border-radius:var(--radius); box-shadow:var(--card-shadow); padding:1.25rem; }
      .card:hover{ box-shadow:var(--card-shadow-hover); }
      .section-title{ font-weight:800; color:#2d3748; margin-bottom:.5rem; display:flex; align-items:center; gap:.5rem; }
      .form-grid{ display:grid; grid-template-columns: 1fr 1fr; gap:.75rem; }
      .input{ padding:.68rem .8rem; border:2px solid #e2e8f0; border-radius:10px; background:#fff; width:100%; }
      .input:focus{ outline:none; border-color:var(--primary-color); box-shadow:0 0 0 3px rgba(102,126,234,.12); }
      .input[readonly]{ background:#f8fafc; color:#475569; }
      .btn{ padding:.68rem 1rem; border:none; border-radius:10px; font-weight:700; display:inline-flex; align-items:center; gap:.5rem; cursor:pointer; transition:var(--transition); }
      .btn-primary{ background:linear-gradient(135deg,#667eea,#5a67d8); color:#fff; }
      .btn-outline{ background:#fff; color:#4a5568; border:1px solid #e2e8f0; }
      .muted{ color:#718096; font-size:.9rem; }
      @media (max-width:1100px){
        .grid{ grid-template-columns:1fr }
        .form-grid{ grid-template-columns:1fr }
      }
    </style>
  </head>
  <body>
    <!-- Sidebar -->
    <nav class="pcoded-navbar menu-light">
      <div class="navbar-wrapper">
        <div class="navbar-content scroll-div">
          <ul class="nav pcoded-inner-navbar">
            <li class="nav-item pcoded-menu-caption"><label>Student Menu</label></li>
            <li class="nav-item"><a href="dashboard.php" class="nav-link"><span class="pcoded-micon"><i class="fas fa-house"></i></span><span class="pcoded-mtext">Dashboard</span></a></li>
            <li class="nav-item"><a href="my-results.php" class="nav-link"><span class="pcoded-micon"><i class="fas fa-list-check"></i></span><span class="pcoded-mtext">My Results</span></a></li>
            <li class="nav-item"><a href="predicted-grade.php" class="nav-link"><span class="pcoded-micon"><i class="fas fa-crystal-ball"></i></span><span class="pcoded-mtext">Predicted Future Grade</span></a></li>
            <li class="nav-item"><a href="feedback-comments.php" class="nav-link"><span class="pcoded-micon"><i class="fas fa-comments"></i></span><span class="pcoded-mtext">Feedback & Comments</span></a></li>
            <li class="nav-item"><a href="performance-history.php" class="nav-link"><span class="pcoded-micon"><i class="fas fa-chart-line"></i></span><span class="pcoded-mtext">Performance History</span></a></li>
            <li class="nav-item"><a href="profile-settings.php" class="nav-link active"><span class="pcoded-micon"><i class="fas fa-user"></i></span><span class="pcoded-mtext">Profile Settings</span></a></li>
            <li class="nav-item"><a href="../Admin/logout.php" class="nav-link"><span class="pcoded-micon"><i class="fas fa-sign-out-alt"></i></span><span class="pcoded-mtext">Logout</span></a></li>
          </ul>
        </div>
      </div>
    </nav>

    <!-- Header -->
    <header class="navbar pcoded-header navbar-expand-lg navbar-light">
      <div class="m-header">
        <a class="mobile-menu" id="mobile-collapse" href="#"><span></span></a>
        <a href="#" class="b-brand"><h3 class="text-primary mb-0">Student Portal</h3></a>
        <a href="#" class="mob-toggler"><i class="feather icon-more-vertical"></i></a>
      </div>
    </header>

    <div class="pcoded-main-container">
      <div class="pcoded-content">
        <div class="page-hero">
          <div class="container">
            <h1>Profile Settings</h1>
            <p>View your details and update your email & password</p>
          </div>
        </div>

        <div class="main container">
          <div class="grid">
            <!-- Details (readonly) -->
            <div class="card">
              <div class="section-title"><i class="fas fa-id-card"></i> Details</div>
              <div class="form-grid">
                <input id="matricNo" class="input" placeholder="Matric No" readonly aria-readonly="true" />
                <input id="department" class="input" placeholder="Department" readonly aria-readonly="true" />
                <input id="faculty" class="input" placeholder="Faculty" readonly aria-readonly="true" />
              </div>
              <div class="muted" style="margin-top:.5rem;">These fields are managed by the school and cannot be edited.</div>
            </div>

            <!-- Update Email & Password -->
            <div class="card">
              <div class="section-title"><i class="fas fa-user-cog"></i> Update Email & Password</div>

              <div class="form-grid" style="margin-bottom:.5rem;">
                <input id="email" class="input" type="email" placeholder="Email" />
                <div></div>
              </div>
              <div style="display:flex; gap:.5rem; margin-bottom:1rem;">
                <button class="btn btn-primary" onclick="saveEmail()"><i class="fas fa-save"></i> Save Email</button>
                <button class="btn btn-outline" onclick="resetEmail()"><i class="fas fa-rotate"></i> Reset</button>
              </div>

              <div class="form-grid">
                <input id="currentPass" class="input" type="password" placeholder="Current Password" autocomplete="current-password" />
                <input id="newPass" class="input" type="password" placeholder="New Password" autocomplete="new-password" />
                <input id="confirmPass" class="input" type="password" placeholder="Confirm New Password" autocomplete="new-password" />
              </div>
              <div style="margin-top:.75rem;display:flex;gap:.5rem;">
                <button class="btn btn-primary" onclick="changePassword()"><i class="fas fa-key"></i> Update Password</button>
              </div>
              <div class="muted" style="margin-top:.5rem;">Use at least 8 characters, including a number and a symbol.</div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <script src="../Admin/assets/js/vendor-all.min.js"></script>
    <script src="../Admin/assets/js/plugins/bootstrap.min.js"></script>
    <script src="../Admin/assets/js/ripple.js"></script>
    <script src="../Admin/assets/js/pcoded.min.js"></script>
    <script>
      // TODO: Replace with server-rendered values from PHP session or API fetch
      // Example: echo htmlspecialchars($_SESSION['student']['matric_no'])
      const profile = {
        matricNo: 'CSE/2020/0123',
        department: 'Computer Science',
        faculty: 'Engineering',
        email: 'sarah.student@university.edu'
      };

      function loadProfile(){
        document.getElementById('matricNo').value = profile.matricNo;
        document.getElementById('department').value = profile.department;
        document.getElementById('faculty').value = profile.faculty;
        document.getElementById('email').value = profile.email;
      }

      function resetEmail(){
        document.getElementById('email').value = profile.email;
      }

      function saveEmail(){
        const emailEl = document.getElementById('email');
        const email = emailEl.value.trim();
        // Simple email format check
        const ok = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
        if (!ok){ alert('Please enter a valid email address.'); emailEl.focus(); return; }

        // In real app: POST /api/student/profile/email { email }
        // Then on success:
        profile.email = email;
        alert('Email updated successfully.');
      }

      function changePassword(){
        const cur = document.getElementById('currentPass').value.trim();
        const n = document.getElementById('newPass').value.trim();
        const c = document.getElementById('confirmPass').value.trim();

        if (!cur){ alert('Please enter your current password.'); return; }
        if (n.length < 8 || !/[0-9]/.test(n) || !/[^\w\s]/.test(n)){
          alert('New password must be at least 8 characters and include a number and a symbol.');
          return;
        }
        if (n !== c){ alert('Passwords do not match.'); return; }

        // In real app: POST /api/student/profile/password { currentPassword:cur, newPassword:n }
        // Clear fields on success
        alert('Password updated successfully.');
        document.getElementById('currentPass').value='';
        document.getElementById('newPass').value='';
        document.getElementById('confirmPass').value='';
      }

      window.addEventListener('load', loadProfile);
    </script>
  </body>
  </html>