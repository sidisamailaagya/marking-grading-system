<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta http-equiv="X-UA-Compatible" content="IE=edge" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=0, minimal-ui">
  <title>Predicted Future Grade | Student Portal</title>
  <meta name="description" content="Prediction of expected grade and suggestions to improve" />
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
    .toolbar{ display:flex; gap:.5rem; flex-wrap:wrap; align-items:center; }
    .select, .input{ padding:.65rem .8rem; border:2px solid #e2e8f0; border-radius:10px; background:#fff; }
    .btn{ padding:.65rem 1rem; border:none; border-radius:10px; font-weight:700; display:inline-flex; align-items:center; gap:.5rem; cursor:pointer; transition:var(--transition); }
    .btn-primary{ background:linear-gradient(135deg,#667eea,#5a67d8); color:#fff; }
    .muted{ color:#718096; font-size:.9rem; }
    .pill{ padding:.18rem .55rem; border-radius:999px; font-weight:800; font-size:.75rem; background:#edf2f7; color:#4a5568; display:inline-block; }
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
          <li class="nav-item"><a href="predicted-grade.php" class="nav-link active"><span class="pcoded-micon"><i class="fas fa-crystal-ball"></i></span><span class="pcoded-mtext">Predicted Future Grade</span></a></li>
          <li class="nav-item"><a href="feedback-comments.php" class="nav-link"><span class="pcoded-micon"><i class="fas fa-comments"></i></span><span class="pcoded-mtext">Feedback & Comments</span></a></li>
          <li class="nav-item"><a href="performance-history.php" class="nav-link"><span class="pcoded-micon"><i class="fas fa-chart-line"></i></span><span class="pcoded-mtext">Performance History</span></a></li>
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
          <h1>Predicted Future Grade</h1>
          <p>Forecast based on your past performance and trend (demo)</p>
        </div>
      </div>

      <div class="main container">
        <div class="grid">
          <div class="card">
            <div style="font-weight:800;color:#2d3748;margin-bottom:.5rem;">Prediction</div>
            <div class="muted">Current trend suggests your next semester performance:</div>
            <div style="font-size:3rem;font-weight:900;color:#2d3748;margin:.25rem 0;" id="predicted">A</div>
            <div class="muted">Confidence: <span class="pill" id="confidence">High</span></div>
            <div class="muted" style="margin-top:.6rem;">If you improve these areas:</div>
            <ul id="suggestList" style="margin:.35rem 0 0 1rem; color:#4a5568;">
              <li>Attend all lectures and tutorials regularly</li>
              <li>Improve punctuality to boost final score</li>
              <li>Practice more past questions in weak topics</li>
            </ul>
          </div>
          <div class="card">
            <div style="font-weight:800;color:#2d3748;margin-bottom:.5rem;">What-if Scenario</div>
            <div class="toolbar" style="margin-bottom:.5rem;">
              <div class="muted">If my behavior score improves by</div>
              <select id="deltaBehavior" class="select">
                <option value="0">0%</option>
                <option value="5">+5%</option>
                <option value="10" selected>+10%</option>
                <option value="20">+20%</option>
              </select>
              <div class="muted">and my academics improve by</div>
              <select id="deltaAcademic" class="select">
                <option value="0">0%</option>
                <option value="5">+5%</option>
                <option value="10">+10%</option>
                <option value="15" selected>+15%</option>
              </select>
              <button class="btn btn-primary" onclick="recompute()"><i class="fas fa-calculator"></i> Recompute</button>
            </div>
            <div class="muted">Projected final percentage:</div>
            <div style="font-size:2.2rem;font-weight:900;color:#2d3748;" id="projected">85%</div>
          </div>
        </div>

        <div class="card" style="margin-top:1rem;">
          <div style="font-weight:800;color:#2d3748;margin-bottom:.5rem;">Recent Results (basis for prediction)</div>
          <table style="width:100%;border-collapse:collapse;">
            <thead><tr><th>Course</th><th>Semester</th><th>Final %</th><th>Grade</th></tr></thead>
            <tbody id="recentBody"></tbody>
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
    const recent = [
      { course:'CS101', sem:'2024/2025 - First', final: 84, grade:'A' },
      { course:'CS201', sem:'2024/2025 - Second', final: 78, grade:'B' },
      { course:'MATH201', sem:'2024/2025 - Second', final: 66, grade:'C' },
    ];

    function letter(x){ if (x>=70) return 'A'; if (x>=60) return 'B'; if (x>=50) return 'C'; if (x>=45) return 'D'; return 'F'; }

    function render(){
      const avg = Math.round(recent.reduce((a,b)=>a+b.final,0)/recent.length);
      document.getElementById('predicted').textContent = letter(avg);
      document.getElementById('confidence').textContent = avg>=80? 'High' : avg>=65? 'Medium' : 'Low';
      document.getElementById('projected').textContent = avg + '%';
      const body = document.getElementById('recentBody'); body.innerHTML='';
      recent.forEach(r => { const tr=document.createElement('tr'); tr.innerHTML=`<td>${r.course}</td><td>${r.sem}</td><td>${r.final}%</td><td>${r.grade}</td>`; body.appendChild(tr); });
    }

    function recompute(){
      const db = Number(document.getElementById('deltaBehavior').value)||0;
      const da = Number(document.getElementById('deltaAcademic').value)||0;
      const avg = Math.round(recent.reduce((a,b)=>a+b.final,0)/recent.length);
      const projected = Math.min(100, Math.round(avg * (1 + da/100) + db*0.2));
      document.getElementById('projected').textContent = projected + '%';
      document.getElementById('predicted').textContent = letter(projected);
    }

    window.addEventListener('load', render);
  </script>
</body>
</html>