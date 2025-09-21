<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta http-equiv="X-UA-Compatible" content="IE=edge" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=0, minimal-ui">
  <title>Enter Grades - Lecturer | Marking & Grading System</title>
  <meta name="description" content="Enter academic and behavioral scores, auto-calc final grade" />
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
    .toolbar{ display:flex; gap:.5rem; flex-wrap:wrap; align-items:center; }
    .select, .input{ padding:.65rem .8rem; border:2px solid #e2e8f0; border-radius:10px; background:#fff; }
    .select:focus, .input:focus{ outline:none; border-color:var(--primary-color); box-shadow:0 0 0 3px rgba(102,126,234,.12); }
    .btn{ padding:.68rem 1rem; border:none; border-radius:10px; font-weight:700; display:inline-flex; align-items:center; gap:.5rem; cursor:pointer; transition:var(--transition); }
    .btn-primary{ background:linear-gradient(135deg,#667eea,#5a67d8); color:#fff; }
    .btn-outline{ background:#fff; color:#4a5568; border:1px solid #e2e8f0; }
    .controls{ display:flex; gap:.5rem; flex-wrap:wrap; align-items:center; }

    table{ width:100%; border-collapse:collapse; }
    th, td{ padding:.65rem; border-bottom:1px solid #f1f5f9; text-align:left; }
    th{ background:#f8fafc; color:#475569; font-weight:800; position:sticky; top:0; z-index:1; }
    .center{ text-align:center; }
    .small{ font-size:.85rem; color:#718096; }
    .badge{ padding:.18rem .5rem; border-radius:999px; font-size:.75rem; font-weight:800; }
    .badge.good{ background:rgba(72,187,120,.12); color:#2f855a; }
    .badge.warn{ background:rgba(237,137,54,.12); color:#9c4221; }
    .badge.bad{ background:rgba(245,101,101,.12); color:#9b2c2c; }
    .right{ text-align:right; }
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
          <li class="nav-item"><a href="enter-grades.php" class="nav-link active"><span class="pcoded-micon"><i class="fas fa-pen-to-square"></i></span><span class="pcoded-mtext">Enter Grades</span></a></li>
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
          <h1>Enter Grades</h1>
          <p>Input academic and behavioral scores; the system auto-calculates final grade</p>
        </div>
      </div>

      <div class="main container">
        <div class="card" style="margin-bottom:1rem;">
          <div class="toolbar">
            <div class="small">Course:</div>
            <select id="courseSelect" class="select"></select>
            <div class="small">Weights:</div>
            <div class="small">Assignments 20% · Tests 20% · Project 20% · Exam 40%</div>
            <div class="small">Behavior factor:</div>
            <select id="behaviorWeight" class="select">
              <option value="0">0% (ignore)</option>
              <option value="5">5%</option>
              <option value="10" selected>10%</option>
            </select>
            <button class="btn btn-outline" onclick="fillZeros()"><i class="fas fa-eraser"></i> Zero All</button>
            <button class="btn btn-primary" onclick="saveAll()"><i class="fas fa-save"></i> Save All</button>
          </div>
        </div>

        <div class="card" style="overflow:auto;">
          <table id="gradesTable">
            <thead>
              <tr>
                <th>Student</th>
                <th>Matric No</th>
                <th class="center">Assign. (20)</th>
                <th class="center">Test (20)</th>
                <th class="center">Project (20)</th>
                <th class="center">Exam (40)</th>
                <th class="center">Discipline</th>
                <th class="center">Punctuality</th>
                <th class="center">Teamwork</th>
                <th>Remarks</th>
                <th class="center">Final %</th>
                <th class="center">Grade</th>
              </tr>
            </thead>
            <tbody id="gradesBody"></tbody>
          </table>
          <div class="small" style="margin-top:.5rem;">Note: Final = Academics (A20+T20+P20+E40) adjusted by behavior weight. Grade scale demo: A≥70, B≥60, C≥50, D≥45, F&lt;45.</div>
        </div>
      </div>
    </div>
  </div>

  <script src="../Admin/assets/js/vendor-all.min.js"></script>
  <script src="../Admin/assets/js/plugins/bootstrap.min.js"></script>
  <script src="../Admin/assets/js/ripple.js"></script>
  <script src="../Admin/assets/js/pcoded.min.js"></script>
  <script>
    // Demo data
    const courses = [
      { code:'CS101', title:'Intro to Programming' },
      { code:'CS201', title:'Data Structures' },
      { code:'CS301', title:'Database Systems' },
    ];
    const enrollments = {
      'CS101': [
        { name:'Sarah Johnson', matric:'MAT/CS/2024/001' },
        { name:'Michael Chen', matric:'MAT/CS/2024/002' },
        { name:'Emily Davis', matric:'MAT/CS/2024/003' },
      ],
      'CS201': [
        { name:'David Wilson', matric:'MAT/CS/2024/004' },
        { name:'Lisa Anderson', matric:'MAT/CS/2024/005' },
      ],
      'CS301': [
        { name:'John Smith', matric:'MAT/CS/2024/010' },
      ],
    };

    function qparam(key){ const u=new URL(window.location.href); return u.searchParams.get(key)||''; }

    function initCourseSelect(){
      const sel = document.getElementById('courseSelect');
      courses.forEach(c=>{ const opt=document.createElement('option'); opt.value=c.code; opt.textContent=`${c.code} — ${c.title}`; sel.appendChild(opt); });
      const q = qparam('course');
      sel.value = courses.find(c=>c.code===q)?.code || courses[0].code;
      sel.addEventListener('change', renderTable);
    }

    function inputCell(cls, max){ return `<input type="number" class="input score ${cls}" min="0" max="${max}" step="0.5" value="0" style="width:90px;">`; }

    function renderTable(){
      const code = document.getElementById('courseSelect').value;
      const body = document.getElementById('gradesBody');
      body.innerHTML = '';
      (enrollments[code]||[]).forEach(s=>{
        const tr = document.createElement('tr');
        tr.innerHTML = `
          <td>${s.name}</td>
          <td>${s.matric}</td>
          <td class="center">${inputCell('a',20)}</td>
          <td class="center">${inputCell('t',20)}</td>
          <td class="center">${inputCell('p',20)}</td>
          <td class="center">${inputCell('e',40)}</td>
          <td class="center">${inputCell('bd',100)}</td>
          <td class="center">${inputCell('bp',100)}</td>
          <td class="center">${inputCell('bt',100)}</td>
          <td><input type="text" class="input remark" placeholder="Optional remarks..." style="min-width:220px;"></td>
          <td class="center final">0</td>
          <td class="center grade"><span class="badge bad">F</span></td>`;
        body.appendChild(tr);
      });
      attachRecalc();
      recalcAll();
    }

    function attachRecalc(){
      document.querySelectorAll('#gradesBody .score').forEach(inp => {
        inp.addEventListener('input', recalcAll);
      });
      document.getElementById('behaviorWeight').addEventListener('change', recalcAll);
    }

    function clamp(v, min, max){ return Math.max(min, Math.min(max, v||0)); }

    function recalcAll(){
      const bWeight = Number(document.getElementById('behaviorWeight').value)||0; // percent
      document.querySelectorAll('#gradesBody tr').forEach(tr => {
        const get = sel => clamp(Number(tr.querySelector(sel)?.value), 0, 100);
        const a = clamp(get('.a'),0,20);
        const t = clamp(get('.t'),0,20);
        const p = clamp(get('.p'),0,20);
        const e = clamp(get('.e'),0,40);
        const bd = clamp(get('.bd'),0,100);
        const bp = clamp(get('.bp'),0,100);
        const bt = clamp(get('.bt'),0,100);
        const academic = a + t + p + e; // out of 100
        const behaviorAvg = (bd + bp + bt) / 3; // 0..100
        const final = Math.round(academic * (100 - bWeight)/100 + behaviorAvg * (bWeight/100));
        const grade = gradeLetter(final);
        tr.querySelector('.final').textContent = final;
        const gcell = tr.querySelector('.grade');
        gcell.innerHTML = gradeBadge(grade);
      });
    }

    function gradeLetter(x){ if (x>=70) return 'A'; if (x>=60) return 'B'; if (x>=50) return 'C'; if (x>=45) return 'D'; return 'F'; }
    function gradeBadge(g){
      const cls = g==='A' ? 'good' : g==='B' ? 'good' : g==='C' ? 'warn' : g==='D' ? 'warn' : 'bad';
      return `<span class="badge ${cls}">${g}</span>`;
    }

    function fillZeros(){ document.querySelectorAll('#gradesBody .score').forEach(i=>i.value=0); recalcAll(); }
    function saveAll(){ alert('Grades saved (demo). Hook to backend to persist.'); }

    window.addEventListener('load', ()=>{ initCourseSelect(); renderTable(); });
  </script>
</body>
</html>