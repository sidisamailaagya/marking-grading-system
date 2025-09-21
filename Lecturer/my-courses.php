<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta http-equiv="X-UA-Compatible" content="IE=edge" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=0, minimal-ui">
  <title>My Courses - Lecturer | Marking & Grading System</title>
  <meta name="description" content="View and manage assigned courses and enrolled students" />
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
    .card{ background:#fff; border-radius:var(--radius); box-shadow:var(--card-shadow); padding:1.25rem; }
    .card:hover{ box-shadow:var(--card-shadow-hover); }
    .flex{ display:flex; align-items:center; }
    .between{ justify-content:space-between; }
    .muted{ color:#718096; font-size:.9rem; }
    .toolbar{ display:flex; gap:.5rem; flex-wrap:wrap; align-items:center; }
    .input, .select{ padding:.68rem .8rem; border:2px solid #e2e8f0; border-radius:10px; background:#fff; }
    .input:focus, .select:focus{ outline:none; border-color:var(--primary-color); box-shadow:0 0 0 3px rgba(102,126,234,.12); }
    .btn{ padding:.68rem 1rem; border:none; border-radius:10px; font-weight:700; display:inline-flex; align-items:center; gap:.5rem; cursor:pointer; transition:var(--transition); }
    .btn-primary{ background:linear-gradient(135deg,#667eea,#5a67d8); color:#fff; }
    .btn-outline{ background:#fff; color:#4a5568; border:1px solid #e2e8f0; }

    .courses-list{ display:grid; gap:.75rem; }
    .course-row{ display:flex; justify-content:space-between; align-items:center; border:1px solid #edf2f7; border-radius:12px; padding: .9rem; }
    .course-title{ font-weight:800; color:#2d3748; }
    .meta{ display:flex; align-items:center; gap:.75rem; color:#718096; font-size:.9rem; }
    .meta .chip{ padding:.15rem .5rem; background:#edf2f7; border-radius:999px; font-size:.8rem; font-weight:700; color:#4a5568; }
    .actions{ display:flex; gap:.5rem; }

    .panel{ border:1px solid #edf2f7; border-radius:12px; overflow:hidden; }
    .panel-head{ background:#f7fafc; padding:.8rem 1rem; font-weight:700; color:#2d3748; }
    table{ width:100%; border-collapse:collapse; }
    th, td{ padding:.75rem; border-bottom:1px solid #f1f5f9; text-align:left; }
    th{ background:#f8fafc; color:#475569; font-weight:800; }
    .status{ padding:.2rem .55rem; border-radius:999px; font-size:.75rem; font-weight:800; }
    .status.active{ background:rgba(72,187,120,.12); color:#2f855a; }
    .status.inactive{ background:rgba(245,101,101,.12); color:#9b2c2c; }
    .small{ font-size:.85rem; color:#718096; }
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
          <li class="nav-item"><a href="my-courses.php" class="nav-link active"><span class="pcoded-micon"><i class="fas fa-book"></i></span><span class="pcoded-mtext">My Courses</span></a></li>
          <li class="nav-item"><a href="enter-grades.php" class="nav-link"><span class="pcoded-micon"><i class="fas fa-pen-to-square"></i></span><span class="pcoded-mtext">Enter Grades</span></a></li>
          <li class="nav-item"><a href="student-performance.php" class="nav-link"><span class="pcoded-micon"><i class="fas fa-user-chart"></i></span><span class="pcoded-mtext">View Student Performance</span></a></li>
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
          <h1>My Courses</h1>
          <p>View assigned courses and enrolled students</p>
        </div>
      </div>

      <div class="main container">
        <div class="card">
          <div class="flex between" style="margin-bottom:.9rem;">
            <div class="toolbar">
              <input id="q" class="input" placeholder="Search by code or title..." />
              <select id="lvl" class="select">
                <option value="">All Levels</option>
                <option value="100">100 Level</option>
                <option value="200">200 Level</option>
                <option value="300">300 Level</option>
                <option value="400">400 Level</option>
              </select>
            </div>
            <div class="toolbar">
              <button class="btn btn-outline" onclick="resetFilters()"><i class="fas fa-rotate"></i> Reset</button>
            </div>
          </div>

          <div id="coursesList" class="courses-list"></div>
        </div>

        <div id="rosterPanel" class="panel" style="margin-top:1rem; display:none;">
          <div class="panel-head" id="rosterTitle">Enrolled Students</div>
          <div style="overflow-x:auto;">
            <table>
              <thead>
                <tr>
                  <th>Student</th>
                  <th>Matric No</th>
                  <th>Level</th>
                  <th>Status</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody id="rosterBody"></tbody>
            </table>
          </div>
          <div class="small" style="padding: .65rem 1rem;">Tip: Use Enter Grades to start grading tasks for this course.</div>
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
    const myCourses = [
      { code:'CS101', title:'Intro to Programming', level:[100,200], enrolled: 62 },
      { code:'CS201', title:'Data Structures', level:[200], enrolled: 48 },
      { code:'CS301', title:'Database Systems', level:[300], enrolled: 32 },
      { code:'CS401', title:'Software Engineering', level:[400], enrolled: 28 },
    ];
    const roster = {
      'CS101': [
        { name:'Sarah Johnson', matric:'MAT/CS/2024/001', level:100, status:'active' },
        { name:'Michael Chen', matric:'MAT/CS/2024/002', level:200, status:'active' },
        { name:'Emily Davis', matric:'MAT/CS/2024/003', level:200, status:'inactive' },
      ],
      'CS201': [
        { name:'David Wilson', matric:'MAT/CS/2024/004', level:200, status:'active' },
        { name:'Lisa Anderson', matric:'MAT/CS/2024/005', level:200, status:'active' },
      ],
      'CS301': [
        { name:'John Smith', matric:'MAT/CS/2024/010', level:300, status:'active' },
      ],
      'CS401': [
        { name:'Jane Lee', matric:'MAT/CS/2024/020', level:400, status:'active' },
      ],
    };

    let filtered = [...myCourses];

    function renderCourses(){
      const list = document.getElementById('coursesList');
      list.innerHTML = '';
      filtered.forEach(c => {
        const row = document.createElement('div');
        row.className = 'course-row';
        row.innerHTML = `
          <div>
            <div class="course-title">${c.code} - ${c.title}</div>
            <div class="meta">
              <span><i class="fas fa-layer-group"></i> ${c.level.join(', ')}</span>
              <span><i class="fas fa-user"></i> ${c.enrolled} enrolled</span>
            </div>
          </div>
          <div class="actions">
            <a class="btn btn-outline" href="enter-grades.php?course=${c.code}"><i class="fas fa-pen-to-square"></i> Enter Grades</a>
            <button class="btn btn-primary" onclick="openRoster('${c.code}', '${c.title}')"><i class="fas fa-users"></i> View Students</button>
          </div>`;
        list.appendChild(row);
      });
      if (!filtered.length){
        list.innerHTML = `<div class="muted">No courses match the current filters.</div>`;
      }
    }

    function openRoster(code, title){
      const panel = document.getElementById('rosterPanel');
      const head = document.getElementById('rosterTitle');
      const body = document.getElementById('rosterBody');
      head.textContent = `Enrolled Students — ${code} · ${title}`;
      body.innerHTML = '';
      (roster[code]||[]).forEach(s => {
        const tr = document.createElement('tr');
        tr.innerHTML = `
          <td>${s.name}</td>
          <td>${s.matric}</td>
          <td>${s.level}</td>
          <td><span class="status ${s.status}">${s.status==='active'?'Active':'Inactive'}</span></td>
          <td>
            <a class="btn btn-outline" href="enter-grades.php?course=${code}&matric=${encodeURIComponent(s.matric)}"><i class="fas fa-pen"></i> Grade</a>
          </td>`;
        body.appendChild(tr);
      });
      panel.style.display = 'block';
      panel.scrollIntoView({behavior:'smooth'});
    }

    function applyFilters(){
      const q = document.getElementById('q').value.trim().toLowerCase();
      const lvl = document.getElementById('lvl').value;
      filtered = myCourses.filter(c => {
        const matchQ = !q || c.code.toLowerCase().includes(q) || c.title.toLowerCase().includes(q);
        const matchL = !lvl || c.level.includes(Number(lvl));
        return matchQ && matchL;
      });
      renderCourses();
    }

    function resetFilters(){ document.getElementById('q').value=''; document.getElementById('lvl').value=''; applyFilters(); }

    document.getElementById('q').addEventListener('input', applyFilters);
    document.getElementById('lvl').addEventListener('change', applyFilters);

    window.addEventListener('load', () => { applyFilters(); });
  </script>
</body>
</html>