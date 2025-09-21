<!DOCTYPE html>
<html lang="en">
<head>
  <title>Reports & Analytics - Marking & Grading System</title>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=0, minimal-ui">
  <meta http-equiv="X-UA-Compatible" content="IE=edge" />
  <meta name="description" content="Generate academic reports and visualize analytics" />
  <meta name="keywords" content="reports, analytics, export, pdf, excel, charts">
  <meta name="author" content="Marking & Grading System" />
  <link rel="icon" href="assets/images/favicon.ico" type="image/x-icon">
  <link rel="stylesheet" href="assets/css/style.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <style>
    :root { --primary-color:#667eea; --primary-dark:#5a67d8; --secondary-color:#764ba2; --success-color:#48bb78; --warning-color:#ed8936; --danger-color:#f56565; --info-color:#4299e1; --light-bg:#f8fafc; --card-shadow:0 10px 25px rgba(0,0,0,0.1); --card-shadow-hover:0 20px 40px rgba(0,0,0,0.15); --border-radius:12px; --transition: all 0.3s cubic-bezier(0.4,0,0.2,1);} *{box-sizing:border-box;margin:0;padding:0} body{font-family:'Inter',sans-serif;background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);min-height:100vh;overflow-x:hidden}
    .pcoded-navbar{background:rgba(255,255,255,0.95);backdrop-filter:blur(10px);border-right:1px solid rgba(255,255,255,0.2);box-shadow:0 0 30px rgba(0,0,0,0.1)} .nav-link{transition:var(--transition);border-radius:8px;margin:2px 8px}.nav-link:hover{background:linear-gradient(135deg,var(--primary-color),var(--primary-dark));color:white !important; transform:translateX(5px)} .nav-link.active{background:linear-gradient(135deg,var(--primary-color),var(--primary-dark));color:white !important}
    .pcoded-header{background:rgba(255,255,255,0.95);backdrop-filter:blur(10px);border-bottom:1px solid rgba(255,255,255,0.2);box-shadow:0 2px 20px rgba(0,0,0,0.1)}
    .page-header{background:linear-gradient(135deg,rgba(102,126,234,0.9),rgba(118,75,162,0.9));color:white;padding:2rem 0;margin-bottom:2rem;border-radius:0 0 30px 30px;position:relative;overflow:hidden}
    .page-header::before{content:'';position:absolute;inset:0;background:url('data:image/svg+xml,<svg xmlns=\"http://www.w3.org/2000/svg\" viewBox=\"0 0 100 100\"><defs><pattern id=\"grain\" width=\"100\" height=\"100\" patternUnits=\"userSpaceOnUse\"><circle cx=\"25\" cy=\"25\" r=\"1\" fill=\"white\" opacity=\"0.1\"/><circle cx=\"75\" cy=\"75\" r=\"1\" fill=\"white\" opacity=\"0.1\"/><circle cx=\"50\" cy=\"10\" r=\"1\" fill=\"white\" opacity=\"0.1\"/></pattern></defs><rect width=\"100\" height=\"100\" fill=\"url(%23grain)\"/></svg>');animation:float 20s ease-in-out infinite}
    @keyframes float{0%,100%{transform:translateY(0)}50%{transform:translateY(-10px)}} .page-content{text-align:center;position:relative;z-index:2}
    .page-title{font-size:2.5rem;font-weight:700;margin-bottom:0.5rem}
    .page-subtitle{font-size:1.1rem;opacity:0.9}
    .main-container{padding:2rem;max-width:1400px;margin:0 auto}
    .grid{display:grid;grid-template-columns:1fr;gap:2rem}
    .card{background:white;border-radius:var(--border-radius);box-shadow:var(--card-shadow);padding:1.5rem}
    .card:hover{box-shadow:var(--card-shadow-hover)} .card-title{font-size:1.25rem;font-weight:700;color:#2d3748;margin-bottom:1rem;display:flex;align-items:center;gap:.5rem}
    .filters{display:grid;grid-template-columns:repeat(4,1fr);gap:1rem}
    .input,.select{padding:.7rem;border:2px solid #e2e8f0;border-radius:8px;background:white;font-size:.9rem}
    .input:focus,.select:focus{outline:none;border-color:var(--primary-color);box-shadow:0 0 0 3px rgba(102,126,234,0.12)}
    .btn{padding:0.7rem 1rem;border:none;border-radius:10px;font-weight:600;font-size:.9rem;cursor:pointer;transition:var(--transition);display:inline-flex;align-items:center;gap:.5rem}
    .btn-primary{background:linear-gradient(135deg,var(--primary-color),var(--primary-dark));color:white}
    .btn-secondary{background:#e2e8f0;color:#334155}
    .btn-success{background:linear-gradient(135deg,var(--success-color),#38a169);color:white}
    .btn-warning{background:linear-gradient(135deg,var(--warning-color),#dd6b20);color:white}
    .btn-danger{background:linear-gradient(135deg,var(--danger-color),#e53e3e);color:white}
    .btn:hover{transform:translateY(-2px)}
    .report-grid{display:grid;grid-template-columns:2fr 1fr;gap:1.5rem}
    .chart-placeholder{height:340px;border:2px dashed #e2e8f0;border-radius:12px;display:flex;align-items:center;justify-content:center;color:#64748b}
    .table{width:100%;border-collapse:collapse}
    .table th,.table td{padding:.7rem;border-bottom:1px solid #eef2f7;text-align:left}
    .table th{background:#f8fafc;color:#475569;font-weight:700}
    .badge{padding:.25rem .5rem;border-radius:6px;font-size:.75rem;font-weight:700;display:inline-block}
    .badge-success{background:rgba(72,187,120,0.12);color:#2f855a}
    .badge-warning{background:rgba(237,137,54,0.12);color:#9c4221}
    .badge-danger{background:rgba(245,101,101,0.12);color:#9b2c2c}
    @media (max-width:1100px){.report-grid{grid-template-columns:1fr}}
    @media (max-width:768px){.main-container{padding:1rem}.filters{grid-template-columns:1fr}}
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
        <li class="nav-item"><a href="reports-analytics.php" class="nav-link active"><span class="pcoded-micon"><i class="fas fa-chart-pie"></i></span><span class="pcoded-mtext">Reports & Analytics</span></a></li>
        <li class="nav-item"><a href="user-management.php" class="nav-link"><span class="pcoded-micon"><i class="fas fa-users-cog"></i></span><span class="pcoded-mtext">User Management</span></a></li>
        <li class="nav-item"><a href="system-settings.php" class="nav-link"><span class="pcoded-micon"><i class="fas fa-cog"></i></span><span class="pcoded-mtext">System Settings</span></a></li>
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
        <h1 class="page-title">Reports & Analytics</h1>
        <p class="page-subtitle">Generate reports per department, faculty, or level and export results</p>
      </div></div></div>

      <div class="main-container">
        <div class="grid">
          <div class="card">
            <div class="card-title"><i class="fas fa-filter"></i> Report Filters</div>
            <div class="filters">
              <select id="filterType" class="select">
                <option value="department">Per Department</option>
                <option value="faculty">Per Faculty</option>
                <option value="level">Per Level</option>
              </select>
              <select id="filterDepartment" class="select">
                <option>Computer Science</option><option>Mathematics</option><option>Physics</option><option>Chemistry</option><option>Biology</option>
              </select>
              <select id="filterFaculty" class="select">
                <option>Science</option><option>Engineering</option><option>Arts</option><option>Management</option><option>Education</option>
              </select>
              <select id="filterLevel" class="select">
                <option>100 Level</option><option>200 Level</option><option>300 Level</option><option>400 Level</option>
              </select>
            </div>
            <div style="display:flex;gap:0.5rem;margin-top:1rem;flex-wrap:wrap;">
              <button class="btn btn-primary" onclick="generateReport()"><i class="fas fa-magnifying-glass-chart"></i> Generate</button>
              <button class="btn btn-secondary" onclick="resetFilters()"><i class="fas fa-rotate"></i> Reset</button>
              <button class="btn btn-success" onclick="exportReport('pdf')"><i class="fas fa-file-pdf"></i> Export PDF</button>
              <button class="btn btn-warning" onclick="exportReport('excel')"><i class="fas fa-file-excel"></i> Export Excel</button>
            </div>
          </div>

          <div class="report-grid">
            <div class="card">
              <div class="card-title"><i class="fas fa-chart-bar"></i> Performance Overview</div>
              <div id="chartArea" class="chart-placeholder">Chart will render here (UI only)</div>
            </div>
            <div class="card">
              <div class="card-title"><i class="fas fa-award"></i> Summary</div>
              <div style="display:grid;grid-template-columns:1fr 1fr;gap:.75rem;">
                <div class="card" style="padding:1rem;box-shadow:none;border:1px solid #edf2f7;">
                  <div class="help-text">Average Score</div>
                  <div style="font-size:1.8rem;font-weight:800;color:#2d3748;">82.4%</div>
                </div>
                <div class="card" style="padding:1rem;box-shadow:none;border:1px solid #edf2f7;">
                  <div class="help-text">Pass Rate</div>
                  <div style="font-size:1.8rem;font-weight:800;color:#2d3748;">91%</div>
                </div>
                <div class="card" style="padding:1rem;box-shadow:none;border:1px solid #edf2f7;grid-column:1/-1;">
                  <div class="help-text">Top Performer</div>
                  <div style="display:flex;align-items:center;gap:.5rem;">
                    <span class="badge badge-success">A</span>
                    <span style="font-weight:600;color:#2d3748;">John Doe</span>
                    <span class="help-text">(CS101)</span>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div class="card">
            <div class="card-title"><i class="fas fa-table"></i> Detailed Results</div>
            <div style="overflow-x:auto;">
              <table class="table" id="resultsTable">
                <thead>
                  <tr>
                    <th>Student</th><th>Course</th><th>Score</th><th>Grade</th><th>Status</th>
                  </tr>
                </thead>
                <tbody>
                  <tr><td>Sarah Johnson</td><td>CS101</td><td>96</td><td><span class="badge badge-success">A</span></td><td><span class="badge badge-success">Pass</span></td></tr>
                  <tr><td>Michael Chen</td><td>MATH201</td><td>61</td><td><span class="badge badge-warning">C</span></td><td><span class="badge badge-success">Pass</span></td></tr>
                  <tr><td>Emily Davis</td><td>PHYS101</td><td>42</td><td><span class="badge badge-danger">F</span></td><td><span class="badge badge-danger">Fail</span></td></tr>
                </tbody>
              </table>
            </div>
            <div style="display:flex;gap:.5rem;margin-top:1rem;justify-content:flex-end;">
              <button class="btn btn-success" onclick="exportReport('pdf')"><i class="fas fa-file-pdf"></i> Export PDF</button>
              <button class="btn btn-warning" onclick="exportReport('excel')"><i class="fas fa-file-excel"></i> Export Excel</button>
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
    function generateReport(){
      // UI-only: simulate render
      const el = document.getElementById('chartArea');
      el.textContent = 'Generating charts...';
      setTimeout(()=>{ el.textContent = 'Charts rendered (demo)'; }, 800);
    }
    function resetFilters(){
      const type = document.getElementById('filterType'); if (type) type.value='level';
      const dep = document.getElementById('filterDepartment'); if (dep) dep.selectedIndex=0;
      const fac = document.getElementById('filterFaculty'); if (fac) fac.selectedIndex=0;
      const lvl = document.getElementById('filterLevel'); if (lvl) lvl.selectedIndex=0;
    }
    function exportReport(type){
      alert('Exporting ' + type.toUpperCase() + ' (demo). Hook server-side export later.');
    }
  </script>
</body>
</html>