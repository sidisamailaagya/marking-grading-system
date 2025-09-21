<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta http-equiv="X-UA-Compatible" content="IE=edge" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=0, minimal-ui">
  <title>Feedback & Remarks - Lecturer | Marking & Grading System</title>
  <meta name="description" content="Add personalized feedback and view suggestions" />
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
    .grid{ display:grid; grid-template-columns: 1.2fr 1fr; gap:1.25rem; }
    .card{ background:#fff; border-radius:var(--radius); box-shadow:var(--card-shadow); padding:1.25rem; }
    .card:hover{ box-shadow:var(--card-shadow-hover); }
    .toolbar{ display:flex; gap:.5rem; flex-wrap:wrap; align-items:center; }
    .input, .select, .textarea{ padding:.65rem .8rem; border:2px solid #e2e8f0; border-radius:10px; background:#fff; }
    .textarea{ min-height:120px; width:100%; resize:vertical; }
    .input:focus, .select:focus, .textarea:focus{ outline:none; border-color:var(--primary-color); box-shadow:0 0 0 3px rgba(102,126,234,.12); }
    .btn{ padding:.68rem 1rem; border:none; border-radius:10px; font-weight:700; display:inline-flex; align-items:center; gap:.5rem; cursor:pointer; transition:var(--transition); }
    .btn-primary{ background:linear-gradient(135deg,#667eea,#5a67d8); color:#fff; }
    .btn-outline{ background:#fff; color:#4a5568; border:1px solid #e2e8f0; }
    .muted{ color:#718096; font-size:.9rem; }
    .list{ display:grid; gap:.6rem; }
    .suggest{ border:1px solid #edf2f7; border-radius:12px; padding:.75rem; }
    .suggest:hover{ border-color:#cbd5e0; }
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
          <li class="nav-item"><a href="reports-analytics.php" class="nav-link"><span class="pcoded-micon"><i class="fas fa-chart-simple"></i></span><span class="pcoded-mtext">Reports & Analytics</span></a></li>
          <li class="nav-item"><a href="feedback-remarks.php" class="nav-link active"><span class="pcoded-micon"><i class="fas fa-comments"></i></span><span class="pcoded-mtext">Feedback & Remarks</span></a></li>
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
          <h1>Feedback & Remarks</h1>
          <p>Add personalized feedback and review system suggestions</p>
        </div>
      </div>

      <div class="main container">
        <div class="grid">
          <div class="card">
            <div class="toolbar" style="margin-bottom:.75rem;">
              <div class="muted">Course:</div>
              <select id="courseSelect" class="select"></select>
              <div class="muted">Student:</div>
              <select id="studentSelect" class="select"></select>
            </div>
            <div>
              <div class="muted" style="margin-bottom:.4rem;">Feedback</div>
              <textarea id="feedback" class="textarea" placeholder="Write your remarks..."></textarea>
              <div style="margin-top:.5rem;display:flex;gap:.5rem;">
                <button class="btn btn-outline" onclick="insertTemplate('Strong performance in assignments. Keep it up!')"><i class="fas fa-plus"></i> Assignments</button>
                <button class="btn btn-outline" onclick="insertTemplate('Please improve punctuality to enhance overall performance.')"><i class="fas fa-plus"></i> Punctuality</button>
                <button class="btn btn-outline" onclick="insertTemplate('Great teamwork shown during group projects.')"><i class="fas fa-plus"></i> Teamwork</button>
              </div>
              <div style="margin-top:.75rem;display:flex;gap:.5rem;">
                <button class="btn btn-primary" onclick="saveFeedback()"><i class="fas fa-save"></i> Save Feedback</button>
                <button class="btn btn-outline" onclick="clearFeedback()"><i class="fas fa-eraser"></i> Clear</button>
              </div>
            </div>
          </div>
          <div class="card">
            <div style="font-weight:800;color:#2d3748;margin-bottom:.5rem;">Automated Suggestions (demo)</div>
            <div class="list" id="suggestions"></div>
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
      { code:'CS101', title:'Intro to Programming', students:[
        { name:'Sarah Johnson', matric:'MAT/CS/2024/001' },
        { name:'Michael Chen', matric:'MAT/CS/2024/002' },
        { name:'Emily Davis', matric:'MAT/CS/2024/003' },
      ]},
      { code:'CS201', title:'Data Structures', students:[
        { name:'David Wilson', matric:'MAT/CS/2024/004' },
        { name:'Lisa Anderson', matric:'MAT/CS/2024/005' },
      ]}
    ];

    const suggestionsData = [
      'Encourage the student to maintain consistency across all assessments.',
      'Recommend attending extra tutorial sessions for challenging topics.',
      'Praise improvements and set clear next targets to sustain momentum.',
      'Advise better time management to meet deadlines.',
      'Suggest collaboration with study groups to foster teamwork skills.'
    ];

    function init(){
      const cs = document.getElementById('courseSelect');
      courses.forEach(c=>{ const o=document.createElement('option'); o.value=c.code; o.textContent=`${c.code} — ${c.title}`; cs.appendChild(o); });
      cs.addEventListener('change', renderStudents);
      cs.value = courses[0].code;
      renderStudents();
      renderSuggestions();
    }

    function renderStudents(){
      const code = document.getElementById('courseSelect').value;
      const ss = document.getElementById('studentSelect'); ss.innerHTML='';
      const list = courses.find(c=>c.code===code)?.students || [];
      list.forEach(s=>{ const o=document.createElement('option'); o.value=s.matric; o.textContent=`${s.name} — ${s.matric}`; ss.appendChild(o); });
    }

    function renderSuggestions(){
      const holder = document.getElementById('suggestions'); holder.innerHTML='';
      suggestionsData.forEach(text=>{
        const div = document.createElement('div'); div.className='suggest';
        div.innerHTML = `<div style="font-weight:700;color:#2d3748;margin-bottom:.25rem;">Suggestion</div><div class="muted">${text}</div>`;
        div.addEventListener('click', ()=>insertTemplate(text));
        holder.appendChild(div);
      });
    }

    function insertTemplate(text){ const ta=document.getElementById('feedback'); ta.value = (ta.value?ta.value+"\n":"") + text; ta.focus(); }
    function clearFeedback(){ document.getElementById('feedback').value=''; }
    function saveFeedback(){
      const course = document.getElementById('courseSelect').value;
      const student = document.getElementById('studentSelect').value;
      const fb = document.getElementById('feedback').value.trim();
      if (!fb){ alert('Please enter feedback first.'); return; }
      alert(`Feedback saved (demo) for ${student} in ${course}.`);
      clearFeedback();
    }

    window.addEventListener('load', init);
  </script>
</body>
</html>