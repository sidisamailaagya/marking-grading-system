<!DOCTYPE html>
<html lang="en">
<head>
  <title>User Management - Marking & Grading System</title>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=0, minimal-ui">
  <meta http-equiv="X-UA-Compatible" content="IE=edge" />
  <meta name="description" content="Manage system users, roles, and access" />
  <meta name="keywords" content="users, roles, reset password, activate, deactivate">
  <meta name="author" content="Marking & Grading System" />
  <link rel="icon" href="assets/images/favicon.ico" type="image/x-icon">
  <link rel="stylesheet" href="assets/css/style.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <style>
    :root{--primary-color:#667eea;--primary-dark:#5a67d8;--secondary-color:#764ba2;--success-color:#48bb78;--warning-color:#ed8936;--danger-color:#f56565;--info-color:#4299e1;--light-bg:#f8fafc;--card-shadow:0 10px 25px rgba(0,0,0,.1);--card-shadow-hover:0 20px 40px rgba(0,0,0,.15);--border-radius:12px;--transition:all .3s cubic-bezier(.4,0,.2,1)}*{box-sizing:border-box;margin:0;padding:0}body{font-family:'Inter',sans-serif;background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);min-height:100vh;overflow-x:hidden}
    .pcoded-navbar{background:rgba(255,255,255,.95);backdrop-filter:blur(10px);border-right:1px solid rgba(255,255,255,.2);box-shadow:0 0 30px rgba(0,0,0,.1)}.nav-link{transition:var(--transition);border-radius:8px;margin:2px 8px}.nav-link:hover{background:linear-gradient(135deg,var(--primary-color),var(--primary-dark));color:#fff !important;transform:translateX(5px)}.nav-link.active{background:linear-gradient(135deg,var(--primary-color),var(--primary-dark));color:#fff !important}
    .pcoded-header{background:rgba(255,255,255,.95);backdrop-filter:blur(10px);border-bottom:1px solid rgba(255,255,255,.2);box-shadow:0 2px 20px rgba(0,0,0,.1)}
    .page-header{background:linear-gradient(135deg,rgba(102,126,234,.9),rgba(118,75,162,.9));color:#fff;padding:2rem 0;margin-bottom:2rem;border-radius:0 0 30px 30px;position:relative;overflow:hidden}
    .page-header::before{content:'';position:absolute;inset:0;background:url('data:image/svg+xml,<svg xmlns=\"http://www.w3.org/2000/svg\" viewBox=\"0 0 100 100\"><defs><pattern id=\"grain\" width=\"100\" height=\"100\" patternUnits=\"userSpaceOnUse\"><circle cx=\"25\" cy=\"25\" r=\"1\" fill=\"white\" opacity=\"0.1\"/><circle cx=\"75\" cy=\"75\" r=\"1\" fill=\"white\" opacity=\"0.1\"/><circle cx=\"50\" cy=\"10\" r=\"1\" fill=\"white\" opacity=\"0.1\"/></pattern></defs><rect width=\"100\" height=\"100\" fill=\"url(%23grain)\"/></svg>');animation:float 20s ease-in-out infinite}
    @keyframes float{0%,100%{transform:translateY(0)}50%{transform:translateY(-10px)}}.page-content{text-align:center;position:relative;z-index:2}.page-title{font-size:2.5rem;font-weight:700;margin-bottom:.5rem}.page-subtitle{font-size:1.1rem;opacity:.9}
    .main{padding:2rem;max-width:1400px;margin:0 auto}
    .action-bar{background:white;border-radius:var(--border-radius);padding:1.5rem;margin-bottom:2rem;box-shadow:var(--card-shadow);display:flex;justify-content:space-between;align-items:center;gap:1rem;flex-wrap:wrap}
    .search{position:relative;flex:1;max-width:520px}
    .search input{width:100%;padding:.8rem 1rem .8rem 3rem;border:2px solid #e2e8f0;border-radius:10px;background:#f8fafc}
    .search i{position:absolute;left:1rem;top:50%;transform:translateY(-50%);color:#64748b}
    .select,.input{padding:.7rem;border:2px solid #e2e8f0;border-radius:8px;background:white}
    .btn{padding:.7rem 1rem;border:none;border-radius:10px;font-weight:600;display:inline-flex;align-items:center;gap:.5rem;cursor:pointer}
    .btn-primary{background:linear-gradient(135deg,var(--primary-color),var(--primary-dark));color:white}
    .btn-secondary{background:#e2e8f0;color:#334155}
    .btn-danger{background:linear-gradient(135deg,var(--danger-color),#e53e3e);color:white}
    .card{background:white;border-radius:var(--border-radius);box-shadow:var(--card-shadow);overflow:hidden}
    table{width:100%;border-collapse:collapse}
    th,td{padding:1rem;border-bottom:1px solid #eef2f7;text-align:left}
    th{background:#f8fafc;color:#475569;font-weight:700}
    .status{padding:.25rem .55rem;border-radius:999px;font-size:.75rem;font-weight:700}
    .status.active{background:rgba(72,187,120,.12);color:#2f855a}
    .status.inactive{background:rgba(245,101,101,.12);color:#9b2c2c}
    .btn-sm{width:36px;height:36px;border-radius:8px;border:none;cursor:pointer;display:flex;align-items:center;justify-content:center}
    .btn-edit{background:rgba(66,153,225,.1);color:#3182ce}
    .btn-edit:hover{background:#3182ce;color:#fff}
    .btn-reset{background:rgba(102,126,234,.1);color:#5a67d8}
    .btn-reset:hover{background:#5a67d8;color:#fff}
    .btn-toggle{background:rgba(237,137,54,.1);color:#dd6b20}
    .btn-toggle:hover{background:#dd6b20;color:#fff}
    @media (max-width:768px){.main{padding:1rem}.action-bar{flex-direction:column;align-items:stretch}th,td{padding:.6rem}}
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
        <li class="nav-item"><a href="reports-analytics.php" class="nav-link"><span class="pcoded-micon"><i class="fas fa-chart-pie"></i></span><span class="pcoded-mtext">Reports & Analytics</span></a></li>
        <li class="nav-item"><a href="user-management.php" class="nav-link active"><span class="pcoded-micon"><i class="fas fa-users-cog"></i></span><span class="pcoded-mtext">User Management</span></a></li>
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
        <h1 class="page-title">User Management</h1>
        <p class="page-subtitle">Reset passwords and activate/deactivate accounts</p>
      </div></div></div>

      <div class="main">
        <div class="action-bar">
          <div class="search">
            <i class="fas fa-search"></i>
            <input id="search" type="text" placeholder="Search by name, email, or role...">
          </div>
          <div style="display:flex;gap:.5rem;align-items:center;flex-wrap:wrap;">
            <select id="roleFilter" class="select"><option value="">All Roles</option><option>Admin</option><option>Lecturer</option><option>Student</option></select>
            <select id="statusFilter" class="select"><option value="">All Status</option><option value="active">Active</option><option value="inactive">Inactive</option></select>
            <button class="btn btn-primary" onclick="openAddUser()"><i class="fas fa-user-plus"></i> Add User</button>
          </div>
        </div>

        <div class="card">
          <table>
            <thead>
              <tr><th>User</th><th>Email</th><th>Role</th><th>Status</th><th>Last Login</th><th>Actions</th></tr>
            </thead>
            <tbody id="usersBody"></tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

  <!-- Modals -->
  <div id="userModal" class="modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);backdrop-filter:blur(3px);align-items:center;justify-content:center;z-index:1000;">
    <div class="card" style="max-width:520px;width:92%;padding:1.25rem;">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:.75rem;">
        <div style="font-weight:800;color:#334155;font-size:1.2rem;" id="userModalTitle">Add User</div>
        <button onclick="closeUserModal()" class="btn btn-secondary" style="padding:.3rem .6rem;">&times;</button>
      </div>
      <div style="display:grid;gap:.75rem;">
        <input id="uName" class="input" placeholder="Full Name">
        <input id="uEmail" class="input" placeholder="Email">
        <select id="uRole" class="select"><option>Admin</option><option>Lecturer</option><option>Student</option></select>
        <select id="uStatus" class="select"><option value="active">Active</option><option value="inactive">Inactive</option></select>
      </div>
      <div style="display:flex;gap:.5rem;justify-content:flex-end;margin-top:1rem;">
        <button class="btn btn-secondary" onclick="closeUserModal()">Cancel</button>
        <button class="btn btn-primary" onclick="saveUser()">Save</button>
      </div>
    </div>
  </div>

  <script src="assets/js/vendor-all.min.js"></script>
  <script src="assets/js/plugins/bootstrap.min.js"></script>
  <script src="assets/js/ripple.js"></script>
  <script src="assets/js/pcoded.min.js"></script>
  <script>
    const users = [
      {name:'Sarah Johnson', email:'sarah.johnson@university.edu', role:'Admin', status:'active', lastLogin:'2025-09-10 09:12'},
      {name:'Michael Chen', email:'michael.chen@university.edu', role:'Lecturer', status:'active', lastLogin:'2025-09-11 14:35'},
      {name:'Emily Davis', email:'emily.davis@university.edu', role:'Student', status:'inactive', lastLogin:'2025-08-29 08:01'},
    ];
    let filtered = [...users];
    let editIndex = null;

    function render(){
      const body = document.getElementById('usersBody'); body.innerHTML='';
      filtered.forEach((u,i)=>{
        const tr = document.createElement('tr');
        tr.innerHTML = `
          <td><strong>${esc(u.name)}</strong></td>
          <td>${esc(u.email)}</td>
          <td>${esc(u.role)}</td>
          <td><span class="status ${u.status}">${u.status==='active'?'Active':'Inactive'}</span></td>
          <td>${esc(u.lastLogin)}</td>
          <td style="display:flex;gap:.35rem;">
            <button class="btn-sm btn-reset" title="Reset Password" onclick="resetPassword(${i})"><i class="fas fa-key"></i></button>
            <button class="btn-sm btn-toggle" title="Toggle Status" onclick="toggleStatus(${i})"><i class="fas fa-power-off"></i></button>
            <button class="btn-sm btn-edit" title="Edit" onclick="openEdit(${i})"><i class="fas fa-edit"></i></button>
          </td>`;
        body.appendChild(tr);
      });
    }
    function esc(s){return String(s??'').replace(/[&<>"']/g,c=>({"&":"&amp;","<":"&lt;",">":"&gt;","\"":"&quot;"}[c]));}
    function applyFilters(){
      const q = document.getElementById('search').value.trim().toLowerCase();
      const role = document.getElementById('roleFilter').value;
      const status = document.getElementById('statusFilter').value;
      filtered = users.filter(u => {
        const matchQ = !q || u.name.toLowerCase().includes(q) || u.email.toLowerCase().includes(q) || u.role.toLowerCase().includes(q);
        const matchR = !role || u.role === role;
        const matchS = !status || u.status === status;
        return matchQ && matchR && matchS;
      });
      render();
    }
    document.getElementById('search').addEventListener('input', applyFilters);
    document.getElementById('roleFilter').addEventListener('change', applyFilters);
    document.getElementById('statusFilter').addEventListener('change', applyFilters);

    function resetPassword(i){ alert('Password reset link sent to '+ users[i].email + ' (demo).'); }
    function toggleStatus(i){ users[i].status = users[i].status==='active'?'inactive':'active'; applyFilters(); }
    function openAddUser(){ editIndex=null; document.getElementById('userModalTitle').textContent='Add User'; document.getElementById('uName').value=''; document.getElementById('uEmail').value=''; document.getElementById('uRole').value='Admin'; document.getElementById('uStatus').value='active'; openUserModal(); }
    function openEdit(i){ editIndex=i; document.getElementById('userModalTitle').textContent='Edit User'; document.getElementById('uName').value=users[i].name; document.getElementById('uEmail').value=users[i].email; document.getElementById('uRole').value=users[i].role; document.getElementById('uStatus').value=users[i].status; openUserModal(); }
    function openUserModal(){ document.getElementById('userModal').style.display='flex'; }
    function closeUserModal(){ document.getElementById('userModal').style.display='none'; }
    function saveUser(){
      const name = document.getElementById('uName').value.trim();
      const email = document.getElementById('uEmail').value.trim();
      const role = document.getElementById('uRole').value; const status = document.getElementById('uStatus').value;
      if (!name || !email){ alert('Name and email are required'); return; }
      const payload = {name, email, role, status, lastLogin: new Date().toISOString().slice(0,16).replace('T',' ')};
      if (editIndex===null) users.push(payload); else users[editIndex]=payload;
      closeUserModal(); applyFilters();
    }

    window.addEventListener('load', ()=>{ applyFilters(); });
  </script>
</body>
</html>