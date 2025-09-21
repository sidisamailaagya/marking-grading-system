<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta http-equiv="X-UA-Compatible" content="IE=edge" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=0, minimal-ui">
  <title>Reports & Analytics - Lecturer | Marking & Grading System</title>
  <meta name="description" content="Course-level analytics, grade distribution, export" />
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
    .grid{ display:grid; grid-template-columns: 2fr 1fr; gap:1.25rem; }
    .card{ background:#fff; border-radius:var(--radius); box-shadow:var(--card-shadow); padding:1.25rem; }
    .card:hover{ box-shadow:var(--card-shadow-hover); }
    .toolbar{ display:flex; gap:.5rem; flex-wrap:wrap; align-items:center; }
    .select{ padding:.65rem .8rem; border:2px solid #e2e8f0; border-radius:10px; background:#fff; }
    .select:focus{ outline:none; border-color:var(--primary-color); box-shadow:0 0 0 3px rgba(102,126,234,.12); }
    .btn{ padding:.68rem 1rem; border:none; border-radius:10px; font-weight:700; display:inline-flex; align-items:center; gap:.5rem; cursor:pointer; transition:var(--transition); }
    .btn-primary{ background:linear-gradient(135deg,#667eea,#5a67d8); color:#fff; }
    .btn-outline{ background:#fff; color:#4a5568; border:1px solid #e2e8f0; }
    .muted{ color:#718096; font-size:.9rem; }
    table{ width:100%; border-collapse:collapse; }
    th, td{ padding:.65rem; border-bottom:1px solid #f1f5f9; text-align:left; }
    th{ background:#f8fafc; color:#475569; font-weight:800; }
  </style>
</head>
<body>
  <!-- Sidebar -->
  <nav class="pcoded-navbar menu-light">
    <div class="navbar-wrapper">
      <div class="navbar-content scroll-div">
        <ul class="nav pcoded-inner-navbar">
          <li class="nav-item pcoded-menu-caption"><label>Lecturer Menu</label></li>
          <li class="nav-item"><a href="dashboard.php" class="nav-link"><span class="pcoded-micon"><i class="fas fa-house"></i></span><span class="pcoded-mtext">Dashboard</span></a></li>
          <li class="nav-item"><a href="my-courses.php" class="nav-link"><span class="pcoded-micon"><i class="fas fa-book"></i></span><span class="pcoded-mtext">My Courses</span></a></li>
          <li class="nav-item"><a href="enter-grades.php" class="nav-link"><span class="pcoded-micon"><i class="fas fa-pen-to-square"></i></span><span class="pcoded-mtext">Enter Grades</span></a></li>
          <li class="nav-item"><a href="student-performance.php" class="nav-link"><span class="pcoded-micon"><i class="fas fa-user-chart"></i></span><span class="pcoded-mtext">View Student Performance</span></a></li>
          <li class="nav-item"><a href="reports-analytics.php" class="nav-link active"><span class="pcoded-micon"><i class="fas fa-chart-simple"></i></span><span class="pcoded-mtext">Reports & Analytics</span></a></li>
          <li class="nav-item"><a href="feedback-remarks.php" class="nav-link"><span class="pcoded-micon"><i class="fas fa-comments"></i></span><span class="pcoded-mtext">Feedback & Remarks</span></a></li>
          <li class="nav-item"><a href="profile-settings.php" class="nav-link"><span class="pcoded-micon"><i class="fas fa-user-cog"></i></span><span class="pcoded-mtext">Profile Settings</span></a></li>
          <li class="nav-item"><a href="../Admin/logout.php" class="nav-link"><span class="pcoded-micon"><i class="fas fa-sign-out-alt"></i></span><span class="pcoded-mtext">Logout</span></a></li>
        </ul>
      </div>
    </div>
  </nav>

  <!-- Header -->
  <header class="navbar pcoded-header navbar-expand-lg navbar-light">
    <div class="m-header">
      <a class="mobile-menu" id="mobile-collapse" href="#"><span></span></a>
      <a href="#" class="b-brand"><h3 class="text-primary mb-0">Lecturer Portal</h3></a>
      <a href="#" class="mob-toggler"><i class="feather icon-more-vertical"></i></a>
    </div>
  </header>

  <div class="pcoded-main-container">
    <div class="pcoded-content">
      <div class="page-hero">
        <div class="container">
          <h1>Reports & Analytics</h1>
          <p>Analyze grade distributions and export results (UI demo)</p>
        </div>
      </div>

      <div class="main container">
        <div class="card" style="margin-bottom:1rem;">
          <div class="toolbar">
            <div class="muted">Course:</div>
            <select id="courseSelect" class="select"></select>
            <div class="muted">Level:</div>
            <select id="levelSelect" class="select">
              <option value="">All</option>
              <option>100</option>
              <option>200</option>
              <option>300</option>
              <option>400</option>
            </select>
            <button class="btn btn-outline" onclick="generate()"><i class="fas fa-magnifying-glass-chart"></i> Generate</button>
            <button class="btn btn-primary" onclick="exportFile('pdf')"><i class="fas fa-file-pdf"></i> Export PDF</button>
            <button class="btn btn-primary" onclick="exportFile('excel')"><i class="fas fa-file-excel"></i> Export Excel</button>
          </div>
        </div>

        <div class="grid">
          <div class="card">
            <div style="font-weight:800;color:#2d3748;margin-bottom:.5rem;">Grade Distribution</div>
            <div id="chartPlaceholder" style="height:300px;border:2px dashed #e2e8f0;border-radius:12px;display:flex;align-items:center;justify-content:center;color:#64748b;">Chart placeholder</div>
          </div>
          <div class="card">
            <div style="font-weight:800;color:#2d3748;margin-bottom:.5rem;">Top/Bottom Performers</div>
            <table>
              <thead><tr><th>Student</th><th>Final %</th><th>Grade</th></tr></thead>
              <tbody id="performersBody"></tbody>
            </table>
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
    const courses = [
      { code:'CS101', title:'Intro to Programming', levels:[100,200] },
      { code:'CS201', title:'Data Structures', levels:[200] },
      { code:'CS301', title:'Database Systems', levels:[300] },
    ];
    const grades = {
      'CS101': [ {name:'Sarah Johnson', final: 95, grade:'A'}, {name:'Michael Chen', final: 79, grade:'B'}, {name:'Emily Davis', final: 68, grade:'C'}, {name:'David Wilson', final: 44, grade:'F'} ],
      'CS201': [ {name:'David Wilson', final: 70, grade:'A'}, {name:'Lisa Anderson', final: 63, grade:'B'} ],
      'CS301': [ {name:'John Smith', final: 82, grade:'A'} ]
    };

    function init(){
      const sel = document.getElementById('courseSelect');
      courses.forEach(c=>{ const opt=document.createElement('option'); opt.value=c.code; opt.textContent=`${c.code} — ${c.title}`; sel.appendChild(opt); });
      sel.value = courses[0].code;
      generate();
    }

    function generate(){
      const code = document.getElementById('courseSelect').value;
      // Chart placeholder text update
      document.getElementById('chartPlaceholder').textContent = `Grade distribution for ${code} (demo)`;

      // Performers list
      const data = (grades[code]||[]).slice().sort((a,b)=>b.final-a.final);
      const body = document.getElementById('performersBody');
      body.innerHTML = '';
      data.slice(0,3).forEach(r => { // top 3
        const tr = document.createElement('tr'); tr.innerHTML = `<td>${r.name}</td><td>${r.final}%</td><td>${r.grade}</td>`; body.appendChild(tr);
      });
      if (data.length>3){
        const sep = document.createElement('tr'); sep.innerHTML = `<td colspan="3" class="muted">…</td>`; body.appendChild(sep);
        const last = data[data.length-1];
        const tr = document.createElement('tr'); tr.innerHTML = `<td>${last.name}</td><td>${last.final}%</td><td>${last.grade}</td>`; body.appendChild(tr);
      }
    }

    function exportFile(type){ alert(`Exporting ${type.toUpperCase()} (demo). Wire to backend later.`); }

    window.addEventListener('load', init);
  </script>
</body>
</html>