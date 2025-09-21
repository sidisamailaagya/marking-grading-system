<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="utf-8" />
  <meta http-equiv="X-UA-Compatible" content="IE=edge" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=0, minimal-ui">
  <title>Lecturer Dashboard - Marking & Grading System</title>
  <meta name="description" content="Lecturer portal for managing courses, grades, and analytics" />
  <link rel="icon" href="../Admin/assets/images/favicon.ico" type="image/x-icon">
  <link rel="stylesheet" href="../Admin/assets/css/style.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <style>
    :root {
      --primary-color: #667eea;
      --primary-dark: #5a67d8;
      --secondary-color: #764ba2;
      --success-color: #48bb78;
      --warning-color: #ed8936;
      --danger-color: #f56565;
      --info-color: #4299e1;
      --light-bg: #f8fafc;
      --card-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
      --card-shadow-hover: 0 20px 40px rgba(0, 0, 0, 0.15);
      --radius: 14px;
      --transition: all .25s ease;
    }

    * {
      box-sizing: border-box;
    }

    body {
      font-family: 'Inter', sans-serif;
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      min-height: 100vh;
    }

    .pcoded-navbar {
      background: rgba(255, 255, 255, 0.96);
      backdrop-filter: blur(10px);
      border-right: 1px solid rgba(0, 0, 0, 0.05);
      box-shadow: 0 0 30px rgba(0, 0, 0, 0.12);
    }

    .pcoded-header {
      background: rgba(255, 255, 255, 0.96);
      backdrop-filter: blur(10px);
      border-bottom: 1px solid rgba(0, 0, 0, 0.05);
      box-shadow: 0 6px 20px rgba(0, 0, 0, 0.06);
    }

    .page-hero {
      background: linear-gradient(135deg, rgba(102, 126, 234, .92), rgba(118, 75, 162, .92));
      color: #fff;
      padding: 2rem 0;
      border-radius: 0 0 26px 26px;
      margin-bottom: 1.5rem;
      position: relative;
      overflow: hidden;
    }

    .page-hero::before {
      content: '';
      position: absolute;
      inset: 0;
      opacity: .15;
      background: radial-gradient(600px 200px at 10% 10%, #fff, transparent), radial-gradient(600px 200px at 90% 80%, #fff, transparent);
    }

    .page-hero h1 {
      font-size: 2rem;
      font-weight: 800;
      margin: 0;
    }

    .page-hero p {
      opacity: .95;
      margin: .3rem 0 0;
    }

    .container {
      padding: 0 1.25rem;
    }

    .main {
      max-width: 1400px;
      margin: 0 auto;
      padding: 1.25rem;
    }

    .grid {
      display: grid;
      grid-template-columns: 2fr 1fr;
      gap: 1.25rem;
    }

    .grid-3 {
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 1.25rem;
    }

    .card {
      background: #fff;
      border-radius: var(--radius);
      box-shadow: var(--card-shadow);
      padding: 1.25rem;
    }

    .card:hover {
      box-shadow: var(--card-shadow-hover);
    }

    .stat {
      display: flex;
      align-items: center;
      gap: 1rem;
    }

    .stat .icon {
      width: 52px;
      height: 52px;
      border-radius: 12px;
      display: flex;
      align-items: center;
      justify-content: center;
      color: #fff;
      font-size: 1.25rem;
    }

    .icon.blue {
      background: linear-gradient(135deg, #667eea, #5a67d8);
    }

    .icon.green {
      background: linear-gradient(135deg, #48bb78, #38a169);
    }

    .icon.orange {
      background: linear-gradient(135deg, #ed8936, #dd6b20);
    }

    .stat .value {
      font-size: 1.8rem;
      font-weight: 800;
      color: #2d3748;
    }

    .muted {
      color: #718096;
      font-size: .9rem;
    }

    .list {
      display: grid;
      gap: .75rem;
    }

    .notif {
      display: flex;
      gap: .75rem;
      padding: .75rem;
      border-radius: 10px;
      border: 1px solid #edf2f7;
      align-items: flex-start;
    }

    .notif .badge {
      width: 36px;
      height: 36px;
      border-radius: 10px;
      display: flex;
      align-items: center;
      justify-content: center;
      color: white;
      font-size: .95rem;
    }

    .badge.warn {
      background: #ed8936;
    }

    .badge.info {
      background: #4299e1;
    }

    .badge.deadline {
      background: #f56565;
    }

    .btn {
      padding: .7rem 1rem;
      border: none;
      border-radius: 10px;
      font-weight: 700;
      display: inline-flex;
      align-items: center;
      gap: .5rem;
      cursor: pointer;
      transition: var(--transition);
    }

    .btn-primary {
      background: linear-gradient(135deg, #667eea, #5a67d8);
      color: white;
    }

    .btn-outline {
      background: #fff;
      color: #4a5568;
      border: 1px solid #e2e8f0;
    }

    .actions {
      display: flex;
      gap: .5rem;
      flex-wrap: wrap;
    }

    .courses {
      display: grid;
      gap: .6rem;
    }

    .course-row {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: .75rem;
      border: 1px solid #edf2f7;
      border-radius: 10px;
    }

    .course-meta {
      display: flex;
      gap: .6rem;
      align-items: center;
      color: #718096;
      font-size: .88rem;
    }

    @media (max-width: 1100px) {
      .grid {
        grid-template-columns: 1fr;
      }

      .grid-3 {
        grid-template-columns: 1fr;
      }
    }
  </style>
</head>

<body>
  <!-- Sidebar -->
  <nav class="pcoded-navbar menu-light">
    <div class="navbar-wrapper">
      <div class="navbar-content scroll-div">
        <ul class="nav pcoded-inner-navbar">
          <li class="nav-item pcoded-menu-caption"><label>Lecturer Menu</label></li>
          <li class="nav-item"><a href="dashboard.php" class="nav-link active"><span class="pcoded-micon"><i class="fas fa-house"></i></span><span class="pcoded-mtext">Dashboard</span></a></li>
          <li class="nav-item"><a href="my-courses.php" class="nav-link"><span class="pcoded-micon"><i class="fas fa-book"></i></span><span class="pcoded-mtext">My Courses</span></a></li>
          <li class="nav-item"><a href="enter-grades.php" class="nav-link"><span class="pcoded-micon"><i class="fas fa-pen-to-square"></i></span><span class="pcoded-mtext">Enter Grades</span></a></li>
          <li class="nav-item"><a href="student-performance.php" class="nav-link"><span class="pcoded-micon"><i class="fas fa-user-chart"></i></span><span class="pcoded-mtext">View Student Performance</span></a></li>
          <li class="nav-item"><a href="reports-analytics.php" class="nav-link"><span class="pcoded-micon"><i class="fas fa-chart-simple"></i></span><span class="pcoded-mtext">Reports & Analytics</span></a></li>
          <li class="nav-item"><a href="feedback-remarks.php" class="nav-link"><span class="pcoded-micon"><i class="fas fa-comments"></i></span><span class="pcoded-mtext">Feedback & Remarks</span></a></li>
          <li class="nav-item"><a href="profile-settings.php" class="nav-link"><span class="pcoded-micon"><i class="fas fa-user-cog"></i></span><span class="pcoded-mtext">Profile Settings</span></a></li>
          <li class="nav-item"><a href="../logout.php" class="nav-link"><span class="pcoded-micon"><i class="fas fa-sign-out-alt"></i></span><span class="pcoded-mtext">Logout</span></a></li>
        </ul>
      </div>
    </div>
  </nav>

  <!-- Header -->
  <header class="navbar pcoded-header navbar-expand-lg navbar-light">
    <div class="m-header">
      <a class="mobile-menu" id="mobile-collapse" href="#"><span></span></a>
      <a href="#" class="b-brand">
        <h3 class="text-primary mb-0">MGSi</h3>
      </a>
      <a href="#" class="mob-toggler"><i class="feather icon-more-vertical"></i></a>
    </div>
  </header>

  <div class="pcoded-main-container">
    <div class="pcoded-content">
      <div class="page-hero">
        <div class="container">
          <h1>Welcome back, Dr. Johnson</h1>
          <p>Manage your courses, grading tasks, and track student performance</p>
        </div>
      </div>

      <div class="main container">
        <!-- Stats -->
        <div class="grid-3">
          <div class="card stat">
            <div class="icon blue"><i class="fas fa-users"></i></div>
            <div>
              <div class="value" id="statStudents">0</div>
              <div class="muted">Students in Classes</div>
            </div>
          </div>
          <div class="card stat">
            <div class="icon orange"><i class="fas fa-tasks"></i></div>
            <div>
              <div class="value" id="statPending">0</div>
              <div class="muted">Pending Grading Tasks</div>
            </div>
          </div>
          <div class="card stat">
            <div class="icon green"><i class="fas fa-gauge-high"></i></div>
            <div>
              <div class="value" id="statAverage">0%</div>
              <div class="muted">Average Performance</div>
            </div>
          </div>
        </div>

        <div class="grid" style="margin-top:1.25rem;">
          <div class="card">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:.75rem;">
              <h3 style="margin:0;color:#2d3748;">My Courses</h3>
              <div class="actions">
                <a class="btn btn-outline" href="my-courses.php"><i class="fas fa-arrow-right"></i> View All</a>
              </div>
            </div>
            <div class="courses" id="coursesList"></div>
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
    const lecturer = {
      name: 'Dr. Johnson'
    };
    const stats = {
      students: 142,
      pending: 8,
      average: 78.6
    };
    const myCourses = [{
        code: 'CS101',
        title: 'Intro to Programming',
        level: [100, 200],
        enrolled: 62,
        pending: 3
      },
      {
        code: 'CS201',
        title: 'Data Structures',
        level: [200],
        enrolled: 48,
        pending: 2
      },
      {
        code: 'CS301',
        title: 'Database Systems',
        level: [300],
        enrolled: 32,
        pending: 3
      },
    ];
    const notifications = [{
        type: 'deadline',
        text: 'CS201: Test 1 grading due in 2 days',
        time: 'Due: Sep 20, 2025'
      },
      {
        type: 'warn',
        text: 'Low submissions in CS301 Project 1',
        time: '3 hours ago'
      },
      {
        type: 'info',
        text: 'New student enrolled in CS101',
        time: 'Today, 09:12 AM'
      },
    ];

    function animateValue(el, to, suffix = '') {
      const start = 0;
      const dur = 800;
      const t0 = performance.now();
      const step = (t) => {
        const p = Math.min((t - t0) / dur, 1);
        el.textContent = (start + (to - start) * p).toFixed(suffix ? 1 : 0) + suffix;
        if (p < 1) requestAnimationFrame(step);
      };
      requestAnimationFrame(step);
    }

    function render() {
      document.querySelector('.page-hero h1').textContent = `Welcome back, ${lecturer.name}`;
      animateValue(document.getElementById('statStudents'), stats.students);
      animateValue(document.getElementById('statPending'), stats.pending);
      animateValue(document.getElementById('statAverage'), stats.average, '%');
      const list = document.getElementById('coursesList');
      list.innerHTML = '';
      myCourses.forEach(c => {
        const row = document.createElement('div');
        row.className = 'course-row';
        row.innerHTML = `
          <div>
            <div style="font-weight:800;color:#2d3748;">${c.code} - ${c.title}</div>
            <div class="course-meta"><span><i class="fas fa-layer-group"></i> ${c.level.join(', ')}</span><span><i class="fas fa-user"></i> ${c.enrolled} students</span></div>
          </div>
          <div class="actions">
            <a class="btn btn-outline" href="enter-grades.php?course=${c.code}"><i class="fas fa-pen-to-square"></i> Enter Grades</a>
            <a class="btn btn-primary" href="student-performance.php?course=${c.code}"><i class="fas fa-chart-line"></i> Performance</a>
          </div>`;
        list.appendChild(row);
      });
      const nList = document.getElementById('notifList');
      nList.innerHTML = '';
      notifications.forEach(n => {
        const bClass = n.type === 'deadline' ? 'deadline' : (n.type === 'warn' ? 'warn' : 'info');
        const icon = n.type === 'deadline' ? 'fa-clock' : (n.type === 'warn' ? 'fa-triangle-exclamation' : 'fa-bell');
        const item = document.createElement('div');
        item.className = 'notif';
        item.innerHTML = `
          <div class="badge ${bClass}"><i class="fas ${icon}"></i></div>
          <div>
            <div style="font-weight:600;color:#2d3748;margin-bottom:.2rem">${n.text}</div>
            <div class="muted">${n.time}</div>
          </div>`;
        nList.appendChild(item);
      });
    }

    window.addEventListener('load', render);
  </script>
</body>

</html>