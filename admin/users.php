<?php
// admin/users.php
require_once __DIR__ . '/../bootstrap.php';
requireRole(ROLE_ADMIN);

$pdo   = db();
$pageTitle = 'User Management';

// ---- Handle POST actions ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'create' || $action === 'update') {
        $id       = (int)($_POST['id'] ?? 0);
        $fullName = trim($_POST['full_name'] ?? '');
        $email    = trim($_POST['email']     ?? '');
        $phone    = trim($_POST['phone']     ?? '');
        $roleId   = (int)($_POST['role_id']  ?? ROLE_CUSTOMER);
        $branchId = (int)($_POST['branch_id']?? 1);
        $status   = in_array($_POST['status']??'', ['active','inactive','blocked']) ? $_POST['status'] : 'active';

        if (!$fullName || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            flash('error', 'Name and valid email are required.');
            redirect('users.php');
        }

        if ($action === 'create') {
            $pw = $_POST['password'] ?? '';
            if (strlen($pw) < 8) { flash('error', 'Password must be 8+ characters.'); redirect('users.php'); }
            $hash = password_hash($pw, PASSWORD_BCRYPT, ['cost' => PASSWORD_COST]);
            try {
                $pdo->prepare(
                    "INSERT INTO users (branch_id,role_id,full_name,email,phone,password_hash,status,email_verified_at)
                     VALUES (?,?,?,?,?,?,?,NOW())"
                )->execute([$branchId, $roleId, $fullName, $email, $phone, $hash, $status]);
                logActivity('user_create', "Created user: $email");
                flash('success', 'User created successfully.');
            } catch (PDOException $e) {
                flash('error', 'Email already exists.');
            }
        } else {
            $params = [$fullName, $email, $phone, $roleId, $branchId, $status, $id];
            $sql = "UPDATE users SET full_name=?,email=?,phone=?,role_id=?,branch_id=?,status=?";
            if (!empty($_POST['password'])) {
                $sql .= ",password_hash=?";
                array_splice($params, 6, 0, [password_hash($_POST['password'], PASSWORD_BCRYPT)]);
            }
            $pdo->prepare($sql . " WHERE id=?")->execute($params);
            logActivity('user_update', "Updated user ID: $id");
            flash('success', 'User updated.');
        }
        redirect('users.php');
    }

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id === (int)$_SESSION['user_id']) { flash('error', 'Cannot delete yourself.'); redirect('users.php'); }
        $pdo->prepare("DELETE FROM users WHERE id=?")->execute([$id]);
        logActivity('user_delete', "Deleted user ID: $id");
        flash('success', 'User deleted.');
        redirect('users.php');
    }

    if ($action === 'toggle_status') {
        $id  = (int)($_POST['id']     ?? 0);
        $cur = $_POST['current_status'] ?? 'active';
        $new = $cur === 'active' ? 'blocked' : 'active';
        $pdo->prepare("UPDATE users SET status=? WHERE id=?")->execute([$new, $id]);
        flash('success', 'Status updated.');
        redirect('users.php');
    }
}

// ---- Fetch data ----
$search   = trim($_GET['search'] ?? '');
$roleFilter = (int)($_GET['role'] ?? 0);
$page     = max(1, (int)($_GET['page'] ?? 1));

$where  = "WHERE 1";
$params = [];
if ($search) {
    $where .= " AND (u.full_name LIKE :s OR u.email LIKE :s2 OR u.phone LIKE :s3)";
    $params[':s'] = $params[':s2'] = $params[':s3'] = "%$search%";
}
if ($roleFilter) {
    $where .= " AND u.role_id = :role";
    $params[':role'] = $roleFilter;
}

$paged = paginate(
    "SELECT u.*, r.display_name AS role_display, b.name AS branch_name
     FROM users u
     JOIN roles r ON r.id=u.role_id
     LEFT JOIN branches b ON b.id=u.branch_id
     $where ORDER BY u.created_at DESC",
    $params, $page
);
$users    = $paged['data'];
$roles    = $pdo->query("SELECT * FROM roles")->fetchAll();
$branches = $pdo->query("SELECT id,name FROM branches WHERE status='active'")->fetchAll();

require_once __DIR__ . '/../views/partials/header.php';
?>

<!-- Page header -->
<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1.5rem">
  <div>
    <h1 style="font-size:1.4rem;font-weight:800;margin-bottom:.2rem">Users</h1>
    <p style="color:var(--muted);font-size:.88rem"><?= number_format($paged['total']) ?> total members</p>
  </div>
  <button class="btn-brand" data-bs-toggle="modal" data-bs-target="#createModal">
    <i class="bi bi-person-plus-fill"></i> Add User
  </button>
</div>

<!-- Filters -->
<div class="panel" style="margin-bottom:1.25rem">
  <div class="panel-body" style="padding:.85rem 1.25rem">
    <form method="GET" style="display:flex;gap:.75rem;align-items:center;flex-wrap:wrap">
      <div style="flex:1;min-width:200px;position:relative">
        <i class="bi bi-search" style="position:absolute;left:.75rem;top:50%;transform:translateY(-50%);color:var(--muted)"></i>
        <input type="text" name="search" class="form-control" style="padding-left:2.3rem"
               placeholder="Search by name, email, phone…" value="<?= e($search) ?>">
      </div>
      <select name="role" class="form-select" style="width:160px">
        <option value="">All Roles</option>
        <?php foreach ($roles as $r): ?>
        <option value="<?= $r['id'] ?>" <?= $roleFilter==$r['id']?'selected':'' ?>><?= e($r['display_name']) ?></option>
        <?php endforeach; ?>
      </select>
      <button type="submit" class="btn-brand"><i class="bi bi-search"></i> Filter</button>
      <?php if ($search || $roleFilter): ?>
      <a href="users.php" class="btn-ghost"><i class="bi bi-x"></i> Clear</a>
      <?php endif; ?>
    </form>
  </div>
</div>

<!-- Table -->
<div class="panel">
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>User</th><th>Email</th><th>Phone</th><th>Branch</th>
          <th>Role</th><th>Status</th><th>Joined</th><th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($users as $u): ?>
        <tr>
          <td>
            <div style="display:flex;align-items:center;gap:.65rem">
              <div style="width:36px;height:36px;background:var(--brand);border-radius:50%;
                          display:grid;place-items:center;color:#fff;font-weight:700;font-size:.85rem;flex-shrink:0">
                <?= strtoupper(substr($u['full_name'],0,1)) ?>
              </div>
              <strong><?= e($u['full_name']) ?></strong>
            </div>
          </td>
          <td><?= e($u['email']) ?></td>
          <td><?= e($u['phone'] ?? '–') ?></td>
          <td><?= e($u['branch_name'] ?? '–') ?></td>
          <td><span class="badge bg-secondary"><?= e($u['role_display']) ?></span></td>
          <td><?= statusBadge($u['status']) ?></td>
          <td style="color:var(--muted);font-size:.8rem"><?= date('d M Y', strtotime($u['created_at'])) ?></td>
          <td>
            <div style="display:flex;gap:.4rem">
              <!-- Edit -->
              <button class="btn-ghost" style="padding:.3rem .6rem;font-size:.8rem"
                onclick="openEdit(<?= htmlspecialchars(json_encode($u), ENT_QUOTES) ?>)">
                <i class="bi bi-pencil"></i>
              </button>
              <!-- Toggle status -->
              <form method="POST" style="display:inline">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="toggle_status">
                <input type="hidden" name="id" value="<?= $u['id'] ?>">
                <input type="hidden" name="current_status" value="<?= $u['status'] ?>">
                <button type="submit" class="btn-ghost" style="padding:.3rem .6rem;font-size:.8rem"
                  title="<?= $u['status']==='active' ? 'Block' : 'Activate' ?>">
                  <i class="bi bi-<?= $u['status']==='active' ? 'slash-circle' : 'check-circle' ?>"></i>
                </button>
              </form>
              <!-- Delete -->
              <?php if ($u['id'] != $_SESSION['user_id']): ?>
              <form method="POST" onsubmit="return confirm('Delete this user?')">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?= $u['id'] ?>">
                <button type="submit" class="btn-danger" style="padding:.3rem .6rem;font-size:.8rem">
                  <i class="bi bi-trash"></i>
                </button>
              </form>
              <?php endif; ?>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($users)): ?>
        <tr><td colspan="8" style="text-align:center;color:var(--muted);padding:3rem">No users found.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <!-- Pagination -->
  <?php if ($paged['last_page'] > 1): ?>
  <div style="padding:1rem 1.25rem;border-top:1px solid var(--border);display:flex;gap:.5rem;justify-content:center">
    <?php for ($i=1; $i<=$paged['last_page']; $i++): ?>
    <a href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&role=<?= $roleFilter ?>"
       style="min-width:36px;height:36px;border-radius:8px;display:grid;place-items:center;
              font-size:.85rem;font-weight:600;text-decoration:none;
              <?= $i==$page ? 'background:var(--brand);color:#fff' : 'border:1px solid var(--border);color:var(--text)' ?>">
      <?= $i ?>
    </a>
    <?php endfor; ?>
  </div>
  <?php endif; ?>
</div>

<!-- ===== CREATE MODAL ===== -->
<div class="modal fade" id="createModal" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title fw-bold">Add New User</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form method="POST">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="create">
        <div class="modal-body">
          <?php include __DIR__ . '/../views/partials/user-form.php'; ?>
          <div class="mb-3">
            <label class="form-label">Password *</label>
            <input type="password" name="password" class="form-control" placeholder="Min 8 characters" required>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn-ghost" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn-brand"><i class="bi bi-person-plus"></i> Create User</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- ===== EDIT MODAL ===== -->
<div class="modal fade" id="editModal" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title fw-bold">Edit User</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form method="POST">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="update">
        <input type="hidden" name="id" id="editId">
        <div class="modal-body">
          <?php include __DIR__ . '/../views/partials/user-form.php'; ?>
          <div class="mb-3">
            <label class="form-label">New Password <span style="color:var(--muted)">(leave blank to keep current)</span></label>
            <input type="password" name="password" class="form-control" placeholder="Leave blank to keep">
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn-ghost" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn-brand"><i class="bi bi-check-lg"></i> Save Changes</button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php
$extraJs = '
<script>
function openEdit(u) {
  document.getElementById("editId").value = u.id;
  ["full_name","email","phone"].forEach(f => {
    const el = document.querySelector("#editModal [name="+f+"]");
    if (el) el.value = u[f] ?? "";
  });
  const rs = document.querySelector("#editModal [name=role_id]");
  if (rs) rs.value = u.role_id;
  const bs = document.querySelector("#editModal [name=branch_id]");
  if (bs) bs.value = u.branch_id;
  const ss = document.querySelector("#editModal [name=status]");
  if (ss) ss.value = u.status;
  new bootstrap.Modal(document.getElementById("editModal")).show();
}
</script>';
require_once __DIR__ . '/../views/partials/footer.php';
?>
