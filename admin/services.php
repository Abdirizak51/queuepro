<?php
// admin/services.php
require_once __DIR__ . '/../bootstrap.php';
requireRole(ROLE_ADMIN);

$pdo = db();
$pageTitle = 'Services';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';

    $fields = [
        'name'                 => trim($_POST['name']                 ?? ''),
        'description'          => trim($_POST['description']          ?? ''),
        'prefix'               => strtoupper(substr(trim($_POST['prefix'] ?? 'A'), 0, 3)),
        'avg_duration_minutes' => max(1, (int)($_POST['avg_duration_minutes'] ?? 10)),
        'max_capacity'         => max(1, (int)($_POST['max_capacity']          ?? 100)),
        'icon'                 => trim($_POST['icon']                 ?? 'bi-person-badge'),
        'color'                => trim($_POST['color']                ?? '#3b82f6'),
        'status'               => in_array($_POST['status'] ?? '', ['active','inactive']) ? $_POST['status'] : 'active',
        'branch_id'            => (int)($_POST['branch_id']           ?? 1),
    ];

    if (!$fields['name']) {
        flash('error', 'Service name is required.');
        redirect('services.php');
    }

    if ($action === 'create') {

        $pdo->prepare(
            "INSERT INTO services
             (branch_id, name, description, prefix, avg_duration_minutes, max_capacity, icon, color, status)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
        )->execute([
            $fields['branch_id'],
            $fields['name'],
            $fields['description'],
            $fields['prefix'],
            $fields['avg_duration_minutes'],
            $fields['max_capacity'],
            $fields['icon'],
            $fields['color'],
            $fields['status'],
        ]);
        flash('success', 'Service created.');

    } elseif ($action === 'update') {

        $id = (int)$_POST['id'];
        $pdo->prepare(
            "UPDATE services SET
             name                 = ?,
             description          = ?,
             prefix               = ?,
             avg_duration_minutes = ?,
             max_capacity         = ?,
             icon                 = ?,
             color                = ?,
             status               = ?,
             branch_id            = ?
             WHERE id = ?"
        )->execute([
            $fields['name'],
            $fields['description'],
            $fields['prefix'],
            $fields['avg_duration_minutes'],
            $fields['max_capacity'],
            $fields['icon'],
            $fields['color'],
            $fields['status'],
            $fields['branch_id'],
            $id,
        ]);
        flash('success', 'Service updated.');

    } elseif ($action === 'delete') {

        $id = (int)$_POST['id'];
        $pdo->prepare("DELETE FROM services WHERE id = ?")->execute([$id]);
        flash('success', 'Service deleted.');

    }

    redirect('services.php');
}

$services = $pdo->query(
    "SELECT s.*, b.name AS branch_name,
            (SELECT COUNT(*) FROM tickets t
             WHERE t.service_id = s.id AND DATE(t.created_at) = CURDATE()) AS today_tickets
     FROM services s
     JOIN branches b ON b.id = s.branch_id
     ORDER BY s.branch_id, s.name"
)->fetchAll();

$branches = $pdo->query("SELECT id, name FROM branches WHERE status = 'active'")->fetchAll();

require_once __DIR__ . '/../views/partials/header.php';
?>

<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1.5rem">
  <div>
    <h1 style="font-size:1.4rem;font-weight:800">Services</h1>
    <p style="color:var(--muted);font-size:.88rem"><?= count($services) ?> services configured</p>
  </div>
  <button class="btn-brand" data-bs-toggle="modal" data-bs-target="#svcModal" onclick="resetForm()">
    <i class="bi bi-plus-lg"></i> Add Service
  </button>
</div>

<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:1.25rem;margin-bottom:1.5rem">
  <?php foreach ($services as $svc): ?>
  <div class="panel" style="margin-bottom:0">
    <div class="panel-body">
      <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1rem">
        <div style="display:flex;align-items:center;gap:.75rem">
          <div style="width:46px;height:46px;border-radius:12px;background:<?= e($svc['color']) ?>22;
                      display:grid;place-items:center;font-size:1.3rem;color:<?= e($svc['color']) ?>">
            <i class="bi <?= e($svc['icon']) ?>"></i>
          </div>
          <div>
            <div style="font-weight:700;font-size:.95rem"><?= e($svc['name']) ?></div>
            <div style="font-size:.75rem;color:var(--muted)"><?= e($svc['branch_name']) ?></div>
          </div>
        </div>
        <?= statusBadge($svc['status']) ?>
      </div>

      <p style="font-size:.82rem;color:var(--muted);margin-bottom:1rem;min-height:2rem">
        <?= e($svc['description'] ?? '') ?>
      </p>

      <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:.5rem;margin-bottom:1rem">
        <div style="text-align:center;background:var(--bg);border-radius:8px;padding:.5rem">
          <div style="font-size:1.1rem;font-weight:800;color:var(--brand)"><?= e($svc['prefix']) ?></div>
          <div style="font-size:.68rem;color:var(--muted)">Prefix</div>
        </div>
        <div style="text-align:center;background:var(--bg);border-radius:8px;padding:.5rem">
          <div style="font-size:1.1rem;font-weight:800"><?= $svc['avg_duration_minutes'] ?></div>
          <div style="font-size:.68rem;color:var(--muted)">Min/Ticket</div>
        </div>
        <div style="text-align:center;background:var(--bg);border-radius:8px;padding:.5rem">
          <div style="font-size:1.1rem;font-weight:800;color:var(--accent)"><?= $svc['today_tickets'] ?></div>
          <div style="font-size:.68rem;color:var(--muted)">Today</div>
        </div>
      </div>

      <div style="display:flex;gap:.5rem">
        <button class="btn-ghost" style="flex:1;justify-content:center"
                onclick='openEdit(<?= htmlspecialchars(json_encode($svc), ENT_QUOTES) ?>)'>
          <i class="bi bi-pencil"></i> Edit
        </button>
        <form method="POST" onsubmit="return confirm('Delete this service and all its tickets?')">
          <?= csrfField() ?>
          <input type="hidden" name="action" value="delete">
          <input type="hidden" name="id"     value="<?= $svc['id'] ?>">
          <button type="submit" class="btn-danger" style="padding:.5rem .75rem">
            <i class="bi bi-trash"></i>
          </button>
        </form>
      </div>
    </div>
  </div>
  <?php endforeach; ?>

  <?php if (empty($services)): ?>
  <div style="grid-column:1/-1;text-align:center;padding:4rem;color:var(--muted)">
    <i class="bi bi-box-seam" style="font-size:3rem;display:block;margin-bottom:.75rem;opacity:.4"></i>
    No services yet. Click <strong>Add Service</strong> to get started.
  </div>
  <?php endif; ?>
</div>

<div class="modal fade" id="svcModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title fw-bold" id="svcModalTitle">Add Service</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
     <form method="POST" action="services.php">
    <?= csrfField() ?>
    <input type="hidden" name="action" id="svcAction" value="create">
    <input type="hidden" name="id"     id="svcId"     value="">
        <div class="modal-body row g-3">
          <div class="col-12">
            <label class="form-label">Service Name *</label>
            <input type="text" name="name" id="svcName" class="form-control" placeholder="e.g. Lacag Keenida" required>
          </div>
          <div class="col-12">
            <label class="form-label">Description</label>
            <textarea name="description" id="svcDesc" class="form-control" rows="2"></textarea>
          </div>
          <div class="col-md-4">
            <label class="form-label">Ticket Prefix</label>
            <input type="text" name="prefix" id="svcPrefix" class="form-control" maxlength="3" placeholder="A" required>
          </div>
          <div class="col-md-4">
            <label class="form-label">Avg Duration (min)</label>
            <input type="number" name="avg_duration_minutes" id="svcDur" class="form-control" value="10" min="1">
          </div>
          <div class="col-md-4">
            <label class="form-label">Max Capacity</label>
            <input type="number" name="max_capacity" id="svcCap" class="form-control" value="100" min="1">
          </div>
          <div class="col-md-6">
            <label class="form-label">Bootstrap Icon</label>
            <input type="text" name="icon" id="svcIcon" class="form-control" placeholder="bi-cash-coin">
            <small style="color:var(--muted);font-size:.75rem">
              <a href="https://icons.getbootstrap.com" target="_blank">Browse icons →</a>
            </small>
          </div>
          <div class="col-md-3">
            <label class="form-label">Color</label>
            <input type="color" name="color" id="svcColor" class="form-control" value="#3b82f6" style="height:42px">
          </div>
          <div class="col-md-3">
            <label class="form-label">Status</label>
            <select name="status" id="svcStatus" class="form-select">
              <option value="active">Active</option>
              <option value="inactive">Inactive</option>
            </select>
          </div>
          <div class="col-12">
            <label class="form-label">Branch</label>
            <select name="branch_id" id="svcBranch" class="form-select">
              <?php foreach ($branches as $b): ?>
              <option value="<?= $b['id'] ?>"><?= e($b['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn-ghost" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn-brand"><i class="bi bi-check-lg"></i> Save Service</button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php
$extraJs = '
<script>
function resetForm() {
  document.getElementById("svcAction").value           = "create";
  document.getElementById("svcModalTitle").textContent = "Add Service";
  document.getElementById("svcId").value               = "";
  document.getElementById("svcName").value             = "";
  document.getElementById("svcDesc").value             = "";
  document.getElementById("svcPrefix").value           = "A";
  document.getElementById("svcDur").value              = "10";
  document.getElementById("svcCap").value              = "100";
  document.getElementById("svcIcon").value             = "bi-person-badge";
  document.getElementById("svcColor").value            = "#3b82f6";
  document.getElementById("svcStatus").value           = "active";
}

function openEdit(s) {
  document.getElementById("svcAction").value           = "update";
  document.getElementById("svcModalTitle").textContent = "Edit Service";
  document.getElementById("svcId").value               = s.id;
  document.getElementById("svcName").value             = s.name;
  document.getElementById("svcDesc").value             = s.description ?? "";
  document.getElementById("svcPrefix").value           = s.prefix;
  document.getElementById("svcDur").value              = s.avg_duration_minutes;
  document.getElementById("svcCap").value              = s.max_capacity;
  document.getElementById("svcIcon").value             = s.icon;
  document.getElementById("svcColor").value            = s.color;
  document.getElementById("svcStatus").value           = s.status;
  document.getElementById("svcBranch").value           = s.branch_id;
  new bootstrap.Modal(document.getElementById("svcModal")).show();
}
</script>';

require_once __DIR__ . '/../views/partials/footer.php';
?>