<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta http-equiv="X-UA-Compatible" content="IE=edge" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=0, minimal-ui">
  <title>Student Dashboard | Marking & Grading System</title>
  <meta name="description" content="Student portal to view results, feedback and progress" />
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
    .grid-3{ display:grid; grid-template-columns: repeat(3, 1fr); gap: 1.25rem; }
    .grid{ display:grid; grid-template-columns: 2fr 1fr; gap: 1.25rem; }
    .card{ background:#fff; border-radius:var(--radius); box-shadow:var(--card-shadow); padding:1.25rem; }
    .card:hover{ box-shadow:var(--card-shadow-hover); }
    .stat{ display:flex; align-items:center; gap:1rem; }
    .icon{ width:52px; height:52px; border-radius:12px; display:flex; align-items:center; justify-content:center; color:#fff; font-size:1.25rem; }
    .blue{ background:linear-gradient(135deg,#667eea,#5a67d8); }
    .green{ background:linear-gradient(135deg,#48bb78,#38a169); }
    .orange{ background:linear-gradient(135deg,#ed8936,#dd6b20); }
    .value{ font-size:1.8rem; font-weight:900; color:#2d3748; }
    .muted{ color:#718096; font-size:.9rem; }
    .list{ display:grid; gap:.6rem; }
    .item{ border:1px solid #edf2f7; border-radius:12px; padding:.75rem; display:flex; align-items:center; justify-content:space-between; }
    .pill{ padding:.18rem .55rem; border-radius:999px; font-weight:800; font-size:.75rem; }
    .pill.good{ background:rgba(72,187,120,.12); color:#2f855a; }
    .pill.warn{ background:rgba(237,137,54,.12); color:#9c4221; }
    .pill.info{ background:rgba(66,153,225,.12); color:#2b6cb0; }
    .btn{ padding:.6rem .9rem; border:none; border-radius:10px; font-weight:700; display:inline-flex; align-items:center; gap:.45rem; cursor:pointer; transition:var(--transition); }
    .btn-primary{ background:linear-gradient(135deg,#667eea,#5a67d8); color:#fff; }
    .btn-outline{ background:#fff; color:#4a5568; border:1px solid #e2e8f0; }
    @media (max-width:1100px){ .grid{ grid-template-columns:1fr } .grid-3{ grid-template-columns:1fr } }
  </style>
</head>
<body>
  <!-- Sidebar -->
  <nav class="pcoded-navbar menu-light">
    <div class="navbar-wrapper">
      <div class="navbar-content scroll-div">
        <ul class="nav pcoded-inner-navbar">
          <li class="nav-item pcoded-menu-caption"><label>Student Menu</label></li>
          <li class="nav-item"><a href="dashboard.php" class="nav-link active"><span class="pcoded-micon"><i class="fas fa-house"></i></span><span class="pcoded-mtext">Dashboard</span></a></li>
          <li class="nav-item"><a href="my-results.php" class="nav-link"><span class="pcoded-micon"><i class="fas fa-list-check"></i></span><span class="pcoded-mtext">My Results</span></a></li>
          <li class="nav-item"><a href="predicted-grade.php" class="nav-link"><span class="pcoded-micon"><i class="fas fa-crystal-ball"></i></span><span class="pcoded-mtext">Predicted Future Grade</span></a></li>
          <li class="nav-item"><a href="feedback-comments.php" class="nav-link"><span class="pcoded-micon"><i class="fas fa-comments"></i></span><span class="pcoded-mtext">Feedback & Comments</span></a></li>
          <li class="nav-item"><a href="performance-history.php" class="nav-link"><span class="pcoded-micon"><i class="fas fa-chart-line"></i></span><span class="pcoded-mtext">Performance History</span></a></li>
          <li class="nav-item"><a href="profile-settings.php" class="nav-link"><span class="pcoded-micon"><i class="fas fa-user"></i></span><span class="pcoded-mtext">Profile Settings</span></a></li>
          <li class="nav-item"><a href="../logout.php" class="nav-link"><span class="pcoded-micon"><i class="fas fa-sign-out-alt"></i></span><span class="pcoded-mtext">Logout</span></a></li>
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
          <h1>Welcome, Sarah</h1>
          <p>Academic Session: <strong id="session">2024/2025</strong> · Semester: <strong id="semester">Second</strong></p>
        </div>
      </div>

      <div class="main container">
        <!-- Stats -->
        <div class="grid-3">
          <div class="card stat">
            <div class="icon blue"><i class="fas fa-graduation-cap"></i></div>
            <div>
              <div class="value" id="statGpa">0.00</div>
              <div class="muted">Current GPA</div>
            </div>
          </div>
          <div class="card stat">
            <div class="icon green"><i class="fas fa-award"></i></div>
            <div>
              <div class="value" id="statLast">0%</div>
              <div class="muted">Last Exam Result</div>
            </div>
          </div>
          <div class="card stat">
            <div class="icon orange"><i class="fas fa-calendar-check"></i></div>
            <div>
              <div class="value" id="statUpcoming">0</div>
              <div class="muted">Upcoming Assignments</div>
            </div>
          </div>
        </div>

        <div class="grid" style="margin-top:1.25rem;">
          <div class="card">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:.75rem;">
              <h3 style="margin:0;color:#2d3748;">Upcoming Assignments</h3>
              <a class="btn btn-outline" href="my-results.php"><i class="fas fa-arrow-right"></i> View Results</a>
            </div>
            <div class="list" id="assignList"></div>
          </div>
          <div class="card">
            <h3 style="margin:0 0 .75rem;color:#2d3748;">Notifications</h3>
            <div class="list" id="notifList"></div>
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
    // Demo data (UI only)
    const sessionInfo = { session: '2024/2025', semester: 'Second' };
    const stats = { gpa: 3.72, lastExam: 86, upcomingCount: 3 };
    const assignments = [
      { course:'CS201', title:'Test 1', due:'Sep 21, 2025', status:'Pending' },
      { course:'CS301', title:'Project Proposal', due:'Sep 25, 2025', status:'Pending' },
      { course:'MATH201', title:'Assignment 2', due:'Sep 28, 2025', status:'Pending' },
    ];
    const notifications = [
      { type:'info', text:'Your profile was updated successfully', time:'Today, 08:55 AM' },
      { type:'warn', text:'Low attendance warning in CS301', time:'Yesterday' },
      { type:'info', text:'Mid-semester break next week', time:'2 days ago' },
    ];

    function animateValue(el, to, decimals=0, suffix=''){
      const start = 0; const dur = 800; const t0 = performance.now();
      const step = (t)=>{ const p=Math.min((t-t0)/dur,1); const val = (start + (to-start)*p).toFixed(decimals); el.textContent = val + suffix; if (p<1) requestAnimationFrame(step); };
      requestAnimationFrame(step);
    }

    function render(){
      document.getElementById('session').textContent = sessionInfo.session;
      document.getElementById('semester').textContent = sessionInfo.semester;
      animateValue(document.getElementById('statGpa'), stats.gpa, 2);
      animateValue(document.getElementById('statLast'), stats.lastExam, 0, '%');
      animateValue(document.getElementById('statUpcoming'), stats.upcomingCount, 0);

      const aList = document.getElementById('assignList'); aList.innerHTML='';
      assignments.forEach(a=>{
        const row = document.createElement('div'); row.className='item';
        row.innerHTML = `<div><div style="font-weight:800;color:#2d3748;">${a.course} · ${a.title}</div><div class="muted">Due: ${a.due}</div></div><span class="pill info">${a.status}</span>`;
        aList.appendChild(row);
      });

      const nList = document.getElementById('notifList'); nList.innerHTML='';
      notifications.forEach(n=>{
        const cls = n.type==='warn'?'warn':n.type==='info'?'info':'good';
        const row = document.createElement('div'); row.className='item';
        row.innerHTML = `<div><div style=\"font-weight:700;color:#2d3748;\">${n.text}</div><div class=\"muted\">${n.time}</div></div><span class=\"pill ${cls}\">${n.type}</span>`;
        nList.appendChild(row);
      });
    }

    window.addEventListener('load', render);
  </script>
</body>
</html>