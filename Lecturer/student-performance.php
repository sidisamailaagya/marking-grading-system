<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta http-equiv="X-UA-Compatible" content="IE=edge" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=0, minimal-ui">
  <title>View Student Performance - Lecturer | Marking & Grading System</title>
  <meta name="description" content="View detailed student performance and predicted grade" />
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
    .grid{ display:grid; grid-template-columns: 1.1fr 1fr; gap:1.25rem; }
    .card{ background:#fff; border-radius:var(--radius); box-shadow:var(--card-shadow); padding:1.25rem; }
    .card:hover{ box-shadow:var(--card-shadow-hover); }
    .profile{ display:flex; gap:1rem; align-items:center; }
    .avatar{ width:64px; height:64px; border-radius:50%; background:linear-gradient(135deg,#667eea,#764ba2); color:#fff; display:flex; align-items:center; justify-content:center; font-weight:800; font-size:1.3rem; }
    .muted{ color:#718096; font-size:.9rem; }
    .tag{ padding:.2rem .55rem; border-radius:999px; background:#edf2f7; color:#4a5568; font-weight:700; font-size:.8rem; display:inline-block; }
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
          <li class="nav-item"><a href="student-performance.php" class="nav-link active"><span class="pcoded-micon"><i class="fas fa-user-chart"></i></span><span class="pcoded-mtext">View Student Performance</span></a></li>
          <li class="nav-item"><a href="reports-analytics.php" class="nav-link"><span class="pcoded-micon"><i class="fas fa-chart-simple"></i></span><span class="pcoded-mtext">Reports & Analytics</span></a></li>
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
          <h1>View Student Performance</h1>
          <p>Detailed breakdown and predicted grade (demo)</p>
        </div>
      </div>

      <div class="main container">
        <div class="grid">
          <div class="card">
            <div class="profile">
              <div class="avatar" id="avatar">SJ</div>
              <div>
                <div style="font-weight:800;color:#2d3748;" id="studentName">Sarah Johnson</div>
                <div class="muted" id="studentMeta">Matric: MAT/CS/2024/001 · Level: 200 · Computer Science</div>
              </div>
            </div>
            <div style="margin-top:1rem;">
              <div class="muted" style="margin-bottom:.4rem;">Course Performance History</div>
              <table>
                <thead>
                  <tr><th>Course</th><th>Semester</th><th>Academic %</th><th>Behavior Adj.</th><th>Final %</th><th>Grade</th></tr>
                </thead>
                <tbody id="historyBody"></tbody>
              </table>
            </div>
          </div>
          <div class="card">
            <div style="font-weight:800;color:#2d3748;margin-bottom:.5rem;">Insights</div>
            <div class="muted">Predicted Grade (next semester)</div>
            <div style="font-size:2.4rem;font-weight:900;color:#2d3748;" id="predicted">A</div>
            <div class="muted" style="margin:.5rem 0 1rem;">Based on trajectory from past 3 courses and behavior trend.</div>
            <div>
              <div class="tag">Strength: Assignments</div>
              <div class="tag">Weakness: Punctuality</div>
              <div class="tag">Trend: Improving</div>
            </div>
          </div>
        </div>
        
        <div class="card" style="margin-top:1rem;">
          <div class="muted" style="margin-bottom:.5rem;">Current Course Components (demo)</div>
          <table>
            <thead><tr><th>Component</th><th>Weight</th><th>Score</th></tr></thead>
            <tbody>
              <tr><td>Assignments</td><td>20%</td><td>18/20</td></tr>
              <tr><td>Tests</td><td>20%</td><td>15/20</td></tr>
              <tr><td>Project</td><td>20%</td><td>17/20</td></tr>
              <tr><td>Exam</td><td>40%</td><td>34/40</td></tr>
              <tr><td>Behavior Adj.</td><td>10%</td><td>8/10</td></tr>
            </tbody>
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
    const student = {
      name: 'Sarah Johnson',
      matric: 'MAT/CS/2024/001',
      level: 200,
      department: 'Computer Science',
      initials: 'SJ',
      history: [
        { course:'CS101', semester:'2024/2025 - First', academic: 84, behavior: 8, final: 88, grade: 'A' },
        { course:'CS201', semester:'2024/2025 - Second', academic: 76, behavior: 7, final: 79, grade: 'B' },
        { course:'CS301', semester:'2025/2026 - First', academic: 81, behavior: 9, final: 85, grade: 'A' },
      ]
    };

    function render(){
      document.getElementById('studentName').textContent = student.name;
      document.getElementById('studentMeta').textContent = `Matric: ${student.matric} · Level: ${student.level} · ${student.department}`;
      document.getElementById('avatar').textContent = student.initials;
      const body = document.getElementById('historyBody'); body.innerHTML='';
      student.history.forEach(h => {
        const tr = document.createElement('tr');
        tr.innerHTML = `<td>${h.course}</td><td>${h.semester}</td><td>${h.academic}%</td><td>+${h.behavior}%</td><td>${h.final}%</td><td>${h.grade}</td>`;
        body.appendChild(tr);
      });
      // Simple predicted grade heuristic
      const avg = Math.round(student.history.reduce((a,b)=>a+b.final,0)/student.history.length);
      document.getElementById('predicted').textContent = avg>=70?'A':avg>=60?'B':avg>=50?'C':avg>=45?'D':'F';
    }

    window.addEventListener('load', render);
  </script>
</body>
</html>