<?php
// ─── DDB Connection ───────────────────────────────────────────────
$host = getenv('DB_HOST')     ?: 'mysql';
$db   = getenv('DB_NAME')     ?: 'cruddb';
$user = getenv('DB_USER')     ?: 'root';
$pass = getenv('DB_PASSWORD') ?: '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Auto-create table
    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        email VARCHAR(150) NOT NULL UNIQUE,
        role VARCHAR(50) DEFAULT 'User',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
} catch (PDOException $e) {
    die(json_encode(['error' => $e->getMessage()]));
}

$msg = '';
$edit = null;

// ─── CRUD Operations ─────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name  = trim($_POST['name']  ?? '');
    $email = trim($_POST['email'] ?? '');
    $role  = trim($_POST['role']  ?? 'User');
    $id    = intval($_POST['id']  ?? 0);

    if ($id) {
        $stmt = $pdo->prepare("UPDATE users SET name=?, email=?, role=? WHERE id=?");
        $stmt->execute([$name, $email, $role, $id]);
        $msg = "✅ User updated successfully!";
    } else {
        $stmt = $pdo->prepare("INSERT INTO users (name, email, role) VALUES (?, ?, ?)");
        $stmt->execute([$name, $email, $role]);
        $msg = "✅ User created successfully!";
    }
}

if (isset($_GET['delete'])) {
    $pdo->prepare("DELETE FROM users WHERE id=?")->execute([intval($_GET['delete'])]);
    header("Location: index.php?msg=deleted");
    exit;
}

if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id=?");
    $stmt->execute([intval($_GET['edit'])]);
    $edit = $stmt->fetch(PDO::FETCH_ASSOC);
}

if (isset($_GET['msg']) && $_GET['msg'] === 'deleted') {
    $msg = "🗑️ User deleted successfully!";
}

$users = $pdo->query("SELECT * FROM users ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
$total = count($users);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>PHP CRUD · ArgoCD Demo</title>
<link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;700&family=Syne:wght@400;700;800&display=swap" rel="stylesheet">
<style>
  :root {
    --bg: #0a0a0f;
    --surface: #111118;
    --card: #16161f;
    --border: #2a2a3a;
    --accent: #6c63ff;
    --accent2: #ff6584;
    --green: #00f5a0;
    --text: #e8e8f0;
    --muted: #6b6b80;
    --mono: 'JetBrains Mono', monospace;
    --sans: 'Syne', sans-serif;
  }

  * { margin: 0; padding: 0; box-sizing: border-box; }

  body {
    background: var(--bg);
    color: var(--text);
    font-family: var(--sans);
    min-height: 100vh;
    background-image:
      radial-gradient(ellipse 80% 50% at 20% -10%, rgba(108,99,255,.15) 0%, transparent 60%),
      radial-gradient(ellipse 60% 40% at 80% 110%, rgba(255,101,132,.10) 0%, transparent 60%);
  }

  header {
    border-bottom: 1px solid var(--border);
    padding: 1.2rem 2rem;
    display: flex;
    align-items: center;
    gap: 1rem;
    background: rgba(17,17,24,.8);
    backdrop-filter: blur(12px);
    position: sticky; top: 0; z-index: 100;
  }

  .logo-badge {
    background: var(--accent);
    color: #fff;
    font-family: var(--mono);
    font-size: .65rem;
    font-weight: 700;
    padding: .3rem .6rem;
    border-radius: 6px;
    letter-spacing: .08em;
  }

  header h1 { font-size: 1.1rem; font-weight: 700; letter-spacing: -.02em; }
  header span { font-family: var(--mono); font-size: .75rem; color: var(--muted); margin-left: auto; }

  .pipeline-bar {
    display: flex;
    align-items: center;
    gap: 0;
    padding: .9rem 2rem;
    background: var(--surface);
    border-bottom: 1px solid var(--border);
    overflow-x: auto;
    font-family: var(--mono);
    font-size: .72rem;
  }

  .pipe-step {
    display: flex;
    align-items: center;
    gap: .5rem;
    padding: .35rem .9rem;
    border-radius: 20px;
    white-space: nowrap;
    color: var(--muted);
  }
  .pipe-step.active { background: rgba(108,99,255,.15); color: var(--accent); border: 1px solid rgba(108,99,255,.3); }
  .pipe-step .dot { width: 7px; height: 7px; border-radius: 50%; background: currentColor; }
  .pipe-step.green .dot { background: var(--green); }
  .pipe-step.green { color: var(--green); }
  .pipe-arrow { color: var(--border); padding: 0 .3rem; font-size: 1rem; }

  .main { max-width: 1100px; margin: 0 auto; padding: 2rem; }

  .stats-row {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
    gap: 1rem;
    margin-bottom: 2rem;
  }

  .stat-card {
    background: var(--card);
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 1.2rem 1.4rem;
    position: relative;
    overflow: hidden;
  }
  .stat-card::before {
    content: '';
    position: absolute; top: 0; left: 0; right: 0; height: 2px;
    background: linear-gradient(90deg, var(--accent), var(--accent2));
  }
  .stat-label { font-family: var(--mono); font-size: .65rem; color: var(--muted); text-transform: uppercase; letter-spacing: .1em; }
  .stat-value { font-size: 2rem; font-weight: 800; margin-top: .3rem; }
  .stat-value.green { color: var(--green); }
  .stat-value.purple { color: var(--accent); }

  .grid { display: grid; grid-template-columns: 380px 1fr; gap: 1.5rem; align-items: start; }
  @media(max-width: 800px) { .grid { grid-template-columns: 1fr; } }

  .card {
    background: var(--card);
    border: 1px solid var(--border);
    border-radius: 16px;
    overflow: hidden;
  }

  .card-header {
    padding: 1.2rem 1.5rem;
    border-bottom: 1px solid var(--border);
    display: flex;
    align-items: center;
    gap: .75rem;
    font-weight: 700;
    font-size: .9rem;
  }
  .card-header .icon { font-size: 1.1rem; }

  .card-body { padding: 1.5rem; }

  .form-group { margin-bottom: 1.2rem; }
  label { display: block; font-family: var(--mono); font-size: .7rem; color: var(--muted); margin-bottom: .5rem; text-transform: uppercase; letter-spacing: .08em; }

  input, select {
    width: 100%;
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 8px;
    padding: .7rem 1rem;
    color: var(--text);
    font-family: var(--mono);
    font-size: .85rem;
    transition: border-color .2s;
    outline: none;
  }
  input:focus, select:focus { border-color: var(--accent); box-shadow: 0 0 0 3px rgba(108,99,255,.12); }
  select option { background: var(--card); }

  .btn {
    display: inline-flex;
    align-items: center;
    gap: .5rem;
    padding: .75rem 1.5rem;
    border: none;
    border-radius: 8px;
    font-family: var(--sans);
    font-weight: 700;
    font-size: .85rem;
    cursor: pointer;
    transition: all .2s;
    text-decoration: none;
  }
  .btn-primary { background: var(--accent); color: #fff; width: 100%; justify-content: center; }
  .btn-primary:hover { background: #7c74ff; transform: translateY(-1px); box-shadow: 0 4px 20px rgba(108,99,255,.4); }
  .btn-sm { padding: .35rem .75rem; font-size: .75rem; }
  .btn-edit { background: rgba(108,99,255,.15); color: var(--accent); border: 1px solid rgba(108,99,255,.3); }
  .btn-edit:hover { background: rgba(108,99,255,.25); }
  .btn-delete { background: rgba(255,101,132,.1); color: var(--accent2); border: 1px solid rgba(255,101,132,.25); }
  .btn-delete:hover { background: rgba(255,101,132,.2); }

  .alert {
    padding: .9rem 1.2rem;
    border-radius: 10px;
    margin-bottom: 1.5rem;
    font-family: var(--mono);
    font-size: .82rem;
    background: rgba(0,245,160,.08);
    border: 1px solid rgba(0,245,160,.25);
    color: var(--green);
  }

  table { width: 100%; border-collapse: collapse; }
  thead tr { border-bottom: 2px solid var(--border); }
  th {
    text-align: left;
    font-family: var(--mono);
    font-size: .65rem;
    color: var(--muted);
    text-transform: uppercase;
    letter-spacing: .1em;
    padding: .75rem 1rem;
  }
  td { padding: .9rem 1rem; border-bottom: 1px solid rgba(255,255,255,.04); font-size: .88rem; }
  tr:last-child td { border-bottom: none; }
  tr:hover td { background: rgba(255,255,255,.02); }

  .badge {
    display: inline-block;
    padding: .2rem .6rem;
    border-radius: 20px;
    font-family: var(--mono);
    font-size: .65rem;
    font-weight: 700;
  }
  .badge-admin { background: rgba(108,99,255,.2); color: var(--accent); }
  .badge-user  { background: rgba(0,245,160,.1);  color: var(--green); }
  .badge-mod   { background: rgba(255,101,132,.1); color: var(--accent2); }

  .empty { text-align: center; padding: 3rem; color: var(--muted); font-family: var(--mono); font-size: .85rem; }
  .empty .icon { font-size: 2.5rem; display: block; margin-bottom: .75rem; }

  .edit-banner {
    background: rgba(108,99,255,.1);
    border: 1px solid rgba(108,99,255,.3);
    border-radius: 10px;
    padding: .75rem 1rem;
    margin-bottom: 1.2rem;
    font-family: var(--mono);
    font-size: .75rem;
    color: var(--accent);
    display: flex;
    align-items: center;
    gap: .5rem;
  }
</style>
</head>
<body>

<header>
  <span class="logo-badge">ARGOCD</span>
  <h1>PHP CRUD CICD Deploy -- AWS ec27777777777-------- </h1>
  <span>v1.0.0 · <?= date('Y-m-d') ?></span>
</header>

<!-- CI/CD Pipeline indicator -->
<div class="pipeline-bar">
  <div class="pipe-step green"><span class="dot"></span>GitHub Push</div>
  <span class="pipe-arrow">→</span>
  <div class="pipe-step green"><span class="dot"></span>GitHub Actions CI</div>
  <span class="pipe-arrow">→</span>
  <div class="pipe-step green"><span class="dot"></span>Docker Build</div>
  <span class="pipe-arrow">→</span>
  <div class="pipe-step green"><span class="dot"></span>DockerHub Push</div>
  <span class="pipe-arrow">→</span>
  <div class="pipe-step green"><span class="dot"></span>Update Manifest</div>
  <span class="pipe-arrow">→</span>
  <div class="pipe-step active"><span class="dot"></span>ArgoCD Sync</div>
  <span class="pipe-arrow">→</span>
  <div class="pipe-step active"><span class="dot"></span>K8s Deploy ✓</div>
</div>

<div class="main">

  <!-- Stats -->
  <div class="stats-row">
    <div class="stat-card">
      <div class="stat-label">Total Users</div>
      <div class="stat-value purple"><?= $total ?></div>
    </div>
    <div class="stat-card">
      <div class="stat-label">Namespace</div>
      <div class="stat-value" style="font-size:1rem;padding-top:.5rem;font-family:var(--mono)">php-crud</div>
    </div>
    <div class="stat-card">
      <div class="stat-label">ArgoCD Status</div>
      <div class="stat-value green" style="font-size:1.1rem;padding-top:.4rem">Synced ✓</div>
    </div>
    <div class="stat-card">
      <div class="stat-label">DB Host</div>
      <div class="stat-value" style="font-size:.9rem;padding-top:.5rem;font-family:var(--mono)"><?= htmlspecialchars($host) ?></div>
    </div>
  </div>

  <?php if ($msg): ?>
    <div class="alert"><?= htmlspecialchars($msg) ?></div>
  <?php endif; ?>

  <div class="grid">

    <!-- Form -->
    <div class="card">
      <div class="card-header">
        <span class="icon"><?= $edit ? '✏️' : '➕' ?></span>
        <?= $edit ? 'Edit User' : 'Add New User' ?>
      </div>
      <div class="card-body">
        <?php if ($edit): ?>
          <div class="edit-banner">✏️ Editing: <?= htmlspecialchars($edit['name']) ?> · <a href="index.php" style="color:var(--accent2);margin-left:auto">Cancel</a></div>
        <?php endif; ?>
        <form method="POST">
          <?php if ($edit): ?><input type="hidden" name="id" value="<?= $edit['id'] ?>"><?php endif; ?>
          <div class="form-group">
            <label>Full Name</label>
            <input type="text" name="name" placeholder="John Doe" value="<?= htmlspecialchars($edit['name'] ?? '') ?>" required>
          </div>
          <div class="form-group">
            <label>Email Address</label>
            <input type="email" name="email" placeholder="john@example.com" value="<?= htmlspecialchars($edit['email'] ?? '') ?>" required>
          </div>
          <div class="form-group">
            <label>Role</label>
            <select name="role">
              <?php foreach (['User','Admin','Moderator'] as $r): ?>
                <option value="<?= $r ?>" <?= ($edit['role'] ?? 'User') === $r ? 'selected' : '' ?>><?= $r ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <button type="submit" class="btn btn-primary"><?= $edit ? '💾 Update User' : '➕ Create User' ?></button>
        </form>
      </div>
    </div>

    <!-- Table -->
    <div class="card">
      <div class="card-header">
        <span class="icon">👥</span>
        All Users
        <span style="margin-left:auto;font-family:var(--mono);font-size:.7rem;color:var(--muted)"><?= $total ?> records</span>
      </div>
      <?php if (empty($users)): ?>
        <div class="empty"><span class="icon">🌱</span>No users yet.<br>Create your first one!</div>
      <?php else: ?>
        <div style="overflow-x:auto">
        <table>
          <thead>
            <tr><th>#</th><th>Name</th><th>Email</th><th>Role</th><th>Created</th><th>Actions</th></tr>
          </thead>
          <tbody>
            <?php foreach ($users as $u): ?>
            <tr>
              <td style="color:var(--muted);font-family:var(--mono);font-size:.75rem"><?= $u['id'] ?></td>
              <td style="font-weight:700"><?= htmlspecialchars($u['name']) ?></td>
              <td style="font-family:var(--mono);font-size:.78rem;color:var(--muted)"><?= htmlspecialchars($u['email']) ?></td>
              <td>
                <span class="badge badge-<?= strtolower($u['role']) ?>"><?= $u['role'] ?></span>
              </td>
              <td style="font-family:var(--mono);font-size:.72rem;color:var(--muted)"><?= date('M d, Y', strtotime($u['created_at'])) ?></td>
              <td>
                <div style="display:flex;gap:.4rem">
                  <a href="?edit=<?= $u['id'] ?>" class="btn btn-sm btn-edit">Edit</a>
                  <a href="?delete=<?= $u['id'] ?>" class="btn btn-sm btn-delete" onclick="return confirm('Delete this user?')">Del</a>
                </div>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
        </div>
      <?php endif; ?>
    </div>

  </div>
</div>
</body>
</html>
