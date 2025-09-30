<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="utf-8" />
  <meta http-equiv="X-UA-Compatible" content="IE=edge" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=0, minimal-ui">
  <title>Feedback & Comments | Student Portal</title>
  <meta name="description" content="View personalized remarks from lecturers and system tips" />
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
      grid-template-columns: 1fr 1fr;
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

    .list {
      display: grid;
      gap: .6rem;
    }

    .item {
      border: 1px solid #edf2f7;
      border-radius: 12px;
      padding: .75rem;
    }

    .muted {
      color: #718096;
      font-size: .9rem;
    }

    .badge {
      padding: .18rem .5rem;
      border-radius: 999px;
      font-weight: 800;
      font-size: .75rem;
      display: inline-block;
    }

    .badge.info {
      background: rgba(66, 153, 225, .12);
      color: #2b6cb0;
    }

    .badge.tip {
      background: rgba(72, 187, 120, .12);
      color: #2f855a;
    }

    @media (max-width:1100px) {
      .grid {
        grid-template-columns: 1fr
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
          <li class="nav-item pcoded-menu-caption"><label>Student Menu</label></li>
          <li class="nav-item"><a href="dashboard.php" class="nav-link"><span class="pcoded-micon"><i class="fas fa-house"></i></span><span class="pcoded-mtext">Dashboard</span></a></li>
          <li class="nav-item"><a href="my-results.php" class="nav-link"><span class="pcoded-micon"><i class="fas fa-list-check"></i></span><span class="pcoded-mtext">My Results</span></a></li>
          <li class="nav-item"><a href="predicted-grade.php" class="nav-link"><span class="pcoded-micon"><i class="fas fa-crystal-ball"></i></span><span class="pcoded-mtext">Predicted Future Grade</span></a></li>
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
      <a href="#" class="b-brand">
        <h3 class="text-primary mb-0">Student Portal</h3>
      </a>
      <a href="#" class="mob-toggler"><i class="feather icon-more-vertical"></i></a>
    </div>
  </header>

  <div class="pcoded-main-container">
    <div class="pcoded-content">
      <div class="page-hero">
        <div class="container">
          <h1>Feedback & Comments</h1>
          <p>Personalized remarks from lecturers and system tips</p>
        </div>
      </div>

      <div class="main container">
        <div class="grid">
          <div class="card">
            <div style="font-weight:800;color:#2d3748;margin-bottom:.5rem;">Lecturer Remarks</div>
            <div class="list" id="lecturerList"></div>
          </div>
          <div class="card">
            <div style="font-weight:800;color:#2d3748;margin-bottom:.5rem;">System Tips</div>
            <div class="list" id="tipsList"></div>
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
    const lecturerRemarks = [{
        course: 'CS201',
        by: 'Dr. Johnson',
        date: 'Sep 15, 2025',
        text: 'Great effort on the recent project. Keep refining your code quality.'
      },
      {
        course: 'MATH201',
        by: 'Dr. Lee',
        date: 'Sep 10, 2025',
        text: 'Practice more on integration techniques to improve speed.'
      },
    ];

    const systemTips = [
      'Improve punctuality to boost final grade by up to 5%.',
      'Join a study group to enhance teamwork and collaboration.',
      'Set weekly goals and track your assignment progress.'
    ];

    function render() {
      const l = document.getElementById('lecturerList');
      l.innerHTML = '';
      lecturerRemarks.forEach(r => {
        const div = document.createElement('div');
        div.className = 'item';
        div.innerHTML = `<div style="font-weight:800;color:#2d3748;">${r.course}</div><div class="muted">${r.by} · ${r.date}</div><div style="margin-top:.35rem;">${r.text}</div>`;
        l.appendChild(div);
      });

      const t = document.getElementById('tipsList');
      t.innerHTML = '';
      systemTips.forEach(text => {
        const div = document.createElement('div');
        div.className = 'item';
        div.innerHTML = `<span class="badge tip">Tip</span> <span style="margin-left:.5rem;">${text}</span>`;
        t.appendChild(div);
      });
    }

    window.addEventListener('load', render);
  </script>
</body>

</html>