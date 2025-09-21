<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta http-equiv="X-UA-Compatible" content="IE=edge" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=0, minimal-ui">
  <title>Performance History | Student Portal</title>
  <meta name="description" content="Compare semester results and visualize trends" />
  <link rel="icon" href="../Admin/assets/images/favicon.ico" type="image/x-icon">
  <link rel="stylesheet" href="../Admin/assets/css/style.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <style>
    :root { --primary-color:#667eea; --primary-dark:#5a67d8; --secondary-color:#764ba2; --success-color:#48bb78; --warning-color:#ed8936; --danger-color:#f56565; --info-color:#4299e1; --light-bg:#f8fafc; --card-shadow:0 10px 25px rgba(0,0,0,0.1); --card-shadow-hover:0 20px 40px rgba(0,0,0,0.15); --radius:14px; --transition: all .25s ease; }
    body { font-family:'Inter',sans-serif; background: linear-gradient(135deg,#667eea 0%,#764ba2 100%); min-height:100vh; }
    .pcoded-navbar{ background:rgba(255,255,255,0.96); backdrop-filter:blur(10px); border-right:1px solid rgba(0,0,0,0.05); box-shadow:0 0 30px rgba(0,0,0,0.12); }
    .pcoded-header{ background:rgba(255,255,255,0.96); backdrop-filter:blur(10px); border-bottom:1px solid rgba(0,0,0,0.05); box-shadow:0 6px 20px rgba(0,0,0,0.06); }
    .page-hero{ background: linear-gradient(135deg, rgba(102,126,234,.92), rgba(118,75,162,.92)); color:#fff; padding: 2rem 0; border-radius: 0 0 26px 26px; margin-bottom: 1.5rem; position:relative; overflow:hidden; }
    .page-hero::before{ content:''; position:absolute; inset:0; opacity:.15; background: radial-gradient(600px 200px at 10% 10%, #fff, transparent), radial-gradient(600px 200px at 90% 80%, #fff, transparent); }
    .container{ padding: 0 1.25rem; }
    .main{ max-width:1400px; margin:0 auto; padding:1.25rem; }
    .grid{ display:grid; grid-template-columns: 1.2fr 1fr; gap: 1.25rem; }
    .card{ background:#fff; border-radius:var(--radius); box-shadow:var(--card-shadow); padding:1.25rem; }
    .card:hover{ box-shadow:var(--card-shadow-hover); }
    .muted{ color:#718096; font-size:.9rem; }
    table{ width:100%; border-collapse:collapse; }
    th, td{ padding:.65rem; border-bottom:1px solid #f1f5f9; text-align:left; }
    th{ background:#f8fafc; color:#475569; font-weight:800; }
    .chip{ padding:.18rem .55rem; border-radius:999px; background:#edf2f7; color:#4a5568; font-weight:800; font-size:.75rem; }
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
          <li class="nav-item"><a href="performance-history.php" class="nav-link active"><span class="pcoded-micon"><i class="fas fa-chart-line"></i></span><span class="pcoded-mtext">Performance History</span></a></li>
          <li class="nav-item"><a href="profile-settings.php" class="nav-link"><span class="pcoded-micon"><i class="fas fa-user"></i></span><span class="pcoded-mtext">Profile Settings</span></a></li>
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
          <h1>Performance History</h1>
          <p>Compare semesters and view improvement/decline trends</p>
        </div>
      </div>

      <div class="main container">
        <div class="grid">
          <div class="card">
            <div style="font-weight:800;color:#2d3748;margin-bottom:.5rem;">Trend (demo)</div>
            <div id="chart" style="height:320px;border:2px dashed #e2e8f0;border-radius:12px;display:flex;align-items:center;justify-content:center;color:#64748b;">Trend chart placeholder</div>
          </div>
          <div class="card">
            <div style="font-weight:800;color:#2d3748;margin-bottom:.5rem;">Summary</div>
            <div class="muted">Best Semester: <span class="chip" id="bestSem">—</span></div>
            <div class="muted" style="margin-top:.3rem;">Worst Semester: <span class="chip" id="worstSem">—</span></div>
            <div class="muted" style="margin-top:.3rem;">Avg. Final %: <span class="chip" id="avgFinal">—</span></div>
          </div>
        </div>

        <div class="card" style="margin-top:1rem;">
          <div style="font-weight:800;color:#2d3748;margin-bottom:.5rem;">Semester Comparison</div>
          <table style="width:100%;border-collapse:collapse;">
            <thead><tr><th>Semester</th><th>Courses</th><th>Avg Final %</th><th>GPA (demo)</th></tr></thead>
            <tbody id="semBody"></tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

  <script src="../Admin/assets/js/vendor-all.min.js"></script>
  <script src="../Admin/assets/js/plugins/bootstrap.min.js"></script>
  <script src="../Admin/assets/js/ripple.js"></script>
  <script src="../Admin/assets/js/pcoded.min.js"></script>
  <script>
    const semesters = [
      { name:'2024/2025 - First', finals:[84, 76, 72], gpa:3.62 },
      { name:'2024/2025 - Second', finals:[78, 66, 74], gpa:3.18 },
      { name:'2025/2026 - First', finals:[89, 85, 80], gpa:3.88 },
    ];

    function avg(arr){ return Math.round(arr.reduce((a,b)=>a+b,0)/arr.length); }

    function render(){
      const semBody = document.getElementById('semBody'); semBody.innerHTML='';
      const stats = semesters.map(s => ({ name:s.name, avg:avg(s.finals), count:s.finals.length, gpa:s.gpa }));
      stats.forEach(s => { const tr=document.createElement('tr'); tr.innerHTML=`<td>${s.name}</td><td>${s.count}</td><td>${s.avg}%</td><td>${s.gpa.toFixed(2)}</td>`; semBody.appendChild(tr); });
      const best = stats.reduce((p,c)=>c.avg>p.avg?c:p, stats[0]);
      const worst = stats.reduce((p,c)=>c.avg<p.avg?c:p, stats[0]);
      document.getElementById('bestSem').textContent = `${best.name} (${best.avg}%)`;
      document.getElementById('worstSem').textContent = `${worst.name} (${worst.avg}%)`;
      document.getElementById('avgFinal').textContent = `${avg(stats.map(s=>s.avg))}%`;
    }

    window.addEventListener('load', render);
  </script>
</body>
</html>