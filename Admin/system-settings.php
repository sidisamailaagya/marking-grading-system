<!DOCTYPE html>
<html lang="en">
<head>
  <title>System Settings - Marking & Grading System</title>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=0, minimal-ui">
  <meta http-equiv="X-UA-Compatible" content="IE=edge" />
  <meta name="description" content="Configure academic session and system preferences" />
  <meta name="keywords" content="system settings, academic session, backup, restore">
  <meta name="author" content="Marking & Grading System" />
  <link rel="icon" href="assets/images/favicon.ico" type="image/x-icon">
  <link rel="stylesheet" href="assets/css/style.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <style>
    :root{--primary-color:#667eea;--primary-dark:#5a67d8;--secondary-color:#764ba2;--success-color:#48bb78;--warning-color:#ed8936;--danger-color:#f56565;--info-color:#4299e1;--light-bg:#f8fafc;--card-shadow:0 10px 25px rgba(0,0,0,.1);--card-shadow-hover:0 20px 40px rgba(0,0,0,.15);--border-radius:12px;--transition:all .3s cubic-bezier(.4,0,.2,1)}*{box-sizing:border-box;margin:0;padding:0}body{font-family:'Inter',sans-serif;background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);min-height:100vh;overflow-x:hidden}
    .pcoded-navbar{background:rgba(255,255,255,.95);backdrop-filter:blur(10px);border-right:1px solid rgba(255,255,255,.2);box-shadow:0 0 30px rgba(0,0,0,.1)}.nav-link{transition:var(--transition);border-radius:8px;margin:2px 8px}.nav-link:hover{background:linear-gradient(135deg,var(--primary-color),var(--primary-dark));color:white !important;transform:translateX(5px)}.nav-link.active{background:linear-gradient(135deg,var(--primary-color),var(--primary-dark));color:white !important}
    .pcoded-header{background:rgba(255,255,255,.95);backdrop-filter:blur(10px);border-bottom:1px solid rgba(255,255,255,.2);box-shadow:0 2px 20px rgba(0,0,0,.1)}
    .page-header{background:linear-gradient(135deg,rgba(102,126,234,.9),rgba(118,75,162,.9));color:#fff;padding:2rem 0;margin-bottom:2rem;border-radius:0 0 30px 30px;position:relative;overflow:hidden}
    .page-header::before{content:'';position:absolute;inset:0;background:url('data:image/svg+xml,<svg xmlns=\"http://www.w3.org/2000/svg\" viewBox=\"0 0 100 100\"><defs><pattern id=\"grain\" width=\"100\" height=\"100\" patternUnits=\"userSpaceOnUse\"><circle cx=\"25\" cy=\"25\" r=\"1\" fill=\"white\" opacity=\"0.1\"/><circle cx=\"75\" cy=\"75\" r=\"1\" fill=\"white\" opacity=\"0.1\"/><circle cx=\"50\" cy=\"10\" r=\"1\" fill=\"white\" opacity=\"0.1\"/></pattern></defs><rect width=\"100\" height=\"100\" fill=\"url(%23grain)\"/></svg>');animation:float 20s ease-in-out infinite}
    @keyframes float{0%,100%{transform:translateY(0)}50%{transform:translateY(-10px)}}.page-content{text-align:center;position:relative;z-index:2}.page-title{font-size:2.5rem;font-weight:700;margin-bottom:.5rem}.page-subtitle{font-size:1.1rem;opacity:.9}
    .main{padding:2rem;max-width:1400px;margin:0 auto}
    .grid{display:grid;grid-template-columns:2fr 1fr;gap:1.5rem}
    .card{background:white;border-radius:var(--border-radius);box-shadow:var(--card-shadow);padding:1.25rem}
    .card:hover{box-shadow:var(--card-shadow-hover)}
    .card-title{font-size:1.25rem;font-weight:800;color:#2d3748;margin-bottom:1rem;display:flex;align-items:center;gap:.5rem}
    .input,.select{padding:.7rem;border:2px solid #e2e8f0;border-radius:8px;background:white;font-size:.9rem}
    .input:focus,.select:focus{outline:none;border-color:var(--primary-color);box-shadow:0 0 0 3px rgba(102,126,234,.12)}
    .btn{padding:.7rem 1rem;border:none;border-radius:10px;font-weight:700;display:inline-flex;align-items:center;gap:.5rem;cursor:pointer}
    .btn-primary{background:linear-gradient(135deg,var(--primary-color),var(--primary-dark));color:white}
    .btn-secondary{background:#e2e8f0;color:#334155}
    .btn-success{background:linear-gradient(135deg,var(--success-color),#38a169);color:white}
    .btn-danger{background:linear-gradient(135deg,var(--danger-color),#e53e3e);color:white}
    .help-text{color:#64748b;font-size:.85rem}
    @media (max-width:1100px){.grid{grid-template-columns:1fr}}
    @media (max-width:768px){.main{padding:1rem}}
  </style>
</head>
<body>
  <nav class="pcoded-navbar menu-light">
    <div class="navbar-wrapper"><div class="navbar-content scroll-div">
      <ul class="nav pcoded-inner-navbar">
        <li class="nav-item pcoded-menu-caption"><label>Navigation</label></li>
        <li class="nav-item"><a href="dashboard.php" class="nav-link"><span class="pcoded-micon"><i class="fas fa-home"></i></span><span class="pcoded-mtext">Dashboard</span></a></li>
        <li class="nav-item"><a href="manage-students.php" class="nav-link"><span class="pcoded-micon"><i class="fas fa-user-graduate"></i></span><span class="pcoded-mtext">Manage Students</span></a></li>
        <li class="nav-item"><a href="manage-lecturers.php" class="nav-link"><span class="pcoded-micon"><i class="fas fa-chalkboard-teacher"></i></span><span class="pcoded-mtext">Manage Lecturers</span></a></li>
        <li class="nav-item"><a href="manage-courses.php" class="nav-link"><span class="pcoded-micon"><i class="fas fa-book"></i></span><span class="pcoded-mtext">Manage Courses</span></a></li>
        <li class="nav-item"><a href="grading-scale.php" class="nav-link"><span class="pcoded-micon"><i class="fas fa-chart-line"></i></span><span class="pcoded-mtext">Grading Scale</span></a></li>
        <li class="nav-item"><a href="reports-analytics.php" class="nav-link"><span class="pcoded-micon"><i class="fas fa-chart-pie"></i></span><span class="pcoded-mtext">Reports & Analytics</span></a></li>
        <li class="nav-item"><a href="user-management.php" class="nav-link"><span class="pcoded-micon"><i class="fas fa-users-cog"></i></span><span class="pcoded-mtext">User Management</span></a></li>
        <li class="nav-item"><a href="system-settings.php" class="nav-link active"><span class="pcoded-micon"><i class="fas fa-cog"></i></span><span class="pcoded-mtext">System Settings</span></a></li>
        <li class="nav-item"><a href="logout.php" class="nav-link"><span class="pcoded-micon"><i class="fas fa-sign-out-alt"></i></span><span class="pcoded-mtext">Logout</span></a></li>
      </ul>
    </div></div>
  </nav>
  <header class="navbar pcoded-header navbar-expand-lg navbar-light">
    <div class="m-header">
      <a class="mobile-menu" id="mobile-collapse" href="#"><span></span></a>
      <a href="#" class="b-brand"><h3 class="text-primary mb-0">MGS Admin</h3></a>
      <a href="#" class="mob-toggler"><i class="feather icon-more-vertical"></i></a>
    </div>
  </header>

  <div class="pcoded-main-container">
    <div class="pcoded-content">
      <div class="page-header"><div class="container-fluid"><div class="page-content">
        <h1 class="page-title">System Settings</h1>
        <p class="page-subtitle">Manage academic session, backups and platform preferences</p>
      </div></div></div>

      <div class="main">
        <div class="grid">
          <div class="card">
            <div class="card-title"><i class="fas fa-calendar-alt"></i> Academic Session Settings</div>
            <div style="display:grid;grid-template-columns:repeat(2,1fr);gap:1rem;">
              <select id="session" class="select"><option>2025/2026</option><option selected>2024/2025</option><option>2023/2024</option></select>
              <select id="currentSemester" class="select"><option>First</option><option selected>Second</option><option>Summer</option></select>
              <input id="startDate" type="date" class="input" value="2025-01-10">
              <input id="endDate" type="date" class="input" value="2025-12-20">
            </div>
            <div style="display:flex;gap:.5rem;margin-top:1rem;">
              <button class="btn btn-primary" onclick="saveSession()"><i class="fas fa-save"></i> Save</button>
              <button class="btn btn-secondary" onclick="resetSession()"><i class="fas fa-rotate"></i> Reset</button>
            </div>
          </div>

          <div class="card">
            <div class="card-title"><i class="fas fa-database"></i> Backup & Restore</div>
            <div class="help-text" style="margin-bottom:.75rem;">Create system backups and restore from backup files.</div>
            <div style="display:flex;gap:.5rem;flex-wrap:wrap;">
              <button class="btn btn-success" onclick="createBackup()"><i class="fas fa-download"></i> Create Backup</button>
              <label class="btn btn-primary" for="restoreFile" style="cursor:pointer;"><i class="fas fa-upload"></i> Restore Backup</label>
              <input id="restoreFile" type="file" accept=".zip,.sql" style="display:none" onchange="restoreBackup(this)">
            </div>
            <div style="margin-top:1rem;">
              <div class="help-text">Recent Backups</div>
              <ul id="backupList" style="list-style:none;padding-left:0;margin-top:.5rem;display:grid;gap:.35rem;">
                <li>backup_2025-09-01_1200.zip</li>
                <li>backup_2025-08-15_0900.zip</li>
              </ul>
            </div>
          </div>
        </div>

        <div class="grid" style="grid-template-columns:1fr;">
          <div class="card">
            <div class="card-title"><i class="fas fa-sliders-h"></i> Platform Preferences</div>
            <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:1rem;">
              <div>
                <div class="help-text">Theme</div>
                <select class="select" id="theme"><option>Light</option><option selected>Auto</option><option>Dark</option></select>
              </div>
              <div>
                <div class="help-text">Default Page Size</div>
                <select class="select" id="pageSize"><option>10</option><option selected>25</option><option>50</option><option>100</option></select>
              </div>
              <div>
                <div class="help-text">Email Notifications</div>
                <select class="select" id="notifications"><option>Enabled</option><option selected>Important Only</option><option>Disabled</option></select>
              </div>
            </div>
            <div style="display:flex;gap:.5rem;margin-top:1rem;">
              <button class="btn btn-primary" onclick="savePreferences()"><i class="fas fa-save"></i> Save Preferences</button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <script src="assets/js/vendor-all.min.js"></script>
  <script src="assets/js/plugins/bootstrap.min.js"></script>
  <script src="assets/js/ripple.js"></script>
  <script src="assets/js/pcoded.min.js"></script>
  <script>
    function saveSession(){ alert('Session saved (demo).'); }
    function resetSession(){ document.getElementById('session').selectedIndex=1; document.getElementById('currentSemester').selectedIndex=1; document.getElementById('startDate').value='2025-01-10'; document.getElementById('endDate').value='2025-12-20'; }
    function createBackup(){ alert('Backup created (demo). Connect to backend to implement.'); const li = document.createElement('li'); const ts = new Date().toISOString().slice(0,16).replace('T','-').replace(':',''); li.textContent='backup_'+ts+'.zip'; document.getElementById('backupList').prepend(li); }
    function restoreBackup(input){ if (!input.files[0]) return; alert('Restoring '+ input.files[0].name +' (demo).'); input.value=''; }
    function savePreferences(){ alert('Preferences saved (demo).'); }
  </script>
</body>
</html>