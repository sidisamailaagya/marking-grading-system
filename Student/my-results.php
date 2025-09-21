<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta http-equiv="X-UA-Compatible" content="IE=edge" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=0, minimal-ui">
  <title>My Results | Student Portal</title>
  <meta name="description" content="View grades and behavioral breakdown by subject" />
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
    .card{ background:#fff; border-radius:var(--radius); box-shadow:var(--card-shadow); padding:1.25rem; }
    .card:hover{ box-shadow:var(--card-shadow-hover); }
    .toolbar{ display:flex; gap:.5rem; flex-wrap:wrap; align-items:center; }
    .muted{ color:#64748b; font-weight:600; }
    .input, .select{ padding:.65rem .8rem; border:2px solid #e2e8f0; border-radius:10px; background:#fff; }
    .input:focus, .select:focus{ outline:none; border-color:var(--primary-color); box-shadow:0 0 0 3px rgba(102,126,234,.12); }
    .btn{ padding:.55rem .9rem; border:none; border-radius:10px; font-weight:700; display:inline-flex; align-items:center; gap:.5rem; cursor:pointer; transition:var(--transition); }
    .btn-primary{ background:linear-gradient(135deg,#667eea,#5a67d8); color:#fff; }
    .btn-outline{ background:#fff; color:#4a5568; border:1px solid #e2e8f0; }
    .btn-ghost{ background:transparent; color:#4a5568; border:1px dashed #e2e8f0; }
    .btn[disabled]{ opacity:.6; cursor:not-allowed; }
    table{ width:100%; border-collapse:collapse; }
    th, td{ padding:.65rem; border-bottom:1px solid #f1f5f9; text-align:left; }
    th{ background:#f8fafc; color:#475569; font-weight:800; position:sticky; top:0; z-index:1; }
    .badge{ padding:.18rem .5rem; border-radius:999px; font-size:.75rem; font-weight:800; }
    .badge.good{ background:rgba(72,187,120,.12); color:#2f855a; }
    .badge.warn{ background:rgba(237,137,54,.12); color:#9c4221; }
    .badge.bad{ background:rgba(245,101,101,.12); color:#9b2c2c; }
    .badge.info{ background:rgba(66,153,225,.12); color:#2b6cb0; }
    .section-title{ display:flex; align-items:center; gap:.5rem; font-weight:800; color:#334155; margin-bottom:.75rem; }
    .section-desc{ color:#64748b; margin-bottom: .5rem; }
    .flex{ display:flex; gap:.5rem; align-items:center; flex-wrap:wrap; }
    .right{ text-align:right; }
    .nowrap{ white-space:nowrap; }
    .num{ text-align:right; font-variant-numeric: tabular-nums; }
    .empty{ color:#94a3b8; font-style:italic; padding:.5rem 0; }
    .cards-grid{ display:grid; grid-template-columns: 1fr; gap:1rem; }
    @media (min-width: 1100px){ .cards-grid{ grid-template-columns: 1fr; } }
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
          <li class="nav-item"><a href="my-results.php" class="nav-link active"><span class="pcoded-micon"><i class="fas fa-list-check"></i></span><span class="pcoded-mtext">My Results</span></a></li>
          <li class="nav-item"><a href="predicted-grade.php" class="nav-link"><span class="pcoded-micon"><i class="fas fa-crystal-ball"></i></span><span class="pcoded-mtext">Predicted Future Grade</span></a></li>
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
          <h1>My Results & Course Registration</h1>
          <p>Register for department-assigned courses and view results for courses you’ve registered and been graded on</p>
        </div>
      </div>

      <div class="main container">
        <div class="cards-grid">

          <!-- Course Registration -->
          <div class="card">
            <div class="section-title"><i class="fas fa-book-open"></i> Course Registration</div>
            <div class="section-desc">Courses available for your faculty, department and level</div>
            <div style="overflow:auto;">
              <table aria-label="Available courses to register">
                <thead>
                  <tr>
                    <th>Course</th>
                    <th>Semester</th>
                    <th class="num">Credits</th>
                    <th class="right">Action</th>
                  </tr>
                </thead>
                <tbody id="availableCoursesBody">
                  <tr><td colspan="4" class="empty">Loading courses...</td></tr>
                </tbody>
              </table>
            </div>
          </div>

          <!-- Registered Courses -->
          <div class="card">
            <div class="section-title"><i class="fas fa-clipboard-check"></i> Registered Courses</div>
            <div class="section-desc">Manage registered courses. Results only show when graded by your lecturer.</div>
            <div style="overflow:auto;">
              <table aria-label="Registered courses list">
                <thead>
                  <tr>
                    <th>Course</th>
                    <th>Semester</th>
                    <th>Status</th>
                    <th class="right">Action</th>
                  </tr>
                </thead>
                <tbody id="registeredCoursesBody">
                  <tr><td colspan="4" class="empty">No registered courses yet.</td></tr>
                </tbody>
              </table>
            </div>
          </div>

          <!-- Results -->
          <div class="card" style="margin-bottom:1rem;">
            <div class="toolbar">
              <div class="muted">Semester:</div>
              <select id="semester" class="select">
                <option>2024/2025 - First</option>
                <option selected>2024/2025 - Second</option>
                <option>2025/2026 - First</option>
              </select>
              <div class="muted">Search:</div>
              <input id="q" class="input" placeholder="Course code or title" />
              <button class="btn btn-outline" onclick="applyFilters()"><i class="fas fa-filter"></i> Filter</button>
              <button class="btn btn-primary" onclick="exportResults()"><i class="fas fa-file-export"></i> Export</button>
            </div>
          </div>

          <div class="card" style="overflow:auto;">
            <div class="section-title"><i class="fas fa-graduation-cap"></i> My Results</div>
            <div class="section-desc">Only registered and graded courses appear below.</div>
            <table aria-label="My results table">
              <thead>
                <tr>
                  <th>Course</th>
                  <th>Semester</th>
                  <th class="num">Assign (20)</th>
                  <th class="num">Test (20)</th>
                  <th class="num">Project (20)</th>
                  <th class="num">Exam (40)</th>
                  <th class="num">Punctuality</th>
                  <th class="num">Teamwork</th>
                  <th>Lecturer Remark</th>
                  <th class="num">Final %</th>
                  <th>Grade</th>
                </tr>
              </thead>
              <tbody id="resultsBody">
                <tr><td colspan="11" class="empty">No graded results yet.</td></tr>
              </tbody>
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
    // Simulated logged-in student profile (replace with PHP session output)
    const student = {
      id: 1,
      name: 'Sarah',
      facultyId: 'ENG',
      departmentId: 'CSE',
      level: 200
    };

    // Demo course catalog: faculty, department, and level based
    const catalog = [
      { id: 101, code:'CS201', title:'Data Structures', sem:'2024/2025 - Second', credits:3, facultyId:'ENG', departmentId:'CSE', level:200 },
      { id: 102, code:'CS202', title:'Discrete Mathematics', sem:'2024/2025 - Second', credits:2, facultyId:'ENG', departmentId:'CSE', level:200 },
      { id: 103, code:'CS203', title:'Computer Architecture', sem:'2025/2026 - First', credits:3, facultyId:'ENG', departmentId:'CSE', level:200 },
      { id: 104, code:'MTH205', title:'Linear Algebra', sem:'2024/2025 - Second', credits:3, facultyId:'SCI', departmentId:'MTH', level:200 }, // different faculty
      { id: 105, code:'EEE101', title:'Intro to Electrical Eng', sem:'2024/2025 - First', credits:3, facultyId:'ENG', departmentId:'EEE', level:100 } // different dept/level
    ];

    // Demo grades keyed by courseId (only registered and graded will display)
    const demoGrades = new Map([
      [101, { A:16, T:14, P:18, E:30, punctuality:75, teamwork:80, remark:'Solid understanding', final:78, grade:'B' }],
      [103, { A:17, T:18, P:19, E:35, punctuality:92, teamwork:88, remark:'Excellent work', final:89, grade:'A' }]
      // 102 intentionally has no grade yet
    ]);

    // App state
    const state = {
      availableCourses: [],
      registrations: new Set(), // courseId set
      grades: demoGrades,
      filteredResults: []
    };

    // Pretend API layer: replace with real fetch() when backend is ready
    const Api = {
      async fetchAvailableCourses(profile){
        // In real app: GET /api/student/available-courses?facultyId=&departmentId=&level=
        return catalog.filter(c =>
          c.facultyId === profile.facultyId &&
          c.departmentId === profile.departmentId &&
          c.level === profile.level
        );
      },
      async fetchRegistrations(studentId){
        // In real app: GET /api/student/registrations
        return []; // start empty by default
      },
      async registerCourse(studentId, courseId){
        // In real app: POST /api/student/registrations { courseId }
        return { ok:true };
      },
      async unregisterCourse(studentId, courseId){
        // In real app: DELETE /api/student/registrations/{courseId}
        return { ok:true };
      },
      async fetchGrades(studentId){
        // In real app: GET /api/student/results
        return demoGrades; // already set above
      }
    };

    // Utilities
    function gradeBadge(g){
      const cls = g==='A'||g==='B' ? 'good' : (g==='C'||g==='D') ? 'warn' : 'bad';
      return `<span class="badge ${cls}">${g}</span>`;
    }
    function statusBadge(txt, kind='info'){ return `<span class="badge ${kind}">${txt}</span>`; }

    // Rendering
    function renderAvailableCourses(){
      const body = document.getElementById('availableCoursesBody');
      body.innerHTML = '';
      const list = state.availableCourses.filter(c => !state.registrations.has(c.id));
      if (!list.length){
        body.innerHTML = '<tr><td colspan="4" class="empty">No available courses to register.</td></tr>';
        return;
      }
      for (const c of list){
        const tr = document.createElement('tr');
        tr.innerHTML = `
          <td><strong>${c.code}</strong> — ${c.title}</td>
          <td>${c.sem}</td>
          <td class="num">${c.credits}</td>
          <td class="right">
            <button class="btn btn-primary" onclick="onRegister(${c.id})"><i class="fas fa-plus"></i> Register</button>
          </td>`;
        body.appendChild(tr);
      }
    }

    function renderRegisteredCourses(){
      const body = document.getElementById('registeredCoursesBody');
      body.innerHTML = '';
      const registered = [...state.registrations].map(id => state.availableCourses.find(c => c.id === id)).filter(Boolean);
      if (!registered.length){
        body.innerHTML = '<tr><td colspan="4" class="empty">No registered courses yet.</td></tr>';
        return;
      }
      for (const c of registered){
        const graded = state.grades.has(c.id);
        const tr = document.createElement('tr');
        tr.innerHTML = `
          <td><strong>${c.code}</strong> — ${c.title}</td>
          <td>${c.sem}</td>
          <td>${graded ? statusBadge('Graded','good') : statusBadge('Awaiting grading','info')}</td>
          <td class="right">
            <button class="btn btn-outline" onclick="onUnregister(${c.id})"><i class="fas fa-times"></i> Unregister</button>
          </td>`;
        body.appendChild(tr);
      }
    }

    function deriveResults(){
      // Only registered AND graded courses
      const rows = [];
      for (const courseId of state.registrations){
        if (!state.grades.has(courseId)) continue;
        const c = state.availableCourses.find(x => x.id === courseId);
        if (!c) continue;
        const g = state.grades.get(courseId);
        rows.push({
          id: courseId, code: c.code, title: c.title, sem: c.sem,
          A: g.A, T: g.T, P: g.P, E: g.E,
          punctuality: g.punctuality, teamwork: g.teamwork,
          remark: g.remark, final: g.final, grade: g.grade
        });
      }
      return rows;
    }

    function renderResultsTable(){
      const body = document.getElementById('resultsBody');
      body.innerHTML = '';
      const rows = state.filteredResults;
      if (!rows.length){
        body.innerHTML = '<tr><td colspan="11" class="empty">No graded results yet.</td></tr>';
        return;
      }
      for (const r of rows){
        const tr = document.createElement('tr');
        tr.innerHTML = `
          <td><strong>${r.code}</strong> — ${r.title}</td>
          <td>${r.sem}</td>
          <td class="num">${r.A}</td>
          <td class="num">${r.T}</td>
          <td class="num">${r.P}</td>
          <td class="num">${r.E}</td>
          <td class="num">${r.punctuality}%</td>
          <td class="num">${r.teamwork}%</td>
          <td>${r.remark}</td>
          <td class="num">${r.final}%</td>
          <td>${gradeBadge(r.grade)}</td>`;
        body.appendChild(tr);
      }
    }

    // Actions
    async function onRegister(courseId){
      const btns = document.querySelectorAll(`button[onclick="onRegister(${courseId})"]`);
      btns.forEach(b => b.disabled = true);
      try{
        const res = await Api.registerCourse(student.id, courseId);
        if (res && res.ok){
          state.registrations.add(courseId);
          refreshAll();
        }
      } finally {
        btns.forEach(b => b.disabled = false);
      }
    }

    async function onUnregister(courseId){
      const btns = document.querySelectorAll(`button[onclick="onUnregister(${courseId})"]`);
      btns.forEach(b => b.disabled = true);
      try{
        const res = await Api.unregisterCourse(student.id, courseId);
        if (res && res.ok){
          state.registrations.delete(courseId);
          refreshAll();
        }
      } finally {
        btns.forEach(b => b.disabled = false);
      }
    }

    // Filtering
    function applyFilters(){
      const sem = document.getElementById('semester').value;
      const q = document.getElementById('q').value.trim().toLowerCase();
      const all = deriveResults();
      state.filteredResults = all.filter(r => {
        const mSem = !sem || r.sem === sem;
        const mQ = !q || r.code.toLowerCase().includes(q) || r.title.toLowerCase().includes(q);
        return mSem && mQ;
      });
      renderResultsTable();
    }

    function refreshAll(){
      renderAvailableCourses();
      renderRegisteredCourses();
      applyFilters();
    }

    // Init
    window.addEventListener('load', async () => {
      state.availableCourses = await Api.fetchAvailableCourses(student);
      const existing = await Api.fetchRegistrations(student.id);
      state.registrations = new Set(existing.map(x => x.courseId ?? x));
      // Optionally preload a sample registration for demo:
      // state.registrations.add(101);
      await Api.fetchGrades(student.id); // grades already loaded into state.grades
      refreshAll();
    });

    function exportResults(){
      alert('Exporting results (demo). Hook to backend to generate PDF/Excel for registered & graded courses.');
    }
  </script>
</body>
</html>