<?php
// admin/branches.php
require_once __DIR__ . '/../bootstrap.php';
requireRole(ROLE_ADMIN);

$pdo = db();
$pageTitle = 'Branches';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';
    $id     = (int)($_POST['id'] ?? 0);

    $fields = [
        'name'    => trim($_POST['name']    ?? ''),
        'address' => trim($_POST['address'] ?? ''),
        'phone'   => trim($_POST['phone']   ?? ''),
        'email'   => trim($_POST['email']   ?? ''),
        'city'    => trim($_POST['city']    ?? ''),
        'country' => trim($_POST['country'] ?? 'Somalia'),
        'status'  => in_array($_POST['status']??'', ['active','inactive']) ? $_POST['status'] : 'active',
    ];

    if (!$fields['name']) { flash('error','Branch name required.'); redirect('branches.php'); }

    if ($action === 'create') {
        $pdo->prepare("INSERT INTO branches (name,address,phone,email,city,country,status) VALUES (?,?,?,?,?,?,?)")
            ->execute(array_values($fields));
        flash('success','Branch created.');
    } elseif ($action === 'update' && $id) {
        $pdo->prepare("UPDATE branches SET name=?,address=?,phone=?,email=?,city=?,country=?,status=? WHERE id=?")
            ->execute(array_merge(array_values($fields), [$id]));
        flash('success','Branch updated.');
    } elseif ($action === 'delete' && $id) {
        $pdo->prepare("DELETE FROM branches WHERE id=?")->execute([$id]);
        flash('success','Branch deleted.');
    }
    redirect('branches.php');
}

$branches = $pdo->query(
    "SELECT b.*,
            (SELECT COUNT(*) FROM users u WHERE u.branch_id=b.id) AS user_count,
            (SELECT COUNT(*) FROM services s WHERE s.branch_id=b.id AND s.status='active') AS service_count,
            (SELECT COUNT(*) FROM tickets t WHERE t.branch_id=b.id AND DATE(t.created_at)=CURDATE()) AS today_tickets
     FROM branches b ORDER BY b.id"
)->fetchAll();

require_once __DIR__ . '/../views/partials/header.php';
?>

<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1.5rem">
  <div>
    <h1 style="font-size:1.4rem;font-weight:800">Branches</h1>
    <p style="color:var(--muted);font-size:.88rem"><?= count($branches) ?> locations</p>
  </div>
  <button class="btn-brand" data-bs-toggle="modal" data-bs-target="#branchModal" onclick="resetForm()">
    <i class="bi bi-building-add"></i> Add Branch
  </button>
</div>

<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:1.25rem">
  <?php foreach ($branches as $b): ?>
  <div class="panel" style="margin-bottom:0">
    <div class="panel-body">
      <div style="display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:1rem">
        <div style="display:flex;align-items:center;gap:.75rem">
          <div style="width:44px;height:44px;background:rgba(27,79,216,.1);border-radius:12px;
                      display:grid;place-items:center;font-size:1.3rem;color:var(--brand)">
            <i class="bi bi-building"></i>
          </div>
          <div>
            <div style="font-weight:700"><?= e($b['name']) ?></div>
            <div style="font-size:.75rem;color:var(--muted)"><?= e($b['city']) ?>, <?= e($b['country']) ?></div>
          </div>
        </div>
        <?= statusBadge($b['status']) ?>
      </div>

      <?php if ($b['address']): ?>
      <div style="font-size:.82rem;color:var(--muted);margin-bottom:.5rem">
        <i class="bi bi-geo-alt"></i> <?= e($b['address']) ?>
      </div>
      <?php endif; ?>
      <?php if ($b['phone']): ?>
      <div style="font-size:.82rem;color:var(--muted);margin-bottom:.5rem">
        <i class="bi bi-telephone"></i> <?= e($b['phone']) ?>
      </div>
      <?php endif; ?>
      <?php if ($b['email']): ?>
      <div style="font-size:.82rem;color:var(--muted);margin-bottom:1rem">
        <i class="bi bi-envelope"></i> <?= e($b['email']) ?>
      </div>
      <?php endif; ?>

      <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:.5rem;margin-bottom:1rem">
        <div style="text-align:center;background:var(--bg);border-radius:8px;padding:.5rem">
          <div style="font-weight:800;color:var(--brand)"><?= $b['user_count'] ?></div>
          <div style="font-size:.67rem;color:var(--muted)">Users</div>
        </div>
        <div style="text-align:center;background:var(--bg);border-radius:8px;padding:.5rem">
          <div style="font-weight:800;color:var(--accent)"><?= $b['service_count'] ?></div>
          <div style="font-size:.67rem;color:var(--muted)">Services</div>
        </div>
        <div style="text-align:center;background:var(--bg);border-radius:8px;padding:.5rem">
          <div style="font-weight:800;color:#f97316"><?= $b['today_tickets'] ?></div>
          <div style="font-size:.67rem;color:var(--muted)">Today</div>
        </div>
      </div>

      <div style="display:flex;gap:.5rem">
        <button class="btn-ghost" style="flex:1;justify-content:center"
                onclick='openEdit(<?= htmlspecialchars(json_encode($b), ENT_QUOTES) ?>)'>
          <i class="bi bi-pencil"></i> Edit
        </button>
        <a href="queue-display.php?branch=<?= $b['id'] ?>" class="btn-ghost" target="_blank"
           style="padding:.5rem .75rem" title="Live Display">
          <i class="bi bi-display"></i>
        </a>
        <form method="POST" onsubmit="return confirm('Delete branch and all its data?')">
          <?= csrfField() ?>
          <input type="hidden" name="action" value="delete">
          <input type="hidden" name="id" value="<?= $b['id'] ?>">
          <button type="submit" class="btn-danger" style="padding:.5rem .75rem"><i class="bi bi-trash"></i></button>
        </form>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<!-- Branch Modal -->
<div class="modal fade" id="branchModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title fw-bold" id="bModalTitle">Add Branch</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form method="POST">
        <?= csrfField() ?>
        <input type="hidden" name="action" id="bAction" value="create">
        <input type="hidden" name="id"     id="bId">
        <div class="modal-body row g-3">
          <div class="col-12">
            <label class="form-label">Branch Name *</label>
            <input type="text" name="name" id="bName" class="form-control" placeholder="Main Branch – Mogadishu" required>
          </div>
          <div class="col-12">
            <label class="form-label">Address</label>
            <input type="text" name="address" id="bAddr" class="form-control" placeholder="Street, District">
          </div>
          <div class="col-md-6">
            <label class="form-label">City</label>
            <input type="text" name="city" id="bCity" class="form-control" placeholder="Mogadishu">
          </div>
          <div class="col-md-6">
            <label class="form-label">Country</label>
            <input type="text" name="country" id="bCountry" class="form-control" value="Somalia">
          </div>
          <div class="col-md-6">
            <label class="form-label">Phone</label>
            <input type="tel" name="phone" id="bPhone" class="form-control" placeholder="+252 61 000 0000">
          </div>
          <div class="col-md-6">
            <label class="form-label">Email</label>
            <input type="email" name="email" id="bEmail" class="form-control" placeholder="branch@example.com">
          </div>
          <div class="col-md-6">
            <label class="form-label">Status</label>
            <select name="status" id="bStatus" class="form-select">
              <option value="active">Active</option>
              <option value="inactive">Inactive</option>
            </select>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn-ghost" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn-brand"><i class="bi bi-check-lg"></i> Save</button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php
$extraJs = '
<script>
function resetForm() {
  ["bId","bName","bAddr","bCity","bPhone","bEmail"].forEach(id => {
    const el = document.getElementById(id); if(el) el.value="";
  });
  document.getElementById("bCountry").value = "Somalia";
  document.getElementById("bStatus").value  = "active";
  document.getElementById("bAction").value  = "create";
  document.getElementById("bModalTitle").textContent = "Add Branch";
}
function openEdit(b) {
  document.getElementById("bAction").value  = "update";
  document.getElementById("bModalTitle").textContent = "Edit Branch";
  document.getElementById("bId").value      = b.id;
  document.getElementById("bName").value    = b.name;
  document.getElementById("bAddr").value    = b.address ?? "";
  document.getElementById("bCity").value    = b.city ?? "";
  document.getElementById("bCountry").value = b.country ?? "Somalia";
  document.getElementById("bPhone").value   = b.phone ?? "";
  document.getElementById("bEmail").value   = b.email ?? "";
  document.getElementById("bStatus").value  = b.status;
  new bootstrap.Modal(document.getElementById("branchModal")).show();
}
</script>';
require_once __DIR__ . '/../views/partials/footer.php';
?>
